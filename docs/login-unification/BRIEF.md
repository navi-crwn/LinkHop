# Brief: Satu Halaman Login untuk Semua Aplikasi HEL.ink

Untuk: sesi Claude di VPS yang merapikan semua halaman login (HEL.ink, PixelHop, NoteHop, dan aplikasi berikutnya).
Disusun: 2026-10-27 oleh sesi cloud. Keputusan desain di bawah sudah disetujui pemilik.

Aturan kerja yang berlaku:
- Production read-only kecuali pemilik mengizinkan.
- Backup dulu.
- Jangan tampilkan isi `.env`.
- Repo publik: jangan commit secret.
- Jangan kreatif tanpa izin.
- PixelHop dan NoteHop sedang dikerjakan sesi lain, jadi selalu tarik perubahan terbaru dulu dan koordinasikan.

---

## 1. Keputusan pemilik

1. **Satu tempat login: hel.ink.**
   - Semua akun hanya ada di tabel `users` HEL.ink. Ini "database master". Database tidak digabung.
   - Tiap aplikasi menyimpan profilnya sendiri yang tertaut ke ID HEL.ink (`helink_id` / `sub`).
2. **Aplikasi lain tidak punya login sendiri.** Tidak ada form email/password, tidak ada daftar manual, tidak ada tombol Google.
   - Yang ada hanya tombol **"Login with HEL.ink"**.
   - Tombol itu membuka **popup** berisi dua tombol:
     - **Login with HEL.ink**, untuk yang sudah punya akun, ke `/auth/...` aplikasi lalu ke `hel.ink/sso/{app}`.
     - **Join with HEL.ink**, untuk yang belum punya akun, ke `hel.ink/join/{app}`, yaitu halaman daftar HEL.ink. Setelah verifikasi email, pengguna kembali ke aplikasi.
3. **Metode login hanya ada di hel.ink/login:** email + password, Google, 2FA, verifikasi email, dan metode lain nanti.
4. **Welcome page muncul sekali** setelah daftar atau verifikasi pertama. Isinya memperkenalkan aplikasi (HEL.ink, PixelHop, NoteHop) beserta tombol buka masing-masing.
5. **Aplikasi baru diumumkan lewat pengumuman**, bukan dengan menampilkan welcome lagi. Ini bagian dari rencana dashboard admin global (bagian 6), tahap berikutnya.

## 2. Keadaan sekarang (sudah dicek)

**hel.ink production = GitHub `main` @ `e8eaede`.** Tidak ada kode SSO yang belum di-commit.
- `AppSsoController` melayani `/sso/pixelhop` dan `/sso/notehop`.
- Tiket berisi `aud` dan berlaku 2 menit.
- Secret tiap aplikasi sudah berbeda (dicek lewat sidik jari).

**hel.ink masih punya `/api/pixelhop/login` dan `/register`**, yaitu API password untuk form login milik PixelHop.
- API ini dihapus begitu form PixelHop dihapus.
- Sementara masih hidup, PR navi-crwn/hel.ink#30 mengamankannya: hanya email terverifikasi, IP ban, limit per IP pengunjung, dan riwayat login.

**PixelHop masih punya form login, form daftar, dan Google sendiri.**
- Google sendiri membuat akun khusus PixelHop yang tidak ada di HEL.ink. Ini yang harus dihilangkan.
- PR navi-crwn/pixelhop#2 mengamankan form-form itu sementara. Kalau form langsung dihapus, PR #2 cukup diambil bagian pemeriksaan `aud` tiket, lalu PR-nya ditutup.

**NoteHop menurut dokumentasi `AppSsoController` tidak punya login sendiri.** Pastikan di kodenya.

**RemoteHop:** repo GitHub kosong dan tidak ada konfigurasi di hel.ink.
- Folder `/pixelhop`, `/notehop` dan `/remotehop` tidak ditemukan. Cari lokasi aslinya dengan:
  ```bash
  sudo find / -maxdepth 4 -type d \( -iname '*pixelhop*' -o -iname '*notehop*' -o -iname '*remotehop*' \) -not -path '/proc/*' 2>/dev/null
  sudo docker ps
  ```
- Kalau RemoteHop akan memakai login HEL.ink, daftarkan sebagai aplikasi ketiga (bagian 3.1). Gunakan **secret sendiri** (`openssl rand -hex 32`).

