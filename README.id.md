<p align="center"><a href="README.md">English</a> · <b>Bahasa Indonesia</b></p>

<p align="center"><picture><source media="(prefers-color-scheme: light)" srcset="docs/readme/hero-id-light.webp"><img src="docs/readme/hero-id-dark.webp" alt="LinkHop: link pendek, halaman bio yang cantik" width="100%"></picture></p>

<p align="center">
  <b>Pendekkan link, hitung setiap klik, dan punya halaman link in bio.<br>Gratis, open source, dan bisa kamu host sendiri.</b>
</p>

<p align="center">
  <a href="https://hel.ink"><b>🔗 Coba hel.ink</b></a> ·
  <a href="https://navi-crwn.github.io/hel.ink/">Website</a> ·
  <a href="https://hel.ink/b/hel">Contoh bio</a> ·
  <a href="#cara-pasang">Cara pasang</a> ·
  <a href="#api">API</a> ·
  <a href="#pertanyaan-umum">FAQ</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CREDITS.md">Kredit</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="Lisensi MIT"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/hel.ink?style=flat-square&color=yellow" alt="Stars"/>
</p>

---

## Apa itu LinkHop?

**LinkHop** adalah pemendek link dari keluarga HOP ("Hop Easy Link"). Satu keluarga memakai satu akun, **HOP ID**, yang dipegang [hub](https://github.com/navi-crwn/HEL.ink) di `hel.ink` / `hub.hel.ink`; dashboard LinkHop ada di `link.hel.ink`, short link tetap di `hel.ink/l/slug`, dan pemindah app dari hub membawa kamu ke [BioHop](https://github.com/navi-crwn/BioHop) (link in bio) dan [PixelHop](https://github.com/navi-crwn/pixelhop) (gambar). LinkHop tidak punya halaman login, daftar, atau profil sendiri: hub yang memasukkan kamu lewat tiket SSO singkat, dan semua app berbagi satu database.

Link yang panjang susah dibagikan, dan kamu tidak pernah tahu siapa yang mengkliknya. LinkHop membuatnya **pendek** (bisa dengan slug pilihanmu), **menghitung setiap klik** per negara, perangkat, dan referrer, dan memberi kamu satu **halaman link in bio** di `hel.ink/b/namamu` untuk semua yang ingin kamu tunjukkan.

Semua fitur sudah termasuk untuk setiap akun. Tanpa paket premium, tanpa fitur yang dikunci. Dan karena open source, kamu juga bisa menjalankan semuanya di server dan domain sendiri.

**Cocok untuk kamu yang:**
- sering membagikan link di media sosial dan ingin tahu mana yang berhasil,
- butuh satu halaman rapi untuk semua link, toko, musik, dan video,
- ingin pemendek link yang kamu kendalikan sendiri, di server sendiri.

<p align="center"><picture><source media="(prefers-color-scheme: light)" srcset="docs/readme/features-id-light.webp"><img src="docs/readme/features-id-dark.webp" alt="Semua yang bisa dilakukan LinkHop" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: light)" srcset="docs/readme/bio-id-light.webp"><img src="docs/readme/bio-id-dark.webp" alt="Link in Bio: satu halaman, semua link" width="100%"></picture></p>

