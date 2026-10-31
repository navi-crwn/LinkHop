# Laporan Perbaikan SSO HEL.ink ↔ PixelHop + Login/Daftar Lambat

Tanggal: 2026-10-27
Repo:
- `navi-crwn/LinkHop` (sebelumnya `navi-crwn/hel.ink`)
- `navi-crwn/PixelHop`

Label bukti: **TERBUKTI** = dibuktikan dengan kode + tes yang gagal sebelum perbaikan dan lulus sesudahnya. **DUGAAN** = belum bisa dibuktikan tanpa akses ke server.

---

## 1. Ringkasan

| # | Temuan | Bukti | Status |
|---|---|---|---|
| 1 | Akun HEL.ink yang emailnya **belum diverifikasi** bisa masuk ke PixelHop dan upload, lewat form login atau daftar milik PixelHop. Jalur tombol "Continue with HEL.ink" sudah mewajibkan verifikasi, jalur API tidak. | TERBUKTI | **Diperbaiki** di kedua sisi |
| 2 | Batas percobaan `/api/pixelhop/*` dihitung per IP **server PixelHop**, jadi semua pengunjung PixelHop berbagi satu kuota 60/menit. Satu penyerang bisa membuat login semua orang terkunci. | TERBUKTI | **Diperbaiki**: batas per IP pengunjung asli |
| 3 | Login lewat API PixelHop melewati IP ban, watchlist, dan riwayat login HEL.ink. | TERBUKTI | **Diperbaiki** |
| 4 | Turnstile di PixelHop *fail-open*: kalau key-nya kosong, semua request diloloskan, padahal form PixelHop memeriksa password HEL.ink. | TERBUKTI | **Diperbaiki**: fail-closed saat SSO aktif |
| 5 | PixelHop belum memeriksa klaim `aud` di tiket. Tiket untuk NoteHop akan diterima PixelHop kalau secret-nya kebetulan sama. | TERBUKTI | **Diperbaiki** |
| 6 | **Daftar** HEL.ink lambat: dua email (verifikasi + welcome) dikirim lewat SMTP **sebelum** halaman dibalas. | TERBUKTI dari kode | **Diperbaiki**: dikirim setelah respons |
| 7 | **Login** HEL.ink lambat. | DUGAAN | Perlu diukur di VPS (lihat bagian 5) |
| 8 | RemoteHop: kodenya tidak ada di GitHub (repo kosong) dan hel.ink tidak punya konfigurasi RemoteHop. | — | Perlu dicek di VPS (lihat bagian 6) |

