# Laporan cloud v2: suite manusia sintetis (PR-E) dan deploy

Tanggal: 14 Oktober 2026. Repo: `navi-crwn/hel.ink`.

## 1. Status pekerjaan

Diperbarui 14 Oktober 2026 dari `main` @ `0db2ae5`. **Semua PR sudah di-merge**; tidak ada PR yang menunggu.

| PR | Isi |
| --- | --- |
| #6 (PR-B) | Keamanan klik bio: ID publik acak, tujuan tidak bocor di halaman ber-password atau 18+, batas klik per IP, open redirect `/\evil.com`, halaman 429 yang jelas |
| #7 (PR-C) | Hasil audit bio: XSS lewat preview editor, hapus file milik user lain, duplikasi berulang, edit yang tidak tersimpan, font berlebih, takedown yang bisa diaktifkan lagi |
| #8 (PR-A) | Turnstile langsung siap saat halaman dibuka (login 1 klik), `/health/queue` |
| #9, #12 (PR-E) | Suite manusia sintetis, CI per PR dan nightly, uji beban k6, lima perbaikan bug dari suite |
| #10 (PR-D) | Homepage arah B (terang + gradien, mode gelap lembut) |
| #11 | Privasi IP: IP klik disimpan sebagai hash; **migrasinya tidak bisa dibatalkan** |
| #13 | Perbaikan pola slug (temuan 5) |
| #14 | Sisa audit bio: aksesibilitas (dialog, keyboard reorder, label, kontras), validasi nilai CSS, batas kolom, editor/preview/publik konsisten, view siap CSP ketat, pixel pelacak bio menunggu persetujuan pengunjung, `privacy:prune` |
| #15 | README baru (EN/ID) dan situs GitHub Pages (`docs/`) |
| #16 | Guest link: hapus pukul 00:00 setelah 14 hari tanpa klik atau hari ke-30; slug bekas diblokir 90 hari (halaman "Link Expired", 410); form shorten lebih rapi |

Di `main` terbaru: **133 test PHP lulus**.

## 2. Apa itu suite manusia sintetis

Aplikasi digambarkan sebagai peta: 25 keadaan (halaman atau kondisi, misalnya "modal buat link terbuka") dan 45 aksi (klik, isi form, drag blok, logout, dan sebagainya). Dari peta itu dibuat perjalanan pengguna secara otomatis:

- **Setiap PR**: setiap aksi dijalankan minimal sekali, ditambah 4 jalan acak. Waktu lokal sekitar 1 menit.
- **Nightly (02:00 WIB)**: juga setiap pasangan aksi yang mungkin (758 pasangan), 40 jalan acak di desktop dan 40 di mobile, lalu uji beban k6.

Setiap perjalanan memakai akun baru ("persona") dengan data sendiri dan IP sendiri. Setelah setiap langkah diperiksa:

- error JavaScript dan `console.error`;
- pelanggaran CSP yang diterapkan;
- respons 5xx, 419 (CSRF kedaluwarsa), 429 (rate limit), dan halaman 4xx yang tidak diharapkan;
- lapisan layar penuh yang menutupi halaman di luar modal;
- data persona lain yang terlihat oleh user biasa (kebocoran antar akun);
- halaman bio ber-password yang HTML-nya sudah memuat link yang dilindungi;
- urutan blok bio tetap sama setelah reload, link milik user lain tidak bisa dibuka;
- konsistensi database setelah perjalanan selesai (blok tanpa halaman, klik tanpa link, posisi blok ganda, halaman publik milik user yang di-suspend, job gagal).

Aksesibilitas (axe) dan pelanggaran CSP mode report-only dicatat di laporan, tetapi tidak menggagalkan CI.

Kalau ada perjalanan yang gagal, langkahnya otomatis dipangkas sampai urutan terpendek yang masih gagal. Contohnya, perjalanan 30 langkah dipangkas jadi 2 langkah dalam 5 kali ulang. File replay disimpan, dan perintah untuk mengulangnya ikut dicetak. Cara menjalankan dan menambah halaman ada di `tests/e2e/README.md`.

## 3. Temuan dari suite

Diperbaiki di PR #12:

1. **Login bisa terkunci karena fitur lain.** Semua route dengan `throttle:N,M` tanpa nama ternyata berbagi satu penghitung per IP. Beberapa kali cek link di `/check` ditambah laporan CSP otomatis dari browser sudah cukup membuat pengunjung mendapat 429 saat login. Sekarang setiap route punya penghitung sendiri. Test: `tests/Feature/RateLimitBucketsTest.php`.
2. **Banner cookie menutupi tombol "Create Link".** Banner (z-50) berada di atas modal buat link (z-40), jadi pengguna baru tidak bisa menekan tombol itu sebelum menutup banner. Sekarang banner selalu berada di bawah dialog.
3. **Install baru di MySQL/MariaDB gagal di tengah migrasi.** Dua migrasi lama memakai `->after('background_gradient')` sebelum kolom itu dibuat oleh migrasi berikutnya. SQLite mengabaikan `after()`, MySQL tidak. Migrasi baru `2025_12_05_200939_apply_early_bio_design_migrations` menjalankan keduanya lebih awal; ke-60 migrasi sekarang jalan dan rollback bersih di MariaDB. Server yang sudah berjalan tidak terpengaruh.
4. **Ambil judul otomatis (fetch-og) error 500** kalau situs tujuan membalas 404, timeout atau TLS gagal. Sekarang membalas 422 dan form tetap bisa dipakai.
5. **Validasi slug di browser tidak pernah jalan.** Pola `[a-zA-Z0-9_-]+` (modal buat link) dan `[A-Za-z0-9-]+` (homepage) tidak sah untuk Chrome versi baru, yang membaca atribut `pattern` dengan flag `v`. Browser mencatat error di console dan mengabaikan pola itu, jadi slug dengan spasi atau simbol baru ditolak oleh server. Tanda `-` sekarang di-escape.

Belum diperbaiki (dicatat untuk keputusan berikutnya):

6. **Preview QR error 500 tanpa ekstensi PHP `imagick`.** `/api/qr-generate` memakai backend PNG yang butuh imagick. Pastikan `php-imagick` terpasang di server (lihat bagian deploy).
7. **Atribut `onclick` dan sejenisnya masih banyak.** Ada sekitar 10 pelanggaran `script-src-attr` per putaran PR di CSP report-only. Ini harus dibersihkan sebelum CSP ketat bisa diterapkan.
8. **Aksesibilitas (axe, serious/critical):** input dan select tanpa label di halaman Links, Settings dan admin; tombol ikon tanpa nama di modal link dan halaman bio publik; kontras warna di homepage, `/check` dan modal; struktur `<dl>` yang salah di dashboard.
9. **GeoIP untuk IP baru bisa lambat.** Setiap IP yang belum ada di cache dicoba ke lima penyedia eksternal secara berurutan. Di uji beban, request pertama dari IP baru kadang butuh 2 sampai 4 detik. File `.mmdb` lokal (`geolite:update`) sebaiknya selalu tersedia di server.
10. **Satu permintaan rusak di halaman bio:** `GET /b/url('https://fonts.googleapis.com/css2…` mendapat 404. Ini berasal dari pemuatan font lama; setelah #7 di-merge, suite tidak lagi melihatnya.

**Status per `main` @ `0db2ae5`:**
- Temuan 7 sudah ditangani di #14: tidak ada lagi atribut `on*="..."` di `resources/views`, jadi CSP ketat (tanpa `script-src-attr`) bisa mulai dicoba. Sebelum mengubah CSP dari report-only menjadi enforce, baca dulu `storage/logs/csp-*.log` production selama beberapa hari.
- Temuan 8 ditangani sebagian besar di #14 (dialog, keyboard reorder, label, kontras). Sisanya akan terlihat di laporan axe dari run nightly berikutnya.
- Temuan 6 dan 9 tetap urusan server: pasang `imagick` dan jalankan `geolite:update`.

Mode nightly yang dijalankan lokal menemukan tiga celah di model uji, bukan di aplikasi: editor bio yang meminta konfirmasi "Discard Changes?" saat keluar tanpa simpan (perilaku yang benar), halaman bio yang tetap terbuka setelah password benar, dan batas 5 login per menit. Model sudah disesuaikan.

Yang sudah diperbaiki PR #6: pesan 429 yang selalu menyebut "creating shortlinks", termasuk saat login.

## 4. Uji beban (k6)