📖 **Tur lengkap, panduan self-host, dan FAQ:** [navi-crwn.github.io/hel.ink](https://navi-crwn.github.io/hel.ink/)

## Contoh

<img src="docs/readme/example-bio.webp" alt="Contoh halaman bio di hel.ink/b/hel" width="280" align="right">

**Halaman bio:** [hel.ink/b/hel](https://hel.ink/b/hel), halaman milik LinkHop sendiri dengan tema Galaxy, tombol link berikon brand, blok teks, FAQ, dan kartu kontak.

**Link pendek:**

| Link pendek | Menuju |
| --- | --- |
| [hel.ink/l/github](https://hel.ink/l/github) | Kode sumber |
| [hel.ink/l/website](https://hel.ink/l/website) | Website |
| [hel.ink/l/selfhost](https://hel.ink/l/selfhost) | Panduan self-host |
| [hel.ink/l/documentation](https://hel.ink/l/documentation) | Referensi API |
| [hel.ink/l/changelog](https://hel.ink/l/changelog) | Perubahan di setiap rilis |

Untuk memuat contoh yang sama di instalasi kamu sendiri, jalankan `php artisan db:seed --class=ExampleBioSeeder`.

<br clear="right">

## Fitur

- **🔗 Link pendek.** Slug acak atau custom, password dan tanggal kedaluwarsa opsional, dan QR code (PNG, SVG, atau JPG) untuk setiap link.
- **🙋 Tanpa akun pun bisa.** Siapa saja bisa memendekkan link. Link tamu dihapus setelah 14 hari tanpa klik, dan paling lama setelah 30 hari; daftar untuk menyimpannya dan melihat statistiknya.
- **🪪 Link in Bio.** Halamanmu sendiri di `hel.ink/b/namamu`, disusun dari blok: link, teks, gambar, YouTube, Spotify, peta, hitung mundur, FAQ, vCard, dan potongan kode.
- **🎨 20 tema.** Galaxy, Neon, Cyberpunk, Aurora, Cherry Blossom, Midnight, Matrix, Ice, Lavender, dan lainnya, atau warna, font, dan CSS kamu sendiri. Editornya menampilkan pratinjau langsung.
- **✨ Animasi.** Hujan, salju, bintang, hati, daun, atau confetti di latar halaman; tombol yang muncul, bergeser, memantul, bersinar, berdenyut, atau bergoyang.
- **🏷️ 150+ ikon brand.** Ikon media sosial dari Simple Icons, ditaruh di posisi yang kamu mau.
- **📊 Analitik.** Klik, pengunjung unik, negara dan kota, perangkat, browser, dan referrer, secara real time, dengan ekspor CSV. Halaman bio punya statistik kunjungan dan klik per blok sendiri.
- **🗂️ Folder & tag.** Kelompokkan, beri tag, dan cari link, kelola banyak link sekaligus, dan tinggalkan komentar di link.
- **🔒 Halaman bio privat.** Kunci halaman dengan password atau cek usia. Google Analytics, Facebook Pixel, dan TikTok Pixel baru dimuat setelah pengunjung setuju.
- **🔌 API & ShareX.** REST API dengan token Bearer, plus konfigurasi ShareX siap pakai di Settings → API.
- **🔐 Login aman.** Email dan password atau login Google, kode dua langkah (TOTP), dan riwayat login.
- **🛡️ Perlindungan abuse.** Batas kecepatan dan kuota, blacklist domain, cek proxy/VPN, formulir laporan, dan pemindaian tiap jam untuk phishing, penipuan, dan link berbahaya lainnya, dengan pengecekan Google Safe Browsing dan URLhaus (opsional).
- **🧰 Panel admin.** Pengguna, link, halaman bio, blokir IP, blacklist domain, laporan abuse, dan antrean job dalam satu tempat.
- **🏠 Bisa di-host sendiri.** Laravel 12 di server kamu sendiri dengan MySQL, PostgreSQL, atau SQLite.

## Cara pasang

**Yang kamu butuhkan:** PHP 8.3+, Composer, Node.js 18+, database (MySQL 8.0+, MariaDB, atau SQLite), dan [hub](https://github.com/navi-crwn/HEL.ink) yang berjalan di database yang sama: hub yang memegang akun (`users`, `api_tokens`, …) dan memasukkan orang ke sini. Redis opsional, untuk cache.

```bash
# 1. Ambil kodenya (paket bersama hop/auth, hop/ui, hop/safety ditarik Composer dari GitHub)
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install && npm install

# 2. Siapkan: DB_* dan HOP_SSO_SECRET sama dengan .env hub, HOP_HUB_URL menunjuk ke hub
cp .env.example .env
php artisan key:generate
php artisan migrate          # hanya tabel milik app ini; tabel akun sudah dibuat hub
php artisan storage:link

# 3. Build dan jalankan
npm run build
php artisan serve --port=8001
```

Lalu buka `http://localhost:8001`: tamu dikirim ke halaman masuk hub dan kembali dalam keadaan sudah masuk. Untuk kerja lokal isi `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001`, `HOP_HUB_URL=http://localhost:8000` (hub di `php artisan serve`), dan di hub `LINKHOP_URL=http://localhost:8001`.

**Kepemilikan database.** Setiap app HOP hanya menjalankan migration miliknya di database bersama. Milik LinkHop ada di `database/migrations` (link, klik, folder, tag, komentar, abuse, IP ban, blacklist domain, registry app); riwayat tabel akun disimpan di `database/migrations-archive` dan tidak dijalankan lagi (test membangun skemanya dari sana). Jangan arahkan `migrate:fresh` ke database yang dipakai hub.

<details>
<summary><b>Pengaturan di <code>.env</code></b></summary>

**Database**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=helink
DB_USERNAME=your_user
DB_PASSWORD=your_pass
```

**Email**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-app-password
```

**HOP ID (hub)**
```env
HOP_APP=linkhop
HOP_HUB_URL=https://hub.hel.ink
HOP_SSO_SECRET=secret-32-karakter-lebih-yang-sama-dengan-hub
```
Login Google, 2FA, dan reset password milik hub; tidak ada yang perlu diatur di sini.

**Domain sendiri:** `APP_URL` adalah dashboard dan API (`link.hel.ink`); `PUBLIC_URL` tempat short link, kode QR, sitemap, dan halaman bio diakses (`hel.ink`). Di instalasi satu host, isi keduanya dengan nilai yang sama.

**Di belakang Cloudflare:** LinkHop mengirim Content Security Policy ketat dalam mode report-only yang hanya memercayai script dengan nonce halaman, dan mencatat apa yang akan diblokir ke `storage/logs/csp-*.log`. Fitur Cloudflare **Email Address Obfuscation** (Scrape Shield) dan **Rocket Loader** (Speed → Optimization) menyuntikkan script sendiri dari `/cdn-cgi/scripts/.../cloudflare-static/` tanpa nonce itu, sehingga memenuhi log. Matikan keduanya di dashboard Cloudflare (disarankan: LinkHop tidak membutuhkannya, dan policy tetap siap diberlakukan), atau lewati hanya di path tertentu dengan Configuration Rule Cloudflare. Mengizinkannya di policy tidak bisa dengan policy nonce (`'strict-dynamic'` mengabaikan daftar host dan `'self'`); caranya harus membuang nonce, yang melemahkan policy untuk semua script lain.

**Production:** biarkan queue worker tetap berjalan.
```bash
php artisan queue:work
```

</details>

<details>
<summary><b>Cutover: memindahkan app ini dari hel.ink ke link.hel.ink</b></summary>

LinkHop tetap jalan di `hel.ink` dengan `APP_URL=https://hel.ink` sampai semua app HOP siap; pindahnya satu jendela untuk semua (rencananya: `docs/planning/PLAN-pemisahan-aplikasi.md` di repo hub). Yang dibutuhkan app ini pada hari itu, berurutan:

1. Hub sudah hidup di `hub.hel.ink` dengan database dan `HOP_SSO_SECRET` yang sama, dan mendaftarkan app ini (`LINKHOP_URL=https://link.hel.ink`).
2. Di `.env` app ini: `APP_URL=https://link.hel.ink`, `PUBLIC_URL=https://hel.ink`, `HOP_APP=linkhop`, `HOP_HUB_URL=https://hub.hel.ink`, `HOP_SSO_SECRET=…`, `BIOHOP_URL`, `PIXELHOP_URL`. Hapus `GOOGLE_*`; kunci Turnstile boleh tetap (formulir lapor abuse). `BIO_ENABLED=false` begitu BioHop melayani `/b/*`.
3. Kode dan dependensi: `git pull`, lalu `/usr/bin/php8.3 /usr/local/bin/composer install --no-dev -o` (`php` default server adalah 8.5 dan tidak punya `zip`), `npm ci && npm run build`.
4. `php artisan migrate --force` (hanya tabel LinkHop; migration `apps` tidak melakukan apa-apa kalau tabelnya sudah ada).
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`, lalu `systemctl restart queue-worker` supaya worker memakai kode dan `.env` baru.
6. nginx: `link.hel.ink` → app ini; di `hel.ink` hanya `/l/*`, `/{slug}` (301 ke `/l/`, sampai 27 November 2026), `/b/*` (BioHop), dan `/api/*` (308 ke `link.hel.ink/api`) yang sampai ke app, sisanya hub (konfigurasi lengkap ada di repo hub, `deploy/nginx/`).
7. Periksa: `https://link.hel.ink/up`, `https://link.hel.ink/` mengirim tamu ke hub dan kembali sudah masuk, `https://hel.ink/l/<slug>` mengalihkan, `curl -H "Authorization: Bearer …" https://link.hel.ink/api/links/<slug>` menjawab, pemindah app di sidebar membuka BioHop dan PixelHop.
8. Mundur: rilis sebelumnya dengan `APP_URL=https://hel.ink` dan konfigurasi nginx lama; database tidak perlu apa-apa (migration hub hanya menambah tabel dan kolom).

</details>

## Cara pakai

**Memendekkan link**
1. Tempel URL panjangmu.
2. Opsional: pilih slug custom, password, atau tanggal kedaluwarsa.
3. Tekan **Shorten** dan salin link-nya.

**Di mana link-mu berada (URL)**

| Apa | URL |
| --- | --- |
| Short link | `hel.ink/l/slug` |
| Halaman bio | `hel.ink/b/nama` |
| Bentuk lama short link | `hel.ink/slug`: dialihkan ke `hel.ink/l/slug` sampai **27 November 2026**, lalu berhenti |
| Dashboard dan API | `link.hel.ink` (masuk lewat hub, `hub.hel.ink`) |
| Akunmu | `hub.hel.ink`: profil, password, 2FA, token API, unduh data, hapus akun |

hel.ink menjadi rumah beberapa app HOP, jadi sejak 28 Oktober 2026 short link punya jalurnya sendiri. Tidak ada yang dihapus; dashboard, kode QR, API, dan ekspor akun sudah menampilkan bentuk baru. Di instalasi sendiri, host publiknya adalah `PUBLIC_URL` (default `APP_URL`), tanggalnya `LEGACY_SLUG_UNTIL`, dan `php artisan links:announce-migration` mengirim email ke setiap pemilik link berisi link mereka dalam bentuk baru (`--reminder` untuk email kedua).

**Membuat halaman bio**
1. Buka **Dashboard → Link in Bio**.
2. Buat halaman dengan namamu.
3. Tambahkan blok: link, teks, gambar, video, musik.
4. Pilih tema dan warnamu.
5. Bagikan: `hel.ink/b/namamu`.

## API

Token API dibuat di hub (**HOP ID → Settings → API**) dan berlaku di semua app HOP; app ini membacanya dari tabel bersama `api_tokens` lewat `hop/auth`. Token membawa **scope**: `link:create`, `link:read`, `link:delete` (token yang dibuat sebelum ada scope tidak punya scope dan boleh melakukan semuanya). API menjawab di `link.hel.ink/api` (dan di `hel.ink/api`, yang meneruskan ke sana sampai 27 November 2026). Batasnya per token (default 100 permintaan per jam).

**Membuat short link** (`link:create`). Ini panggilan yang dipakai BioHop saat blok bio minta short link.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Field | | |
| --- | --- | --- |
| `url` | wajib | Tujuan (`https://` ditambahkan kalau skemanya tidak ada). Ditolak kalau pemeriksaan keamanan menandainya. |
| `alias` | opsional | Slug, 3–255 karakter `A-Z a-z 0-9 _ -`; kata terpesan dan slug yang sudah dipakai ditolak. Acak kalau kosong. |
| `folder` | opsional | Nama folder di akun; dibuat kalau belum ada. |
| `expires_at` | opsional | Tanggal-waktu ISO di masa depan. |
| `password` | opsional | 4–50 karakter; pengunjung harus mengetiknya. |

```json
HTTP 201
{"success": true, "message": "Link created successfully",
 "data": {"url": "https://hel.ink/l/my-link", "slug": "my-link", "target_url": "https://example.com",
          "status": "active", "created_at": "2026-10-28T10:00:00+00:00", "expires_at": null}}
```

Error selalu berbentuk `{"success": false, "error": "…", "message": "…"}`: `401` token tidak dikenal atau kedaluwarsa, `403` scope kurang, akun ditangguhkan atau belum terverifikasi, `422` validasi (`messages` berisi field-nya), `429` kuota atau rate limit.

**Membaca link** (`link:read`): `GET /api/links/{slug}` → `data` yang sama plus `clicks`. **Menghapus link** (`link:delete`): `DELETE /api/links/{slug}` → `{"success": true}`. Keduanya menjawab `404` untuk slug yang bukan milik akun token.

Konfigurasi ShareX siap diunduh di halaman **API docs** dashboard.

## Pertanyaan umum

**Benar-benar gratis?**
Ya. Tidak ada paket berbayar dan tidak ada fitur yang dikunci, dan kodenya berlisensi MIT, jadi kamu boleh menjalankan, mengubah, dan membagikannya.

**Perlu akun?**
Ya, HOP ID (gratis, dibuat di hub); akun itu juga untuk masuk ke app HOP lainnya. Link tamu yang dibuat di beranda lama tetap jalan dan dihapus setelah 14 hari tanpa klik, paling lama setelah 30 hari.

**Di mana halaman bio saya?**
Di `hel.ink/b/` lalu nama yang kamu pilih, misalnya [hel.ink/b/hel](https://hel.ink/b/hel).

**Bisa pakai domain sendiri?**
Bisa, dengan meng-host salinan sendiri di samping hub: `APP_URL` host dashboard, `PUBLIC_URL` host publik untuk short link, dan `HOP_HUB_URL` hub-nya.

**Bisa jalan tanpa Link in Bio?**
Bisa: `BIO_ENABLED=false` di `.env`. Bio hidup di paket `hop/bio` (`packages/bio`); saat mati, tidak ada route yang didaftarkan dan tidak butuh tabel.

**Apa yang tercatat dari sebuah klik?**
Waktu, negara dan kota, perangkat, browser, dan referrer. Pixel pelacak di halaman bio baru dimuat setelah pengunjung setuju.

**Bagaimana melaporkan link berbahaya?**
Pakai formulir laporan (`/report-abuse`). Laporan masuk ke antrean abuse di pusat kendali HOP, bersama temuan dari pemindaian otomatis.

## Di balik layar

| Lapisan | Teknologi |
|---|---|
| **Backend** | Laravel 12, PHP 8.2+, MySQL / PostgreSQL / SQLite |
| **Frontend** | Tailwind CSS 4, Alpine.js 3, Vite 7 |
| **Grafik & peta** | Chart.js 4, Leaflet 1.9 |
| **Ikon** | Heroicons, Simple Icons (150+ brand) |
| **Akun** | HOP ID di hub; `hop/auth` (klien SSO, middleware `hop.auth` dan `hop.token`) |
| **Paket bersama** | `hop/auth`, `hop/ui` (pemindah app), `hop/safety` (keamanan URL dan konten), `hop/bio` |
| **Layanan** | Cloudflare Turnstile (formulir lapor abuse), IP-API, FlagCDN |

| Folder | Isinya |
|---|---|
| `app/`, `routes/`, `resources/` | Aplikasi Laravel: controller, model, view Blade, dan editor bio. |
| `config/bio-themes.php` | Tema-tema halaman bio. |
| `config/hop.php`, `config/limits.php` | Keluarga HOP (daftar `apps` sama dengan hub) dan kuota link. Pola dan ambang keamanan datang dari `hop/safety` (`config('safety')`). |
| `database/migrations-archive/` | Riwayat tabel akun (sekarang milik hub); tidak dijalankan. |
| `tests/` | Tes PHPUnit (`php artisan test`) dan synthetic users Playwright di `tests/e2e/`. |
| `docs/` | Website (GitHub Pages). `docs/readme/` berisi gambar README, diambil dari website dengan `node scripts/readme-images.mjs`. |

Daftar dependensi lengkap: [CREDITS.md](CREDITS.md).

## Berkontribusi

1. Fork repo ini.
2. Buat branch (`git checkout -b feature/cool-thing`).
3. Commit perubahanmu dan jalankan `php artisan test`.
4. Push dan buka pull request.

Ikuti PSR-12 dan tambahkan tes untuk fitur baru.

---

<p align="center">Lisensi MIT · Dibuat oleh <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://hel.ink">hel.ink</a> · <a href="https://navi-crwn.github.io/hel.ink/">Website</a></p>