## 3. Yang perlu dikerjakan

### 3.1 HEL.ink (`/var/www/helink/shortlink-app`, repo navi-crwn/hel.ink)

1. **Route `GET /join/{app}`** (untuk tamu), di `AppSsoController`:
   - Sudah login: redirect ke `route(APPS[$app]['route'])`, yaitu `/pixelhop` atau `/notehop`, yang membuka aplikasi dalam keadaan login.
   - Belum login: `session(['url.intended' => url('/'.$app)])`, lalu redirect ke `route('register')`.
   - Ubah `AppSsoController::cameFrom()` supaya juga mengenali intended berupa path `/{app}`, tidak hanya `/sso/{app}`. Bandingkan dengan `parse_url(..., PHP_URL_PATH)`, jangan dengan `str_contains`.
   - **Jangan** set intended ke `/sso/{app}` tanpa `state`, karena akan error 400 "Invalid state". `/{app}` memulai alur login baru yang benar.
   - Tes: tamu yang membuka `/join/pixelhop` diarahkan ke register. Setelah daftar dan verifikasi, ia sampai di `/pixelhop`. User yang sudah login langsung diteruskan.
2. **Welcome page sekali:** tambahkan bagian "Satu akun untuk semua aplikasi HEL.ink" di `resources/views/auth/onboarding.blade.php`.
   - Halaman ini sudah hanya tampil setelah verifikasi email atau setelah daftar dengan Google.
   - Daftar aplikasinya diambil dari `AppSsoController::APPS`, hanya yang sudah dikonfigurasi (`baseUrl($app) !== null`).
   - Tambahkan field `tagline` per aplikasi. Teksnya minta dari pemilik, jangan dikarang.
   - Tombol tiap aplikasi mengarah ke `continueUrl($app)`.
3. **Hapus `/api/pixelhop/login` dan `/register`** beserta `PixelHopAccountController` dan `tests/Feature/PixelHopAccountApiTest.php`.
   - Lakukan **setelah** PixelHop versi tanpa form password sudah live.
   - Bagian lain dari PR #30 tetap dipakai: email daftar dikirim setelah respons, dan `LoginHistory::keepLatest`.
4. Kalau RemoteHop atau aplikasi baru lain bergabung:
   - Tambah entri di `AppSsoController::APPS`.
   - Tambah `config/services.php` dan `.env.example` (`{APP}_URL`, `{APP}_SSO_SECRET`).
   - Tambah route `sso.{app}`, `{app}` (open) dan `join/{app}`.
   - Tambah tes mengikuti `tests/Feature/NoteHopSsoTest.php`.

### 3.2 PixelHop (repo navi-crwn/pixelhop, Go)

Draft backend ada di [`pixelhop-draft.patch`](pixelhop-draft.patch), dibuat terhadap `main` @ `6f046a1`. Draft ini **belum lengkap**: template dan popup belum dibuat, jadi tes `TestHelinkIsTheOnlySignIn` di dalamnya masih gagal. Isinya:
- `helink_api.go` dan tesnya dihapus, karena sudah tidak ada form password HEL.ink.
- `POST /login` dan `POST /register` diarahkan ke `/login` saat SSO aktif (`helinkOnly`).
- `config.GoogleEnabled()` bernilai false saat SSO aktif, sehingga `/auth/google` mengembalikan 404 dan tombol Google hilang.
- Tiket dengan `aud` selain `pixelhop` ditolak (`helink.go`, ditambah kasus tes "other app").

Masih perlu dibuat:
1. Partial template `helink-card`:
   - Judul, plus kalimat "Satu akun HEL.ink untuk HEL.ink, PixelHop dan NoteHop". Kalimat final minta dari pemilik.
   - Tombol **Login with HEL.ink** → `/auth/helink?next=…`.
   - Tombol **Join with HEL.ink** → `{{.Helink}}/join/pixelhop`.
2. `login.html` dan `register.html`: saat `.SSO`, tampilkan hanya `helink-card`. Saat tidak SSO (self-host), form lama tetap ada.
3. `base.html`:
   - Untuk tamu saat `.SSO`, tambahkan `<dialog id="helink-signin">` berisi `helink-card`.
   - Tombol nav "Sign in" diberi `data-open-dialog="#helink-signin"`. Handler-nya sudah ada di `web/static/js/app.js` sekitar baris 531.
   - Karena "Sign in" berupa `<a href="/login">`, tambahkan `e.preventDefault()` di handler untuk elemen `<a>`. Tanpa JS, link tetap membuka halaman `/login` yang isinya sama.