Sudah aman dari sebelumnya, tidak diubah:
- **Tiket SSO:** HMAC, berlaku 2 menit, `state` terikat cookie, sekali pakai, alamat kembali dikunci.
- **NoteHop:** sudah memakai secret sendiri (`NOTEHOP_SSO_SECRET`) dan mengirim `aud` (PR #29).
- **Tanda tangan API:** berjangka waktu dan dibandingkan dengan `hash_equals`.

---

## 2. Perubahan di hel.ink

| File | Perubahan |
|---|---|
| `app/Http/Controllers/Api/PixelHopAccountController.php` | Lihat rincian di bawah tabel. |
| `routes/api.php` | Batas bersama untuk server PixelHop dinaikkan 60 → 300/menit, karena batas per pengunjung sekarang ada di controller. |
| `app/Models/LoginHistory.php` | Metode baru `keepLatest()`, yang menyimpan 5 login terakhir. Dipakai login web dan login PixelHop. |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Memakai `LoginHistory::keepLatest()`. Perilakunya sama. |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Email verifikasi dan welcome dikirim lewat `defer()`, yaitu setelah respons terkirim. Tidak butuh queue worker. |
| `scripts/sso-check.sh` | Script cek SSO di VPS (read-only). Sekarang juga mencakup NoteHop. |
| `scripts/auth-timing.sh` | Script ukur kenapa login/daftar lambat (read-only). |
| Tes | `tests/Feature/PixelHopAccountApiTest.php` (+4 tes) dan `tests/Feature/Auth/RegistrationTest.php` (+1 tes). |

Rincian perubahan di `PixelHopAccountController.php`:
- Login API menolak akun yang belum verifikasi dengan `403 unverified`. Pengecualian: akun Google, sama seperti di web.
- IP ban (`ip_bans`) diterapkan ke IP pengunjung. IP ini dikirim PixelHop di body yang ikut ditandatangani.
- Ada batas 30 kali gagal per 15 menit per IP pengunjung, di samping batas 10 kali per email yang sudah ada.
- User yang di-suspend dan mencoba login dicatat ke `ip_watchlist`.
- Login yang berhasil dicatat ke `login_histories` dengan browser `PixelHop`.
- Email untuk pendaftaran lewat PixelHop juga dikirim setelah respons.

Tidak ada migrasi database.

## 3. Perubahan di PixelHop

| File | Perubahan |
|---|---|
| `internal/app/helink_api.go` | `accountFromHelink` menolak semua akun yang belum verifikasi, sebagai pengaman kedua setelah hel.ink. Kode `unverified` dan `banned` dari hel.ink ditampilkan sebagai pesan yang jelas. `helinkRegister` tidak lagi langsung memasukkan user: akun dibuat di HEL.ink, lalu user diminta konfirmasi email dan login. Profil PixelHop baru dibuat saat login pertama setelah email dikonfirmasi. |
| `internal/app/auth.go`, `web/templates/login.html` | Setelah daftar, user diarahkan ke halaman login dengan pesan "Account created. Open the link HEL.ink sent to … to confirm your email, then sign in." |
| `internal/app/helink.go` | `turnstileOK` menolak semua request kalau SSO HEL.ink aktif tapi key Turnstile kosong. Tiket dengan `aud` selain `pixelhop` ditolak; tiket tanpa `aud` (format lama) masih diterima. URL siteverify dijadikan variabel supaya bisa dites. |
| `.env.example` | Menjelaskan bahwa Turnstile wajib diisi kalau `HELINK_URL` diisi. |
| Tes | `helink_api_test.go`, `helink_test.go` (+3 tes baru; tes daftar diperbarui). |

## 4. Dampak ke pengguna

- Pengguna yang belum verifikasi email tidak bisa masuk PixelHop sampai mengklik link verifikasi dari HEL.ink. Ini memang tujuannya, karena PixelHop adalah image hosting.
- Daftar dari PixelHop sekarang menampilkan "cek email Anda", bukan langsung masuk dashboard.
- Kalau `TURNSTILE_SITE_KEY` atau `TURNSTILE_SECRET_KEY` PixelHop kosong, form login/daftar PixelHop selalu menolak. Tombol "Continue with HEL.ink" tetap berfungsi.

---

## 5. Login/daftar HEL.ink lambat

**Daftar (TERBUKTI, sudah diperbaiki).** Sebelumnya `RegisteredUserController::store` menjalankan `event(new Registered)` (email verifikasi lewat SMTP) lalu `Mail::send(WelcomeEmail)` (SMTP lagi). Keduanya selesai dulu baru halaman dibalas, padahal satu email bisa makan 1–5 detik tergantung server SMTP. Sekarang keduanya ada di `defer()`. Tes `sign-up emails are sent after the response, not before it` gagal sebelum perbaikan dan lulus sesudahnya.

**Login (DUGAAN, perlu diukur).** Yang berjalan sinkron saat login hanya cek password (bcrypt) dan panggilan Turnstile ke Cloudflare (batas waktu 5 detik). GeoIP dan deteksi proxy sudah di-`defer`. Kemungkinan penyebab:
1. Koneksi VPS ke `challenges.cloudflare.com` lambat.
2. VPS sedang sibuk. Sekarang ada HEL.ink, pichost, PixelHop, NoteHop dan RemoteHop di server yang sama, sehingga CPU, RAM, atau worker php-fpm bisa habis.
3. Halaman tujuan setelah login (dashboard) yang lambat, bukan proses login-nya.

Cara mengukur di VPS (read-only):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/scripts/auth-timing.sh -o /tmp/auth-timing.sh
sudo bash /tmp/auth-timing.sh /var/www/helink/shortlink-app
```

Script ini mengukur:
- Waktu respons `/login` dan `/register`.
- Waktu panggilan ke Turnstile.
- Waktu koneksi SMTP.
- Load, RAM dan proses terberat.
- Jumlah worker php-fpm.
- Error timeout di log Laravel.

Kirim output-nya untuk dianalisis.

## 6. Cek SSO di VPS (termasuk RemoteHop)

File `07-sso-check.sh` sebelumnya hanya dikirim sebagai lampiran, jadi tidak ada di server. Sekarang bisa diunduh langsung:

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/scripts/sso-check.sh -o /tmp/sso-check.sh
sudo bash /tmp/sso-check.sh /var/www/helink/shortlink-app /pixelhop /remotehop /notehop
```

Ganti path kalau folder aplikasinya berbeda; kosongkan argumen untuk aplikasi yang tidak ada. Perintah ini adalah catatan historis; cocokkan dengan tata letak deployment saat ini sebelum menjalankannya.

Script ini tidak menampilkan isi secret, hanya sidik jari (12 karakter pertama sha256). Yang perlu dilaporkan:
1. Apakah ada dua aplikasi dengan sidik jari secret yang sama. Seharusnya setiap aplikasi punya secret sendiri.
2. Endpoint HEL.ink mana saja yang dipanggil RemoteHop.
3. Apakah ada file SSO di server HEL.ink yang belum di-commit.
4. Apakah Turnstile PixelHop sudah terisi. **Ini wajib sebelum deploy PixelHop.**

---

## 7. Cara deploy

**Prasyarat:**
- Backup dulu (`handoff/scripts/01-backup.sh` atau prosedur biasa).
- Pastikan `TURNSTILE_SITE_KEY` dan `TURNSTILE_SECRET_KEY` sudah terisi di env PixelHop. Cek dengan `sso-check.sh`: sidik jarinya tidak boleh `(empty)`.

**Urutan:** hel.ink dulu, lalu PixelHop, di sesi yang sama. Kalau sempat ada jeda, keduanya tetap aman:
- hel.ink baru + PixelHop lama: akun yang belum verifikasi ditolak, hanya pesannya kurang jelas ("email and password don't match").
- PixelHop baru + hel.ink lama: PixelHop sendiri yang menolak akun yang belum verifikasi.

**hel.ink** (tidak ada migrasi):

```bash
cd /var/www/helink/shortlink-app
sudo -u www-data git pull origin main     # setelah PR di-merge
sudo -u www-data env HOME=/tmp php8.3 artisan optimize:clear
sudo -u www-data env HOME=/tmp php8.3 artisan config:cache
sudo -u www-data env HOME=/tmp php8.3 artisan route:cache
sudo systemctl reload php8.3-fpm
```

**PixelHop:** pull branch atau `main` setelah merge, lalu `make build` dan restart service PixelHop seperti deploy biasa.

**Cek setelah deploy:**
1. Buat akun baru dari form daftar PixelHop. Harus muncul "Account created… confirm your email", dan user belum masuk.
2. Login di PixelHop dengan akun itu sebelum verifikasi. Harus ditolak dengan pesan "Confirm your email first…".
3. Klik link verifikasi, lalu login lagi di PixelHop. Harus berhasil.
4. Daftar akun di hel.ink. Halaman verifikasi harus muncul cepat, dan email tetap masuk beberapa detik kemudian.
5. Di admin hel.ink, riwayat login user yang masuk lewat PixelHop harus tercatat dengan browser "PixelHop".

**Rollback:** `git checkout <commit sebelumnya>`, lalu ulangi langkah cache dan restart. Tidak ada perubahan database.

---

## 8. Hasil tes

- hel.ink: `php artisan test` → **216 passed**. `vendor/bin/pint --test` → passed.
- PixelHop: `go vet ./...` → bersih. `go test -race ./...` → **ok**. `make build` → ok.

## 9. Belum dikerjakan (perlu keputusan atau akses server)

- **RemoteHop:** belum bisa diperiksa sampai ada output `sso-check.sh` atau kodenya di-push ke GitHub.
- **Ringan, belum diubah:**
  - Daftar tiket yang sudah dipakai di PixelHop disimpan di memori, sehingga hilang saat restart.
  - Pesan "email sudah terdaftar" saat daftar memberi tahu email mana yang punya akun.
- **Penyebab pasti login lambat:** menunggu output `auth-timing.sh`.
