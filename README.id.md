<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <b>Bahasa Indonesia</b> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="Beranda LinkHop: kolom untuk menempel tautan panjang, tombol Shorten, dan adegan pulau 3D" width="100%"></picture></p>

<p align="center">
  <b>Tautan pendek, kode QR, dan statistik klik.<br>Gratis, open source, dan bisa Anda host sendiri.</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>Coba LinkHop</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">Situs web</a> ·
  <a href="#instalasi">Instalasi</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CREDITS.md">Kredit</a> ·
  <a href="SECURITY.md">Keamanan</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

LinkHop memendekkan tautan, menghitung kliknya, dan membuat kode QR untuk setiap tautan. Ini adalah pemendek tautan di keluarga HOP, dan berjalan di [link.hel.ink](https://link.hel.ink). Tautan pendek dibuka di `hel.ink/l/slug`.

Siapa pun bisa memendekkan tautan tanpa akun. HOP ID gratis menyimpan tautan Anda dan menampilkan statistiknya. HOP ID adalah satu akun yang dipakai semua aplikasi HOP, dan tersimpan di [hub](https://github.com/navi-crwn/HEL.ink) pada `hel.ink` dan `hub.hel.ink`. LinkHop tidak punya halaman masuk, daftar, atau profil sendiri: hub yang memasukkan Anda dan mengembalikan Anda dengan tiket berumur pendek, dan aplikasi-aplikasinya memakai satu basis data bersama.

## Keluarga HOP

| Aplikasi | Alamat | Repositori | Fungsinya |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · akun di `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Satu akun untuk semua aplikasi HOP. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Tautan pendek, kode QR, dan statistik klik. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Satu halaman link-in-bio untuk semua yang Anda kerjakan. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Hosting gambar dengan album dan tautan langsung. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Catatan terenkripsi yang Anda bagikan lewat tautan. |
| **Imperium** | privat (hanya pemilik) | privat | Panel kontrol HOP. |

Semua fitur tersedia untuk setiap akun, tanpa tingkat berbayar. Kodenya berlisensi MIT, jadi Anda juga bisa menjalankannya di server dan domain Anda sendiri.

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="Dasbor LinkHop: klik minggu ini dengan satu batang per hari, total, daftar periksa untuk memulai, tautan terbaru, dan tautan terbaik" width="100%"></picture></p>

Tangkapan layar halaman setelah masuk memakai data demo karangan, bukan akun sungguhan.

## Fitur

- Tautan pendek dengan slug acak atau slug sendiri (3 sampai 255 karakter), kata sandi opsional, tanggal kedaluwarsa, dan kode QR dalam PNG, SVG, atau JPG.
- Batas tautan: waktu mulai, jumlah klik maksimum, alamat cadangan untuk saat tautan sudah kedaluwarsa atau klik habis, dan jenis pengalihan 301, 302, 307, atau 308.
- Perutean: hingga 20 aturan yang mengirim pengunjung ke tempat lain menurut negara, bahasa, perangkat, sistem operasi, atau peramban, serta pembagian A/B dari 2 sampai 5 alamat dengan bobot. Pembuat tag kampanye (UTM) mengisi parameter `utm_*`.
- Buka di aplikasi: di iOS dan Android, tautan bisa mencoba aplikasi yang terpasang lebih dulu, dengan App Store atau Google Play sebagai cadangan.
- Analitik: klik, pengunjung unik, negara dan kota, perangkat, peramban, peramban dalam aplikasi, perujuk, serta pindai QR dibandingkan klik langsung. Periode yang dipilih dibandingkan dengan periode sebelumnya. Log per klik disimpan 30 hari.
- Pengorganisasian: folder, tag, pencarian, tindakan massal, komentar pada tautan, impor CSV hingga 500 baris, dan palet perintah Ctrl atau Cmd + K. Di Pengaturan ada ekspor CSV tautan Anda beserta statistiknya.
- Pemeriksa tautan di `/check`: tempel tautan `hel.ink` untuk melihat tujuannya sebelum Anda membukanya. Tidak ada yang diklik atau dihitung.
- Pemantau tujuan: sehari sekali LinkHop menanyakan ke setiap tujuan aktif apakah masih menjawab, lalu mengirim email ke pemilik setelah tiga pemeriksaan gagal berturut-turut. Bisa dimatikan di Pengaturan.
- Peringatan konten: tautan bisa ditandai sensitif atau khusus dewasa, lalu pengunjung melihat pemberitahuan sebelum melanjutkan.
- Perlindungan dari penyalahgunaan: pembatasan laju dan kuota, daftar hitam domain, pemblokiran IP, pemeriksaan proxy dan VPN pada pengunjung, formulir laporan, serta pemindaian setiap jam untuk phishing, penipuan, dan tautan berbahaya lain, dengan opsi pencarian di Google Safe Browsing dan URLhaus.
- API dan webhook: REST API dengan token bercakupan dan kunci idempotensi, webhook bertanda tangan untuk lima peristiwa tautan, dan konfigurasi ShareX.
- Sembilan bahasa: English, 简体中文, Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français, dan Русский. Tambahkan `?lang=id` pada halaman mana pun untuk berganti; pilihan disimpan di cookie.
- Privasi: tanpa iklan, tanpa pelacak pihak ketiga, dan tanpa skrip, font, atau gambar dari CDN. Pustaka dibundel atau ada di `public/vendor`. Dua host pihak ketiga yang tersisa adalah Cloudflare Turnstile pada formulir tamu dan ubin OpenStreetMap di bawah peta analitik.
- Hosting sendiri: satu perintah instalasi, skrip pembaruan dan penghapusan, cadangan setiap malam, dan API admin bertanda tangan untuk pusat kontrol HOP.

Halaman bio (`hel.ink/b/anda`) dibuat di [BioHop](https://github.com/navi-crwn/BioHop), bukan di LinkHop. Satu kasus ketika LinkHop masih melayaninya dijelaskan di [Halaman bio](#halaman-bio).

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="Halaman Analytics: filter, klik dan pengunjung unik dibandingkan periode sebelumnya, negara dan perangkat teratas, serta grafik klik dari waktu ke waktu" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="Halaman My links: daftar yang bisa dicari dengan folder, tag, jumlah klik, dan garis tujuh hari untuk setiap tautan" width="100%"></picture></p>

## Letak masing-masing

| Apa | URL |
| --- | --- |
| Tautan pendek | `hel.ink/l/slug` |
| Bentuk lama tautan pendek | `hel.ink/slug`, dialihkan ke `hel.ink/l/slug` sampai 27 November 2026 (`LEGACY_SLUG_UNTIL`) |
| Halaman bio (BioHop) | `hel.ink/b/name` |
| Dasbor dan API | `link.hel.ink`, masuk lewat hub di `hub.hel.ink` |
| Akun Anda | `hub.hel.ink`: profil, kata sandi, verifikasi dua langkah, token API, unduh data, hapus akun |

Tautan pendek pindah dari `hel.ink/slug` ke `hel.ink/l/slug` pada 28 Oktober 2026 agar `hel.ink` bisa menampung beberapa aplikasi HOP. Tidak ada yang dihapus. Dasbor, kode QR, API, dan ekspor akun menampilkan bentuk yang baru. Pada instalasi Anda sendiri, host publiknya adalah `PUBLIC_URL` (bawaannya `APP_URL`), dan `php artisan links:announce-migration` mengirim email berisi tautan dalam bentuk baru ke setiap pemilik tautan (`--reminder` mengirim email kedua).

## Instalasi

Pemasang memeriksa komputer Anda, mengajukan beberapa pertanyaan, dan bisa memasang hub HOP ID di samping LinkHop:

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

Dari hasil clone, jalankan `./install.sh`. Untuk memperbarui, jalankan `./update.sh`; untuk menghapus LinkHop, jalankan `./uninstall.sh`. Tambahkan `--yes` untuk memakai semua nilai bawaan, `--dry-run` untuk melihat langkah tanpa menjalankannya, dan `--help` untuk opsi lain.

- `install.sh` membutuhkan PHP 8.2 atau lebih baru (memakai `php8.3` bila terpasang; atur `PHP_BIN` untuk memilih yang lain), Composer, Node.js 20 atau lebih baru, dan basis data: SQLite atau MySQL/MariaDB. Skrip ini menulis `.env` yang privat dan `start.sh` yang menjalankan server web, worker antrean, dan penjadwal.
- `update.sh` lebih dulu menyalin basis data ke `backups/` (SQLite atau MySQL/MariaDB), menaruh situs dalam mode pemeliharaan, menarik kode baru, membangun ulang, memigrasi, dan mengembalikan versi lama bila ada langkah yang gagal sebelum migrasi. Jika salinan basis data tidak bisa dibuat, skrip berhenti; `--no-backup` melanjutkan tanpa salinan.
- `uninstall.sh` menghapus paket terpasang dan berkas hasil build, dan mempertahankan `.env`, basis data, unggahan, serta log Anda. `--purge` juga menghapus semuanya itu dan meminta Anda mengetik nama situs terlebih dahulu.

> [!NOTE]
> LinkHop menyimpan akun di hub HOP ID ([HEL.ink](https://github.com/navi-crwn/HEL.ink)); tidak ada mode tanpa hub. Pemasang dapat memasang hub di samping LinkHop (bawaan, sekitar satu menit) atau terhubung ke hub yang sudah Anda jalankan. Untuk memasang hub dan beberapa aplikasi sekaligus, gunakan `install-family.sh` dari repositori HEL.ink.

### Instalasi manual

Anda membutuhkan PHP 8.2 atau lebih baru dengan ekstensi Laravel yang lazim serta `gd` atau `imagick` (kode QR PNG), Composer, Node.js 20.19 atau lebih baru, dan basis data. [Hub](https://github.com/navi-crwn/HEL.ink) harus berjalan di basis data yang sama: hub memiliki tabel akun (`users`, `api_tokens`, dan lainnya) dan memasukkan pengguna.

```bash
# Paket bersama hop/auth, hop/ui, dan hop/safety diambil dari GitHub lewat Composer.
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # lalu ubah .env, lihat di bawah
php artisan migrate --force       # hanya tabel aplikasi ini
php artisan storage:link
php artisan serve --port=8001
```

Untuk pengembangan lokal, atur `APP_ENV=local`, `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001`, `HOP_HUB_URL=http://localhost:8000` (hub dengan `php artisan serve`) dan `SESSION_SECURE_COOKIE=false`, lalu di hub `LINKHOP_URL=http://localhost:8001`. Tamu yang membuka LinkHop diarahkan ke halaman masuk hub dan kembali dalam keadaan masuk.

Di produksi, jaga dua hal tetap berjalan: worker antrean (`php artisan queue:work --tries=3`) dan penjadwal (satu baris cron `* * * * * cd /path/to/linkhop && php artisan schedule:run`). Penjadwal menjalankan pemindaian penyalahgunaan, pemantau tujuan, percobaan ulang webhook, pembersihan privasi, penghapusan tautan tamu, dan pencadangan.

**Kepemilikan basis data.** Setiap aplikasi HOP hanya menjalankan migrasinya sendiri pada basis data bersama. Migrasi LinkHop ada di `database/migrations`. Riwayat tabel akun disimpan di `database/migrations-archive` dan tidak dijalankan lagi; pengujian membangun skema dari sana. Jangan jalankan `migrate:fresh` pada basis data yang dipakai hub.

<details>
<summary><b>Pengaturan di <code>.env</code></b></summary>

Berkas `.env.example` mencantumkan semuanya. Berikut pengaturan yang paling mungkin Anda ubah.

| Pengaturan | Artinya |
| --- | --- |
| `APP_URL` | Dasbor dan API (`https://link.hel.ink` pada layanan yang di-host). |
| `PUBLIC_URL` | Tempat tautan pendek, kode QR, dan sitemap diakses (`https://hel.ink`). Pada instalasi satu host, isi dengan nilai yang sama seperti `APP_URL`. |
| `HOP_APP`, `HOP_HUB_URL`, `HOP_SSO_SECRET` | Koneksi ke hub. `HOP_APP` adalah `linkhop`; rahasianya sama dengan di `.env` hub, minimal 32 karakter. Masuk, login Google, verifikasi dua langkah, dan reset kata sandi dipegang hub, jadi di sini tidak ada yang perlu diatur. |
| `DB_*` | Basis data yang sama dengan hub. |
| `MAIL_*` | Email keluar: pemberitahuan untuk pemilik tautan, pemantau tujuan, dan peringatan pencadangan. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile pada pemendek tamu dan formulir laporan. Di produksi, tanpa kunci rahasia, formulir tamu menolak setiap kiriman. |
| `GUEST_LINK_IDLE_DAYS`, `GUEST_LINK_MAX_DAYS` | Tautan tamu dihapus setelah 14 hari tanpa klik, dan paling lama 30 hari. Server hanya bisa memperpendeknya. |
| `LIMIT_*` | Kuota dari `config/limits.php`, misalnya 10 tautan per menit dan 200 per hari untuk setiap alamat, 200 per hari dan 1000 tautan aktif untuk setiap akun. |
| `SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY` | Pencarian opsional untuk pemindaian penyalahgunaan. |
| `GEOLITE_LICENSE_KEY` | Opsional. Tanpa ini, `geolite:update` mengunduh basis data DB-IP Lite yang gratis. |
| `BIO_ENABLED`, `BIOHOP_URL`, `PIXELHOP_URL` | Paket bio bawaan dan alamat aplikasi lain. |
| `HOP_CONTROL_URL` | Tujuan `/admin` untuk admin: pusat kontrol HOP. |
| `LEGACY_SLUG_UNTIL` | Tanggal pengalihan lama `hel.ink/slug` berhenti. Biarkan kosong pada instalasi baru. |
| `TRUSTED_PROXIES` | Alamat reverse proxy, atau `cloudflare`. |
| `SENTRY_LARAVEL_DSN`, `BACKUP_DISKS` | Pelacakan galat, dan disk tujuan penulisan cadangan (bawaan `local`). |

**Lokasi pengunjung.** Negara dan kota berasal dari basis data DB-IP Lite lokal yang diperbarui `geolite:update` setiap minggu. Bila basis data itu tidak punya jawaban untuk alamat publik, LinkHop menanyakan ke paling banyak dua layanan pencarian web: freeipapi.com, ip-api.com, ipapi.co, ipinfo.io, atau abstractapi.com. Alamat pengunjung hanya disimpan sebagai hash berkunci.

**Cadangan.** Penjadwal menjalankan `backup:run` pukul 02:45 setiap hari. Arsipnya berisi basis data, `storage/app/public`, dan `.env`, dan ditulis ke disk di `BACKUP_DISKS`. Cadangan MySQL atau MariaDB membutuhkan `mysqldump` di server. `.env` berisi rahasia Anda, jadi lindungi disk cadangan atau atur `BACKUP_ARCHIVE_PASSWORD`.

**Di balik Cloudflare.** LinkHop mengirim Content Security Policy yang hanya memercayai skrip dengan nonce halaman. Email Address Obfuscation (Scrape Shield) dan Rocket Loader (Speed, Optimization) milik Cloudflare menyisipkan skrip sendiri tanpa nonce itu, sehingga peramban memblokirnya dan pemblokiran itu dicatat di `storage/logs/csp-*.log`. Matikan keduanya di dasbor Cloudflare. LinkHop tidak membutuhkannya. Menambahkannya ke kebijakan tidak akan berhasil pada kebijakan bernonce, karena `'strict-dynamic'` mengabaikan daftar host yang diizinkan.

**Satu host.** Layanan yang di-host membagi LinkHop menjadi dua: dasbor di `link.hel.ink` dan tautan pendek di `hel.ink/l/`. Aturan nginx untuk pembagian itu ada di repositori hub pada `deploy/nginx/`. Untuk satu host, atur `APP_URL` dan `PUBLIC_URL` ke nilai yang sama.

</details>

## Cara memakai

**Memendekkan tautan.** Tempel alamat panjang, pilih slug, kata sandi, atau tanggal kedaluwarsa bila perlu, tekan Shorten, lalu salin hasilnya. Buka Advanced di beranda untuk opsi tamu; setelah masuk, New link menyediakan semua opsi di atas.

**Halaman bio.** Masuk ke [bio.hel.ink](https://bio.hel.ink) dengan HOP ID yang sama, pilih nama, lalu bagikan `hel.ink/b/namaanda`.

## API

Token API dibuat di hub (HOP ID, Settings, API) dan berlaku di setiap aplikasi HOP. LinkHop membacanya dari tabel bersama `api_tokens`. Token membawa cakupan (scope): `link:create`, `link:read`, dan `link:delete`. Token yang dibuat sebelum ada cakupan tidak punya cakupan dan boleh melakukan semuanya. API menjawab di `link.hel.ink/api`, dan di `hel.ink/api` yang meneruskan ke sana sampai 27 November 2026. Batas laju berlaku per token, 100 permintaan per jam kecuali token menetapkan batasnya sendiri.

Membuat tautan pendek (`link:create`). BioHop memanggil ini ketika sebuah blok bio membutuhkan tautan pendek.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Kolom | | |
| --- | --- | --- |
| `url` | wajib | Alamat lengkap `http://` atau `https://`, hingga 2048 karakter. Ditolak bila pemeriksaan keamanan menandainya. |
| `alias` | opsional | Slug: 3 sampai 255 karakter dari `A-Z a-z 0-9 _ -`. Kata yang dicadangkan dan slug yang sudah dipakai ditolak. Acak bila dikosongkan. |
| `folder` | opsional | Nama folder pada akun. Dibuat bila belum ada. |
| `password` | opsional | 4 sampai 50 karakter; pengunjung harus mengetiknya. |
| `expires_at` | opsional | Tanggal-waktu ISO di masa depan. |
| `starts_at` | opsional | Waktu sebelum pengunjung melihat "Not live yet". |
| `max_clicks` | opsional | Tautan berhenti setelah sejumlah klik ini (1 sampai 10.000.000). |
| `fallback_url` | opsional | Tujuan pengunjung setelah tanggal kedaluwarsa atau batas klik. Tidak boleh tautan pendek lain. |
| `redirect_type` | opsional | `301`, `302` (bawaan), `307`, atau `308`. |
| `content_warning`, `warning_reason` | opsional | `none`, `sensitive`, atau `adult`, dan catatan hingga 255 karakter yang dilihat pengunjung. |
| `routing_rules`, `ab_variants`, `app_links` | opsional | Perutean menurut pengunjung, pembagian A/B, dan deep link. Bentuknya ada di halaman API docs pada dasbor. |

```json
HTTP 201
{"success": true,
 "data": {"url": "https://hel.ink/l/my-link", "slug": "my-link", "target_url": "https://example.com",
          "status": "active", "content_warning": "none", "warning_reason": null,
          "created_at": "2026-11-02T10:00:00+00:00", "expires_at": null, "starts_at": null,
          "max_clicks": null, "fallback_url": null, "routing_rules": [], "ab_variants": [],
          "app_links": null, "redirect_type": "302"},
 "message": "Link created successfully"}
```

Galat selalu berbentuk sama, `{"success": false, "error": "…", "message": "…"}`. `401` berarti token tidak dikenal atau sudah kedaluwarsa, `403` cakupan tidak ada atau akun ditangguhkan atau belum terverifikasi, `422` galat validasi (`messages` mencantumkan kolomnya), dan `429` kuota atau batas laju.

Kirim header `Idempotency-Key` pada `POST /api/shorten` agar aman diulang. Kunci yang sama dengan isi yang sama mengembalikan jawaban pertama (`Idempotent-Replayed: true`) dan tidak membuat apa pun dua kali. Kunci yang sama dengan isi lain menghasilkan `422`. Kunci disimpan selama 24 jam.

Baca tautan (`link:read`) dengan `GET /api/links/{slug}`, yang mengembalikan `data` yang sama ditambah `clicks`. Hapus (`link:delete`) dengan `DELETE /api/links/{slug}`. Keduanya menjawab `404` untuk slug yang tidak ada di akun pemilik token.

Webhook diatur di Pengaturan: hingga lima alamat https, masing-masing dipanggil untuk `link.created`, `link.updated`, `link.deleted`, `link.limit_reached`, dan `link.expired`. Setiap pesan membawa `X-LinkHop-Signature`, yaitu HMAC-SHA256 dari stempel waktu dan isi pesan. Pengiriman yang gagal dicoba lagi setelah 1 menit, 5 menit, 30 menit, dan 2 jam, dan webhook yang gagal sepuluh pengiriman berturut-turut dimatikan.

Konfigurasi ShareX bisa diunduh dari halaman API docs pada dasbor.

## FAQ

**Apakah gratis?** Ya. Tidak ada paket berbayar dan tidak ada fitur yang dikunci.

**Apakah saya perlu akun?** Tidak. Siapa pun bisa memendekkan tautan tanpa akun. Tautan tamu dihapus setelah 14 hari tanpa klik, dan paling lama 30 hari. HOP ID gratis menyimpan tautan Anda, menampilkan statistiknya, dan memasukkan Anda ke aplikasi HOP lain.

**Di mana halaman bio saya?** Di BioHop, pada `hel.ink/b/` diikuti nama yang Anda pilih. Ubah di [bio.hel.ink](https://bio.hel.ink).

**Apa yang dicatat sebuah klik?** Waktu, negara dan kota, perangkat, peramban, dan perujuk, serta hash berkunci dari alamat dan peramban yang berubah setiap hari, yang dipakai untuk menghitung pengunjung unik. Alamatnya sendiri tidak disimpan, dan log klik dihapus setelah 30 hari.

**Bagaimana melaporkan tautan buruk?** Pakai formulir laporan di `/report-abuse`. Laporan masuk ke antrean penyalahgunaan di pusat kontrol HOP, di samping temuan pemindaian otomatis.

**Bisakah saya memakai domain sendiri?** Bisa, dengan menjalankan salinan Anda sendiri di samping hub. `APP_URL` adalah host dasbor, `PUBLIC_URL` host untuk tautan pendek, dan `HOP_HUB_URL` adalah hub.

### Halaman bio

Di hel.ink, halaman bio milik BioHop. Instalasi satu server tanpa BioHop tetap bisa melayaninya dari paket `hop/bio` bawaan (`packages/bio`) dengan mengatur `BIO_ENABLED=true`. Bila BioHop ada di sampingnya, biarkan `false`: paket itu lalu tidak mendaftarkan rute dan tidak membutuhkan tabel. `php artisan db:seed --class=ExampleBioSeeder` membuat halaman bio contoh dan beberapa tautan pendek contoh pada instalasi pengembangan.

## Di balik layar

| Lapisan | Teknologi |
| --- | --- |
| Backend | Laravel 12, PHP 8.2 atau lebih baru, SQLite atau MySQL/MariaDB |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 7, serta GSAP, Lenis, dan Three.js untuk beranda |
| Grafik dan peta | Chart.js 4, Leaflet 1.9 dengan ubin OpenStreetMap |
| Akun | HOP ID di hub, lewat `hop/auth` (klien SSO, serta middleware `hop.auth` dan `hop.token`) |
| Paket bersama | `hop/auth`, `hop/ui` (pengalih aplikasi, font), `hop/safety` (keamanan URL dan konten), `hop/bio` (dibundel di `packages/bio`) |
| Layanan | Cloudflare Turnstile, DB-IP Lite dan layanan pencarian web opsional untuk lokasi pengunjung, Google Safe Browsing dan URLhaus opsional, Sentry |

| Folder | Isinya |
| --- | --- |
| `app/`, `routes/`, `resources/` | Aplikasi Laravel: controller, model, view Blade, skrip, dan gaya. |
| `config/hop.php`, `config/limits.php`, `config/shortener.php` | Daftar keluarga HOP (daftar `apps` yang sama dengan hub), kuota tautan, serta pengaturan tautan tamu, slug, dan pemantau. Pola dan ambang keamanan berasal dari `hop/safety` (`config('safety')`). |
| `database/migrations/`, `database/migrations-archive/` | Tabel milik LinkHop sendiri, dan riwayat tabel akun (kini milik hub, tidak dijalankan). |
| `lang/` | Sembilan berkas bahasa. |
| `tests/` | Pengujian Pest dan PHPUnit (`php artisan test`), serta pengujian peramban dengan pengguna sintetis di `tests/e2e/` ([cara menjalankannya](tests/e2e/README.md)). |
| `docs/` | Situs web di [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/). `docs/readme/` berisi tangkapan layar README; `scripts/readme-images.mjs` mengambilnya ulang. |
| `scripts/` | Skrip bantu: `build-changelog.py` (changelog dalam aplikasi), `check-hop-family.sh` (tabel keluarga di README bahasa Inggris), dan `readme-images.mjs`. |

Setiap dependensi beserta lisensinya tercantum di [CREDITS.md](CREDITS.md).

## Berkontribusi

1. Fork repositori ini dan buat branch.
2. Buat perubahan Anda, lalu jalankan `vendor/bin/pint` dan `php artisan test`.
3. Push dan buka pull request ke `main`.

Mohon tambahkan pengujian untuk perilaku baru. Laporkan masalah keamanan secara privat, seperti dijelaskan di [SECURITY.md](SECURITY.md), dan gunakan templat isu untuk hal lainnya.

## Lisensi

MIT. Lihat [LICENSE](LICENSE).

---

<p align="center">Dibuat oleh <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">Situs web</a></p>