4. Ganti teks "Create account" di `home.html` dan `docs.html` dengan "Join with HEL.ink", atau arahkan ke popup.
5. **Akun lama khusus PixelHop** (password lokal atau Google lokal):
   - Saat pemiliknya login dengan HEL.ink memakai email yang sama dan sudah terverifikasi, `linkHelinkUser` otomatis menautkan profil lama ke akun HEL.ink, sehingga galeri tetap ada.
   - Umumkan ke pengguna lama: "buat akun HEL.ink dengan email yang sama".
   - Cek jumlahnya dulu: `SELECT COUNT(*) FROM users WHERE helink_id IS NULL`. Jalankan pada salinan database, jangan pada production.
6. Turnstile di PixelHop tidak lagi dibutuhkan untuk login, karena HEL.ink sudah punya Turnstile sendiri.

### 3.3 NoteHop

- Pastikan NoteHop hanya punya tombol login HEL.ink, popup yang sama, dan "Join" ke `hel.ink/join/notehop`.
- Pastikan NoteHop memeriksa `aud === "notehop"`.

## 4. Urutan deploy (tiap langkah: backup, lalu tes, lalu izin pemilik)

1. **HEL.ink:** route `/join/{app}` dan welcome di onboarding. Aman dideploy lebih dulu karena tidak ada yang bergantung padanya.
2. **PixelHop dan NoteHop:** popup "Login / Join with HEL.ink", hapus form password dan Google lokal.
3. **HEL.ink:** hapus `/api/pixelhop/*`.

Cek setelah deploy:
- Tamu di PixelHop: "Sign in" memunculkan popup. "Join with HEL.ink" membuka form daftar HEL.ink. Setelah verifikasi email, pengguna kembali ke PixelHop dalam keadaan login.
- Daftar dengan Google di hel.ink, lalu klik "Login with HEL.ink" di PixelHop: langsung masuk tanpa daftar lagi.
- `/auth/google` di PixelHop mengembalikan 404. `POST /login` di PixelHop tidak membuat sesi.

## 5. Login/daftar lambat (hasil ukur VPS 2026-10-27)

**Hasil ukur:**

| Bagian | Waktu |
|---|---|
| `GET /login` | 0,3 dtk (normal) |
| `GET /register` | 0,34 / 1,6 / 3,6 dtk (kadang lambat) |
| Turnstile | 0,26 dtk |
| SMTP connect | 0,2 dtk |

Load server rendah. RAM masih tersedia 5,5 GB.

**Daftar lambat (TERBUKTI):** dua email SMTP dikirim sebelum halaman dibalas. Sudah diperbaiki di PR #30 dengan `defer()`.

**`GET /register` kadang lambat (DUGAAN):** session disimpan di MySQL bersama pichost. Ukur 20× dan cek slow query log MySQL sebelum mengubah apa pun.
```bash
for i in $(seq 20); do curl -s -o /dev/null -w '%{time_starttransfer}\n' https://hel.ink/register; done
```

## 6. Usulan tahap berikutnya: dashboard admin global (belum disetujui, tanyakan pemilik)

Bangun di dalam admin HEL.ink, **bukan proyek terpisah**, karena HEL.ink sudah memegang semua akun:
- **Daftar aplikasi di database** (tabel `apps`, menggantikan konstanta `APPS`): nama, URL, deskripsi, ikon, status, tanggal rilis. Secret tetap di `.env`.
- **Pengumuman:** admin menulis pengumuman, misalnya "Aplikasi baru: X". Tampil sekali per user sebagai banner di HEL.ink. Opsional juga di aplikasi lain lewat endpoint publik yang ditandatangani.
- **Pengguna lintas aplikasi:** suspend di HEL.ink otomatis memblokir login ke semua aplikasi. Saat ini sudah berlaku karena tiket ditolak untuk user yang di-suspend. Untuk sesi yang sedang aktif di aplikasi, dibutuhkan endpoint cek status.
- **Statistik ringkas per aplikasi** (jumlah user, upload, catatan) lewat endpoint admin per aplikasi yang ditandatangani.
