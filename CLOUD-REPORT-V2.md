# Laporan cloud v2: suite manusia sintetis (PR-E) dan deploy

Tanggal: 14 Oktober 2026. Repo: `navi-crwn/hel.ink`.

## 1. Status pekerjaan

| PR | Isi | Status |
| --- | --- | --- |
| #6 (PR-B) | Keamanan klik bio: ID publik acak, tujuan tidak bocor di halaman ber-password atau 18+, batas klik per IP, open redirect `/\evil.com`, halaman 429 yang jelas | Draft, CI hijau |
| #7 (PR-C) | Hasil audit bio (XSS lewat preview editor, hapus file milik user lain, duplikasi berulang, edit yang tidak tersimpan, font berlebih, takedown yang bisa diaktifkan lagi) | Draft, CI hijau |
| #8 (PR-A) | Turnstile langsung siap saat halaman dibuka, `/health/queue` | Draft, CI hijau |
| #12 (PR-E) | Suite manusia sintetis, CI per PR dan nightly, uji beban k6, empat perbaikan bug dari suite | Draft, menggantikan #9 |

Privasi IP (sembunyikan IP dari pemilik link, simpan sebagai hash) dikerjakan di thread "Tindak lanjut audit bio". Homepage arah B dikerjakan di thread terpisah.

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

Belum diperbaiki (dicatat untuk keputusan berikutnya):

5. **Preview QR error 500 tanpa ekstensi PHP `imagick`.** `/api/qr-generate` memakai backend PNG yang butuh imagick. Pastikan `php-imagick` terpasang di server (lihat bagian deploy).
6. **Atribut `onclick` dan sejenisnya masih banyak.** Ada sekitar 10 pelanggaran `script-src-attr` per putaran PR di CSP report-only. Ini harus dibersihkan sebelum CSP ketat bisa diterapkan.
7. **Aksesibilitas (axe, serious/critical):** input dan select tanpa label di halaman Links, Settings dan admin; tombol ikon tanpa nama di modal link dan halaman bio publik; kontras warna di homepage, `/check` dan modal; struktur `<dl>` yang salah di dashboard.
8. **GeoIP untuk IP baru bisa lambat.** Setiap IP yang belum ada di cache dicoba ke lima penyedia eksternal secara berurutan. Di uji beban, request pertama dari IP baru kadang butuh 2 sampai 4 detik. File `.mmdb` lokal (`geolite:update`) sebaiknya selalu tersedia di server.
9. **Satu permintaan rusak di halaman bio:** `GET /b/url('https://fonts.googleapis.com/css2…` mendapat 404. Kemungkinan ini berasal dari pemuatan font, yang sedang diubah di PR #7. Perlu dicek ulang setelah #7 di-merge.

Mode nightly yang dijalankan lokal menemukan tiga celah di model uji, bukan di aplikasi: editor bio yang meminta konfirmasi "Discard Changes?" saat keluar tanpa simpan (perilaku yang benar), halaman bio yang tetap terbuka setelah password benar, dan batas 5 login per menit. Model sudah disesuaikan.

Yang sudah diperbaiki PR #6: pesan 429 yang selalu menyebut "creating shortlinks", termasuk saat login.

## 4. Uji beban (k6)

Skrip: `tests/load/k6.js`. Skenarionya meliputi redirect shortlink (naik sampai 40 request/detik), halaman bio beserta klik bloknya (sampai 20 request/detik), cek link, dan dashboard user yang sudah login.

Hasil lokal terhadap server uji (`php artisan serve` dengan 16 worker, SQLite; ini bukan server produksi, jadi angkanya dipakai untuk membandingkan antar-commit, bukan kapasitas sebenarnya):

| Skenario | Median | p95 | Ambang p95 |
| --- | --- | --- | --- |
| Redirect shortlink | 27 ms | 149 ms | 800 ms |
| Halaman bio | 390 ms | 729 ms | 1500 ms |
| Dashboard | 19 ms | 1.6 s | 2.5 s |

Total 5.324 request, 0 gagal, semua pemeriksaan lulus. Halaman bio sekitar 15 kali lebih lambat dari redirect, dan perbaikan font di PR #7 seharusnya membantu.

Uji beban tidak boleh diarahkan ke produksi. Untuk staging, isi `BASE_URL`, `BIO_SLUG` dan `LINK_SLUG`.

## 5. Instruksi deploy

### Urutan merge

1. #6 (keamanan klik bio, ada migrasi `public_id`)
2. #7 (audit bio). Kalau muncul konflik di `BioPageController` setelah #6, selesaikan dulu.
3. #8 (Turnstile)
4. #12 (suite dan empat perbaikan). Konflik kecil di `routes/web.php` dengan #8 mungkin terjadi; isi kedua sisi dipertahankan.

Tutup #9 setelah #12 di-merge, karena #12 sudah berisi commit #9.

### Sebelum deploy

- Backup database dan `storage/app/public` (`php artisan backup:run`, butuh `mysqldump`).
- Periksa `.env` produksi:
  - `APP_ENV=production`, `APP_DEBUG=false`
  - `TURNSTILE_SITE_KEY` dan `TURNSTILE_SECRET_KEY` **wajib diisi**. Sejak #8, tanpa secret semua form yang dilindungi (login, daftar, lupa password, lapor abuse) akan ditolak di produksi.
  - `TRUSTED_PROXIES=cloudflare` kalau situs berada di belakang Cloudflare, supaya IP pengunjung, batas klik dan rate limit benar.
  - `SESSION_SECURE_COOKIE=true`
  - `E2E_HOOKS` **tidak boleh ada**. Route uji juga tidak aktif di produksi, tetapi variabel ini sebaiknya tidak pernah diset.
- Ekstensi PHP: `gd`, `intl`, `pdo_mysql`, `imagick` (untuk preview QR).

### Langkah deploy

```bash
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Pastikan cron scheduler aktif: `* * * * * cd /path/ke/hel.ink && php artisan schedule:run >> /dev/null 2>&1`.

### Cek setelah deploy

- `curl -s https://hel.ink/up` mengembalikan 200.
- `curl -s https://hel.ink/health/queue` mengembalikan JSON, bukan halaman shortlink.
- Login sekali. Turnstile tidak boleh meminta klik tambahan pada kebanyakan pengunjung.
- Buka satu halaman bio dan klik satu blok. Alamat klik harus berbentuk `/b/click/<12 karakter acak>`, bukan angka.
- Buka satu halaman bio ber-password: sebelum password dimasukkan, link tujuannya tidak boleh terlihat di source halaman.
- Pantau `storage/logs/laravel-*.log` dan `storage/logs/csp-*.log` selama satu jam pertama.

### Kalau harus rollback

```bash
php artisan down
git checkout <commit-sebelumnya>
composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate:rollback   # membatalkan semua migrasi dari deploy ini (satu batch), termasuk public_id dari #6
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart && php artisan up
```

Migrasi `public_id` aman untuk dijalankan ulang. Rollback-nya menghapus kolom, sehingga link klik bio yang sudah dibagikan dengan ID baru akan berhenti bekerja.