Skrip: `tests/load/k6.js`. Skenarionya meliputi redirect shortlink (naik sampai 40 request/detik), halaman bio beserta klik bloknya (sampai 20 request/detik), cek link, dan dashboard user yang sudah login.

Hasil lokal terhadap server uji (`php artisan serve` dengan 16 worker, SQLite; ini bukan server produksi, jadi angkanya dipakai untuk membandingkan antar-commit, bukan kapasitas sebenarnya):

| Skenario | p95 sebelum #6/#7 | p95 di `main` terbaru | Ambang p95 |
| --- | --- | --- | --- |
| Redirect shortlink | 149 ms | 44 ms | 800 ms |
| Halaman bio | 729 ms | 44 ms | 1500 ms |
| Dashboard | 1.6 s | 2.1 s | 2.5 s |

Total 5.360 request, 0 gagal, semua pemeriksaan lulus. Halaman bio sekarang sama cepatnya dengan redirect, karena #7 hanya memuat font yang dipilih. Dashboard masih paling lambat; lonjakannya berasal dari GeoIP untuk IP baru (temuan 9).

Uji beban tidak boleh diarahkan ke produksi. Untuk staging, isi `BASE_URL`, `BIO_SLUG` dan `LINK_SLUG`.

## 5. Instruksi deploy (untuk sesi lokal di server)

Production saat ini masih di `278715a`. Deploy berikut membawa semua PR di atas sekaligus. Fakta server (dari sesi lokal): app di `/var/www/helink/shortlink-app`, PHP situs `php8.3`, semua proses jalan sebagai `www-data`, deploy lewat `~/hel-ink-handoff/03-deploy.sh`. Semua perintah artisan di bawah memakai bentuk:

```bash
cd /var/www/helink/shortlink-app
A="sudo -u www-data env HOME=/tmp php8.3 artisan"
```

Aturan tetap berlaku: setiap perintah yang mengubah data, file atau konfigurasi **minta izin user dulu**.

### Yang berubah dibanding production

**Migrasi baru (6):**

| Migrasi | Isi | Bisa dibatalkan? |
| --- | --- | --- |
| `2025_12_05_200939_apply_early_bio_design_migrations` | Perbaikan urutan migrasi untuk install baru di MySQL. Di server lama tidak mengubah apa-apa (pakai guard `hasColumn`) | Ya |
| `2026_10_15_000001_add_public_id_to_bio_links_table` | Kolom `public_id` + isi untuk semua blok yang ada | Ya, tapi link klik yang sudah dibagikan berhenti bekerja |
| `2026_10_15_000002_hash_click_ip_addresses` | IP di `link_clicks` dan `bio_clicks` di-hash | **Tidak.** IP asli hanya kembali lewat restore backup |
| `2026_10_16_000001_tidy_bio_indexes` | Merapikan index bio (hapus index ganda, tambah index klik) | Ya |
| `2026_10_16_000002_widen_bio_text_color_columns` | Kolom warna teks bio diperlebar | Ya |
| `2026_10_17_000001_create_retired_slugs_table` | Tabel slug bekas guest link | Ya |

Tabel pichost di `helink_db` (`moderation_queue`, `api_rate_status`, `api_usage_logs`, `quarantine_records`) **tidak disentuh** oleh migrasi mana pun.

**`.env` baru (opsional):** `GUEST_LINK_TIMEZONE=Asia/Jakarta` (jam hapus guest link; kosong = `APP_TIMEZONE`), `RETIRED_SLUG_DAYS=90`.

**Jadwal baru:** `privacy:prune` tiap hari 03:15; `links:prune-guest` pindah ke 00:00 di zona `GUEST_LINK_TIMEZONE`.

**Penghapusan data yang akan terjadi otomatis setelah deploy** (laporkan angkanya ke user sebelum deploy):

| Data | Aturan | Cara menghitung dulu |
| --- | --- | --- |
| Guest link | 14 hari berturut tanpa klik, atau sudah lewat hari ke-30 | `$A links:prune-guest --dry-run` |
| `bio_clicks` | lebih dari 30 hari | `$A tinker --execute 'echo App\Models\BioClick::where("clicked_at","<",now()->subDays(30))->count();'` |
| `login_histories` | lebih dari 90 hari | `$A tinker --execute 'echo App\Models\LoginHistory::where("logged_in_at","<",now()->subDays(90))->count();'` |
| `activity_logs` | lebih dari 90 hari | `$A tinker --execute 'echo App\Models\ActivityLog::where("created_at","<",now()->subDays(90))->count();'` |
| Laporan abuse yang sudah ditutup | lebih dari 180 hari; IP pelapor dikosongkan setelah 30 hari | — |

Statistik total (jumlah klik per link) tidak berubah; yang dihapus hanya baris log lama.

### Sebelum deploy

1. **Backup wajib** (karena hash IP tidak bisa dibatalkan): `bash ~/hel-ink-handoff/01-backup.sh /var/www/helink/shortlink-app`.
2. Cek `.env` production (tanpa menampilkan nilainya):
   - `APP_ENV=production`, `APP_DEBUG=false`, `TRUSTED_PROXIES=cloudflare`, `SESSION_SECURE_COOKIE=true`, `FILESYSTEM_DISK=public`.
   - `TURNSTILE_SITE_KEY` dan `TURNSTILE_SECRET_KEY` **harus terisi**. Sejak #8, tanpa secret semua form login, daftar, lupa password dan lapor abuse ditolak di production.
   - Di dashboard Cloudflare, widget Turnstile sebaiknya mode **Managed**.
   - `E2E_HOOKS` tidak boleh ada.
3. Ekstensi `php8.3`: `gd`, `intl`, `pdo_mysql`, `imagick` (`php8.3 -m | grep -E 'gd|intl|pdo_mysql|imagick'`). Tanpa `imagick`, QR PNG (link, bio, preview) error 500.
4. Hitung data yang akan terhapus (tabel di atas) dan laporkan ke user.

### Langkah deploy

```bash
bash ~/hel-ink-handoff/03-deploy.sh /var/www/helink/shortlink-app main
```

Script ini menjalankan `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `migrate --force`, cache ulang, `chown` storage dan `queue:restart` dengan `php8.3`. Kalau dijalankan manual:

```bash
$A down
git pull origin main
sudo -u www-data env HOME=/tmp php8.3 "$(command -v composer)" install --no-dev --optimize-autoloader --no-scripts && $A package:discover
npm ci && npm run build
$A migrate --force
$A config:cache && $A route:cache && $A view:cache
$A queue:restart
$A up
```

Opsional sesudahnya: tambah `GUEST_LINK_TIMEZONE=Asia/Jakarta` di `.env` lalu `$A config:cache`.

### Cek setelah deploy

- `curl -s -o /dev/null -w '%{http_code}' https://hel.ink/up` → 200.
- `curl -s https://hel.ink/health/queue` → JSON `{"status":...}`, bukan halaman shortlink.
- `$A migrate:status | tail -8` → enam migrasi baru berstatus Ran.
- `$A schedule:list | grep -E 'privacy|prune-guest|abuse'` → jadwal baru terdaftar.
- Login sekali di browser: cukup satu klik (Turnstile tidak minta klik tambahan pada kebanyakan pengunjung).
- Buka satu halaman bio, klik satu blok: alamatnya `/b/click/<12 karakter acak>`, bukan angka.
- Halaman bio ber-password: sebelum password dimasukkan, tujuan link tidak ada di source halaman.
- Halaman Analytics pemilik link tidak lagi menampilkan IP pengunjung.
- Pantau `storage/logs/laravel-*.log` dan `storage/logs/csp-*.log` selama satu jam pertama. Pastikan tidak ada file milik root: `find storage bootstrap/cache -user root | head`.

### Kalau harus rollback

```bash
$A down
git checkout 278715a
sudo -u www-data env HOME=/tmp php8.3 "$(command -v composer)" install --no-dev --optimize-autoloader --no-scripts && $A package:discover
npm ci && npm run build
$A migrate:rollback   # membatalkan semua migrasi dari deploy ini (satu batch)
$A config:cache && $A route:cache && $A view:cache && $A queue:restart && $A up
```

- Rollback `public_id` menghapus kolom itu, jadi link klik bio yang sudah dibagikan dengan ID baru berhenti bekerja.
- Hash IP **tidak** kembali lewat rollback. IP asli hanya bisa dipulihkan dari backup database yang dibuat sebelum deploy.
- Data yang sudah dihapus `privacy:prune` / `links:prune-guest` juga hanya kembali lewat backup.
