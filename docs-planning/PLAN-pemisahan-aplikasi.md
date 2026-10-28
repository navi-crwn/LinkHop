# Rencana pemisahan HEL.ink menjadi hub + aplikasi

Status: draf keputusan, 28 Oktober 2026. Belum ada kode yang diubah.

Dokumen ini dibuat untuk mengambil keputusan, bukan untuk langsung dikerjakan. Bagian 3 berisi pertanyaan yang harus dijawab lebih dulu; bagian 4 adalah rencana fase yang bentuknya tergantung jawaban itu. Isi formulir di bagian 7 setelah membaca.

---

## 1. Arsitektur target yang diusulkan

```
                    pengunjung
                        |
                 hel.ink (router di edge)
     +----------------+------------------+----------------+
     |                |                  |                |
   /  /about ...    /b/*               /api/*           /{slug}
     |                |                  |                |
   hub            bio.hel.ink        link.hel.ink     link.hel.ink
 (halaman utama)  (Link in Bio)      (API shortener)  (redirect)

 dashboard:   link.hel.ink   bio.hel.ink   p.hel.ink (PixelHop)
 akun:        satu akun HEL.ink, dibagikan lewat tiket SSO (pola PixelHop)
```

URL publik tidak berubah: `hel.ink/slug` dan `hel.ink/b/slug` tetap hidup, QR code dan link yang sudah tersebar tidak perlu diganti.

## 2. Kondisi sekarang (yang relevan)

| Hal | Fakta |
| --- | --- |
| Kode bio | ±1.600 baris PHP (3 controller, 3 model, 3 support), 29 dari 66 migration, folder view sendiri, halaman admin sendiri, 5 file test |
| Namespace slug | Satu DB: `hel.ink/{slug}` cek tabel bio dulu, lalu tabel link. Bio di `/b/navi` otomatis mengklaim `hel.ink/navi` |
| Bio bikin short link | `/api/bio/shorten` memanggil `LinkService` langsung |
| Pembentukan URL | `Link::short_url` memakai `url($slug)`; URL bio dibentuk dengan `url('/b/...')` di ±47 tempat. Semuanya bergantung `APP_URL` |
| Auth dan akun | Password, Google, 2FA, Turnstile, verifikasi email, reset, dan penerbit tiket SSO untuk PixelHop semua di app ini |
| Layanan bersama | LinkSafety, blacklist domain, `abuse:scan`, GeoIP, parser user agent, CSP (daftar host embed bio), kuota, sitemap, ekspor dan hapus data pengguna, upload ke disk `public` |
| Preseden | PixelHop (p.hel.ink) sudah jadi app terpisah dengan tiket SSO HMAC, umur 2 menit, dipakai lewat `PixelHopSsoController` |
| Janji ke publik | README: "everything is included", satu app untuk self-host. Website memakai `hel.ink/b/hel` sebagai contoh |
| Infra | Di belakang Cloudflare (`TRUSTED_PROXIES=cloudflare`, Turnstile) |

---

## 3. Pertanyaan keputusan

Setiap pertanyaan punya opsi, pros/cons, dan rekomendasi. Urutannya dari yang paling menentukan.

### D1. Apa tujuan utama pemisahan?

Jawaban ini menentukan sampai fase berapa rencana dijalankan.

| Tujuan | Cukup sampai | Catatan |
| --- | --- | --- |
| Kode lebih rapi, bug bio tidak menyentuh shortener | Fase 1 (modularisasi + flag) | Tidak perlu app baru, tidak ada migrasi |
| Self-hoster bisa pilih hanya shortener | Fase 1 | Flag `BIO_ENABLED=false` |
| Brand per produk, dashboard terpisah, rilis independen | Fase 2 sampai 4 | Inilah yang Anda gambarkan |
| Monetisasi atau tim berbeda per produk | Fase 2 sampai 5 | Pusat akun (D2) jadi lebih penting |

Rekomendasi: tulis tujuannya satu kalimat di formulir. Kalau jawabannya hanya baris pertama atau kedua, berhenti di Fase 1 dan sisanya tidak perlu dibaca sekarang.

### D2. Di mana akun dan login hidup?

**Opsi A. Tetap di app link (link.hel.ink).** Hub hanya punya tombol "Masuk" yang mengarah ke link.hel.ink. Bio dan PixelHop jadi konsumen SSO dari app link.

- Pro: hampir nol pekerjaan tambahan, penerbit SSO sudah ada dan teruji, Google OAuth hanya ganti redirect URI.
- Pro: risiko paling kecil pada hal yang paling sensitif (password, 2FA, sesi).
- Kontra: secara konsep ganjil. "Akun HEL.ink" dikelola oleh app shortener; kalau nanti app link mau diganti atau dimatikan, akun ikut.
- Kontra: pengaturan profil, 2FA, hapus akun ada di link.hel.ink, padahal pengguna mungkin hanya memakai bio.

**Opsi B. Pusat akun di hel.ink (atau accounts.hel.ink).** Hub menjadi app Laravel yang memegang user, auth, profil, admin pengguna, dan menerbitkan tiket SSO. App link ikut jadi konsumen.

- Pro: bentuk akhirnya bersih; setiap produk hanya konsumen, akun hidup di satu tempat netral.
- Pro: halaman profil, keamanan, dan hapus akun satu pintu untuk semua produk.
- Kontra: migrasi besar: tabel users, login_history, api_tokens(?), activity_log, 2FA, Google, Turnstile, verifikasi, reset, admin pengguna, suspensi. Setara atau lebih besar dari memisahkan bio.
- Kontra: app link kehilangan sesi lokalnya dan harus menerima tiket, lalu memetakan ulang semua `auth()->user()` ke user lokal yang disinkron. Admin panel yang hari ini satu harus dipecah: admin user di hub, admin link di link, admin bio di bio.
- Kontra: downtime atau masa transisi untuk login.

**Opsi C. A sekarang, B nanti.** Rancang Fase 2 supaya penerbit SSO bisa dipindah (gunakan nama host `SSO_ISSUER_URL` di konsumen, bukan hardcode), kerjakan B sebagai proyek terpisah setelah bio stabil.

Rekomendasi: **C**. Yang penting sekarang hanya satu: setiap konsumen membaca alamat penerbit SSO dari config, dan tiket membawa `sub` = user id, sehingga memindahkan penerbit nanti tidak mengubah data di konsumen.

### D3. Siapa yang menjadi router di hel.ink?

**Opsi A. Cloudflare Worker (atau Cloudflare Rules + origin rules).** Worker membaca path dan memilih origin.

- Pro: tidak tergantung server; app bisa pindah server tanpa mengubah router. Cache dan WAF tetap di depan.
- Pro: logika router kecil (puluhan baris) dan bisa diuji sendiri.
- Kontra: satu lapisan baru yang harus dideploy dan dipantau; kalau Worker error, hel.ink mati total.
- Kontra: biaya Worker setelah kuota gratis; tiap klik short link lewat Worker.

**Opsi B. nginx di server yang sama.** Satu server block `hel.ink` dengan `location` per path yang `proxy_pass` ke vhost internal tiap app.

- Pro: tidak ada komponen baru di luar server; gratis; latensi internal nol.
- Pro: mudah dipahami self-hoster.
- Kontra: ketiga app harus di satu mesin (atau nginx jadi reverse proxy ke mesin lain, yang sama saja dengan Worker tapi di-host sendiri).
- Kontra: skala terikat satu server.

**Opsi C. App hub Laravel memproxy sendiri.** Hub menerima semua request dan meneruskan lewat HTTP client.

- Kontra: tiap klik short link lewat PHP dua kali. Tidak disarankan.

Rekomendasi: **B** kalau semua app memang di satu server (sekarang begitu); **A** kalau rencana memisahkan server. Keduanya memerlukan hal yang sama di sisi app: `PUBLIC_URL`, `TRUSTED_PROXIES`, nama cookie unik.

### D4. Apakah bio tetap bisa diakses di `hel.ink/{slug}` tanpa `/b/`?

**Opsi A. Hentikan alias root.** Bio hanya di `hel.ink/b/slug`. Router: `/b/*` ke bio, sisanya ke link.

- Pro: router tetap bodoh, tidak ada state bersama, tidak ada race antara dua app saat klaim slug.
- Pro: namespace slug link dan bio jadi terpisah; bio `navi` dan link `navi` boleh sama-sama ada.
- Kontra: pengguna yang sudah membagikan `hel.ink/navi` untuk bio-nya kehilangan alias itu. Perlu dicek berapa yang memakainya (lihat Fase 0).
- Kontra: sebaliknya, kalau namespace dipisah, link `hel.ink/navi` dan bio `hel.ink/b/navi` bisa dimiliki orang berbeda, berpotensi membingungkan atau disalahgunakan untuk impersonasi.

**Opsi B. Pertahankan alias lewat tabel bersama.** App link menyimpan tabel `external_slugs` (slug → url tujuan) yang diisi app bio lewat API internal saat bio dibuat, diganti nama, dihapus, atau di-unpublish.

- Pro: alias tetap hidup, satu namespace tetap terjaga.
- Kontra: dua app harus menjaga konsistensi; setiap perubahan slug bio jadi dua tulisan. Kalau API internal gagal, alias basi.
- Kontra: klaim slug harus dicek di dua tempat; tetap ada race kecil.

**Opsi C. Hentikan alias, tapi pertahankan reservasi silang.** Namespace tetap satu: saat bio `navi` dibuat, app bio minta app link mereservasi slug `navi` (dan sebaliknya), tanpa redirect. Alias `hel.ink/navi` mati, tapi tidak bisa diambil orang lain.

- Pro: mencegah impersonasi tanpa redirect yang harus selalu benar.
- Kontra: tetap ada API internal dua arah, hanya lebih sederhana dari B.

Rekomendasi: **A** kalau data Fase 0 menunjukkan alias root jarang dipakai; **C** kalau ingin menjaga "satu nama untuk satu orang" di seluruh hel.ink. B hanya kalau alias memang fitur yang dipromosikan.

### D5. Database: terpisah per app atau satu DB bersama?

**Opsi A. DB terpisah per app.** Bio punya DB sendiri; user di bio adalah salinan ringan (id, nama, email, avatar, admin, suspended) yang disinkron dari tiket SSO dan webhook.

- Pro: batas kepemilikan skema jelas, migration tidak saling injak, bisa di-host terpisah.
- Pro: pola yang sudah dipakai PixelHop.
- Kontra: data pengguna harus disinkron: suspensi, hapus akun, ganti nama atau email. Butuh webhook dari penerbit akun ke setiap konsumen plus mekanisme ulang kalau gagal.
- Kontra: ekspor data pengguna (fitur yang sudah ada di Settings) harus mengumpulkan dari beberapa app.

**Opsi B. Satu DB, dua app.** Bio dan link membaca tabel `users` yang sama.

- Pro: tidak ada sinkronisasi; suspensi dan hapus akun otomatis konsisten; migrasi data nol.
- Pro: alias slug root (D4) bisa tetap pakai cara lama.
- Kontra: dua codebase mengelola satu skema; siapa yang punya migration `users`? Deploy harus dikoordinasi; satu app bisa merusak app lain lewat migration.
- Kontra: tidak bisa di-host terpisah; kebocoran di satu app membuka seluruh DB.
- Kontra: untuk self-hoster, dua app yang berbagi DB lebih rawan salah konfigurasi daripada dua app mandiri.

Rekomendasi: **A**, karena konsisten dengan PixelHop dan dengan D2 opsi C. B hanya masuk akal kalau D2 dijawab "akun tetap di app link selamanya" dan D4 dijawab "pertahankan alias".

### D6. Apa bentuk hub hel.ink?

**Opsi A. Situs statis atau app sangat tipis.** Halaman utama, products, about, privacy, terms, help, contact. Tombol Masuk ke penerbit SSO. Bisa dari `docs/` yang sudah ada (GitHub Pages) atau Laravel kecil.

- Pro: hampir tidak ada permukaan serangan, murah dirawat, cepat.
- Kontra: tidak bisa menjadi pusat akun (D2 opsi B) tanpa dijadikan app.
- Kontra: halaman marketing yang hari ini di app link (`MarketingPageController`, 9 halaman) harus dipindahkan dan dua kali dipelihara kalau app link juga tetap punya halaman serupa.

**Opsi B. App Laravel penuh yang juga pusat akun.** Sama dengan D2 opsi B.

Rekomendasi: **A** dulu, naik ke B bersamaan dengan D2 opsi B kalau nanti diputuskan. Halaman marketing dipindah ke hub dan dihapus dari app link, supaya satu sumber.

### D7. Apakah dashboard shortener benar-benar perlu pindah ke link.hel.ink?

Pertanyaan ini sering terlewat. Memisahkan bio tidak mengharuskan memindahkan shortener.

**Opsi A. Pindah ke link.hel.ink** (rencana Anda).

- Pro: simetris: setiap produk di subdomainnya, hel.ink netral. Nama "LinkHop" bisa hidup sendiri.
- Kontra: semua yang mengarah ke dashboard berubah: bookmark, email verifikasi dan reset yang masih beredar, Google OAuth redirect URI, dokumentasi API, tautan di PixelHop, cookie sesi (semua pengguna logout satu kali).
- Kontra: router wajib ada sejak hari pertama, karena `hel.ink/slug` tidak lagi dilayani app yang sama dengan hub.

**Opsi B. Shortener tetap di hel.ink, hub adalah halaman utama app link.** Hanya bio yang keluar ke bio.hel.ink; `/b/*` diproxy ke sana.

- Pro: router hanya satu aturan (`/b/*`), tidak ada perubahan sesi, OAuth, atau API. Risiko turun drastis.
- Pro: hel.ink/slug tetap dilayani langsung tanpa hop proxy.
- Kontra: hel.ink tidak "murni halaman utama"; dashboard link tetap di sana. Brand LinkHop tidak punya rumah sendiri.
- Kontra: kalau nanti tetap ingin pindah, pekerjaannya tidak hilang, hanya ditunda.

**Opsi C. B dulu, A kemudian.** Pisahkan bio lebih dulu (perubahan yang Anda inginkan paling banyak ada di sini), lalu pindahkan dashboard link setelah router dan SSO terbukti.

Rekomendasi: **C**. Dua migrasi besar yang digabung dalam satu cutover memperbesar peluang downtime dan menyulitkan mencari penyebab kalau ada yang rusak.

### D8. Ke mana API publik?

**Opsi A. Tetap `hel.ink/api/*`, diproxy ke app link.**

- Pro: token dan integrasi pengguna tidak putus; dokumentasi tidak berubah.
- Kontra: satu aturan router lagi; `/api` menjadi slug reserved selamanya (sudah begitu).

**Opsi B. Pindah ke `link.hel.ink/api/*`, `hel.ink/api/*` 308 selama masa deprecation.**

- Pro: API hidup bersama produknya.
- Kontra: klien yang tidak mengikuti redirect pada POST akan gagal; perlu komunikasi dan jendela deprecation (sarankan 12 bulan).

Rekomendasi: **A**, lalu tambahkan `link.hel.ink/api` sebagai alamat kanonik baru di dokumentasi tanpa mematikan yang lama.

### D9. Bagaimana nasib self-hoster?

**Opsi A. Mode standalone tetap didukung.** Repo hel.ink tetap bisa dijalankan sebagai satu app dengan bio di dalamnya lewat flag; bio.hel.ink adalah deployment dari repo bio yang sama kodenya tetapi dipasang terpisah. Ini hanya mungkin kalau kode bio tetap berupa paket Composer yang bisa dipasang di dua tempat.

- Pro: janji README terjaga; satu perintah install tetap ada.
- Kontra: satu paket, dua cara pasang, dua jalur uji. Setiap fitur bio harus bekerja sebagai modul internal dan sebagai app mandiri.

**Opsi B. Self-host = tiga app + router.** Sediakan `docker-compose` yang menyalakan hub, link, bio, dan nginx router.

- Pro: satu cara jalan, sama dengan produksi.
- Kontra: ambang masuk naik; README harus menurunkan janjinya; pengguna yang hanya mau shortener kecil akan merasa berat.

**Opsi C. Shortener standalone tetap satu app (tanpa bio), bio adalah app opsional.** Flag `BIO_ENABLED` dihapus; app link tidak lagi berisi bio sama sekali; yang mau bio memasang app kedua.

- Pro: paling sederhana secara kode: tidak ada dua mode.
- Kontra: self-hoster yang hari ini memakai bio harus memasang app kedua dan memigrasikan data saat upgrade. Perlu perintah `bio:export` dan panduan.

Rekomendasi: **C** untuk jangka panjang, dengan rilis transisi yang masih punya flag (Fase 1) supaya self-hoster punya waktu. Ini keputusan yang paling berdampak ke komunitas, jadi layak diumumkan dulu di CHANGELOG atau issue sebelum dikerjakan.

### D10. Urutan: sekali pindah atau bertahap?

**Opsi A. Big bang.** Semua subdomain, router, dan pemisahan bio dalam satu cutover.

- Pro: satu kali pengumuman, satu kali logout massal, tidak ada arsitektur setengah jadi.
- Kontra: kalau ada yang rusak, penyebabnya bisa di tiga tempat; rollback berarti mengembalikan semuanya.

**Opsi B. Bertahap** (sesuai Fase 1 sampai 5 di bawah).

- Pro: tiap fase bisa dirilis dan di-rollback sendiri; produksi tetap jalan di antara fase.
- Kontra: lebih lama kalender; ada periode arsitektur campuran yang harus didokumentasikan.

Rekomendasi: **B**.

---

## 4. Rencana fase

Ukuran relatif: S (hari), M (sekitar seminggu), L (beberapa minggu). Angka kasar, untuk membandingkan antar fase, bukan janji.

### Fase 0. Ukur dulu (S)

Sebelum memutuskan D4 dan D9, ambil angka dari produksi:

- Berapa bio page yang pernah menerima klik lewat `hel.ink/{slug}` tanpa `/b/` (dapat dari log akses, redirect di `RedirectController`).
- Berapa pengguna aktif yang punya bio page saja, link saja, keduanya.
- Berapa pemanggil API aktif per bulan dan token yang dipakai.
- Berapa self-hoster yang terlihat (stars, issue, versi yang dilaporkan).

Keluaran: angka di formulir bagian 7.

### Fase 1. Modularisasi di tempat (M)

Hasil: produksi tidak berubah dari luar, tetapi bio sudah terpisah secara kode dan siap diangkat.

- Pindahkan controller, model, policy, support, view, migration, dan test bio ke satu namespace (misalnya `app/Bio` atau paket `packages/bio`).
- Bio hanya memanggil inti lewat kontrak kecil: `ShortlinkCreator`, `UrlSafety`, `GeoLookup`, `UserAgentParser`. Hari ini panggilan langsung ke `LinkService`, `LinkSafety`, `GeoIpService`, `UserAgentService`.
- Tambah `PUBLIC_URL` (alamat publik untuk short link dan bio) terpisah dari `APP_URL` (alamat dashboard). Ganti `url($slug)` dan `url('/b/...')` ke helper yang membaca `PUBLIC_URL`.
- Beri nama cookie sesi yang unik (`SESSION_COOKIE=helink_session`) sekarang, supaya saat ada tiga app di bawah hel.ink tidak bertabrakan.
- `ReservedSlugs` membaca daftar path hub dari config, bukan hanya dari router app ini.
- Flag `BIO_ENABLED`; saat `false`, route bio tidak didaftarkan, menu disembunyikan, kuota bio hilang dari dashboard, abuse scan melewati bio.
- Test: seluruh suite hijau dengan flag on dan off.

Nilai fase ini berdiri sendiri: kalau rencana berhenti di sini, repo sudah lebih sehat.

### Fase 2. App bio mandiri di bio.hel.ink (L)

Prasyarat: D2, D4, D5 dijawab.

- Repo baru dari paket bio Fase 1. Klien SSO meniru alur PixelHop: `state` di cookie, tiket HMAC, verifikasi `exp`, `jti` sekali pakai.
- Tabel `users` lokal ringan diisi dari tiket; webhook dari penerbit akun untuk `user.suspended`, `user.deleted`, `user.updated`, dengan antrean ulang.
- Pembuatan short link dari blok bio memanggil API HEL.ink dengan token layanan per pengguna (atau tiket berumur pendek), bukan `LinkService`.
- Migrasi data: `bio_pages`, `bio_links`, `bio_clicks`, dan file di `storage/app/public` milik bio. Jalankan dua kali: gladi bersih ke staging, lalu produksi dengan jendela beku singkat (bio read-only beberapa menit).
- Router: `hel.ink/b/*` dan `/b/click/*` ke bio.hel.ink. `bio.hel.ink/b/*` 301 ke `hel.ink/b/*` supaya tidak ada duplikat SEO. Aset Vite absolut ke bio.hel.ink.
- Halaman admin bio, `abuse:scan` untuk bio, prune `bio_clicks`, dan sitemap bio pindah ke app bio. Sitemap hub merujuk ke sitemap bio.
- Setelah stabil: hapus modul bio dari app link (atau tinggalkan flag `false` satu rilis untuk self-hoster, sesuai D9).

### Fase 3. Hub hel.ink dan router penuh (M)

Prasyarat: D3, D6, D7, D8 dijawab.

- Pasang router sesuai D3 dengan urutan: path hub → `/b/*` → `/api/*` → `/{slug}`.
- Halaman marketing pindah dari app link ke hub; app link menyimpan hanya halaman yang memang miliknya (API docs, help untuk fitur link).
- Turnstile: tambah host; CSP tiap app disesuaikan dengan host aset barunya.
- Health check per origin dan halaman error router yang jelas ("layanan link sedang gangguan"), bukan 502 kosong.

### Fase 4. Dashboard shortener pindah ke link.hel.ink (M)

Prasyarat: Fase 3 hidup minimal beberapa minggu.

- `APP_URL=https://link.hel.ink`, `PUBLIC_URL=https://hel.ink`. Google OAuth redirect URI baru. `hel.ink/dashboard`, `/login`, `/settings` dan kawan-kawan 301 ke link.hel.ink.
- Email yang masih beredar (verifikasi, reset) tetap bekerja karena path lama dialihkan.
- Pengguna logout satu kali; umumkan sebelumnya.
- PixelHop dan bio: `SSO_ISSUER_URL` diganti ke link.hel.ink (atau tidak berubah kalau penerbit sudah dipisahkan).

### Fase 5. Pusat akun (L, opsional)

Hanya kalau D2 dijawab B. Tarik auth, profil, admin pengguna, dan penerbit SSO ke hel.ink atau accounts.hel.ink; app link menjadi konsumen seperti bio dan PixelHop. Proyek tersendiri dengan dokumen keputusan sendiri.

---

## 5. Daftar periksa teknis lintas fase

- `PUBLIC_URL` vs `APP_URL` di setiap app; tidak ada `url()` telanjang untuk URL publik.
- `SESSION_COOKIE` unik per app; `SESSION_DOMAIN` tetap null.
- `TRUSTED_PROXIES` mencakup router internal, bukan hanya Cloudflare, supaya IP di analitik dan Turnstile tetap benar.
- Reserved slugs = path hub + path app link + `b` + `api`.
- Canonical dan 301 dari host subdomain ke host publik untuk semua halaman publik.
- Turnstile site key mencakup semua host yang menampilkan widget.
- Google OAuth redirect URI diperbarui saat Fase 4.
- Webhook akun: suspended, deleted, updated; antrean ulang; endpoint idempoten.
- Ekspor data pengguna mengumpulkan dari semua app (atau tiap app punya ekspor sendiri dan hub hanya menautkan).
- Hapus akun menyebar ke semua app dalam satu alur, termasuk file upload.
- QR code: tidak ada URL baru di QR; `PUBLIC_URL` memastikan QR lama dan baru identik.
- Sitemap: hub mengindeks sitemap tiap app.
- CHANGELOG, README (EN dan ID), website `docs/`, halaman products, panduan self-host, dan `.env.example` tiap app diperbarui di fase yang bersangkutan.

## 6. Risiko utama dan mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Router mati, semua produk ikut mati | Tinggi | Health check per origin, halaman error yang jelas, router sesederhana mungkin, bisa dilewati dengan DNS langsung ke app link sebagai darurat |
| Short URL berubah menjadi link.hel.ink/slug karena `APP_URL` | Tinggi, merusak QR dan link di luar | `PUBLIC_URL` dibuat dan diuji di Fase 1, sebelum ada subdomain |
| Cookie sesi bertabrakan di host hel.ink | Sedang, login acak gagal | Nama cookie unik sejak Fase 1 |
| Data bio hilang atau terduplikasi saat migrasi | Tinggi | Gladi bersih ke staging, jendela beku, verifikasi jumlah baris dan checksum, simpan tabel lama 90 hari |
| Suspensi tidak tersinkron, halaman bio pelaku abuse tetap tayang | Sedang | Webhook plus pemeriksaan status dari penerbit saat render dengan cache singkat |
| Duplikat SEO antara hel.ink dan subdomain | Sedang | 301 dari subdomain untuk semua path publik, canonical di setiap halaman |
| Komunitas self-hoster kecewa | Sedang | Umumkan D9 sebelum dikerjakan, rilis transisi dengan flag, perintah ekspor bio |
| Dua cutover sekaligus sulit didiagnosis | Tinggi | D10 opsi B, satu fase per rilis |

## 7. Formulir keputusan

Isi kolom Pilihan. Baris D1 cukup kalimat bebas.

| # | Pertanyaan | Opsi | Rekomendasi | Pilihan Anda |
| --- | --- | --- | --- | --- |
| D1 | Tujuan utama pemisahan | lihat tabel | tulis satu kalimat | |
| D2 | Lokasi akun dan login | A link / B hub / C A lalu B | C | |
| D3 | Router di hel.ink | A Worker / B nginx / C Laravel | B (satu server) atau A | |
| D4 | Alias `hel.ink/{slug}` untuk bio | A hentikan / B tabel bersama / C reservasi silang | A atau C, lihat Fase 0 | |
| D5 | Database | A per app / B bersama | A | |
| D6 | Bentuk hub | A tipis / B app penuh | A | |
| D7 | Dashboard shortener pindah | A sekarang / B tidak / C bio dulu | C | |
| D8 | API publik | A tetap hel.ink/api / B pindah + deprecation | A | |
| D9 | Self-hoster | A dua mode / B tiga app / C shortener tanpa bio | C dengan transisi | |
| D10 | Urutan | A big bang / B bertahap | B | |

Angka dari Fase 0:

- Bio page yang pernah diakses lewat alias root: ____
- Pengguna bio saja / link saja / keduanya: ____ / ____ / ____
- Pemanggil API aktif per bulan: ____

---

# Bagian 8. Keputusan 28 Oktober 2026 dan rencana yang disesuaikan

## 8.1 Keputusan pemilik

| # | Keputusan |
| --- | --- |
| Tujuan | Brand per produk, dashboard terpisah, hel.ink menjadi beranda semua app HOP. "Hop Easy Link" = satu lompatan ke setiap app (LinkHop, BioHop, PixelHop). Siap untuk langganan HOP kalau ada monetisasi |
| Akun | Portal utama di hub.hel.ink (login, profil, pengendalian semua app, pilih app). Tiap produk tetap punya tombol masuk yang mengarah ke portal |
| URL short link | Berubah menjadi `hel.ink/l/{slug}`. Bentuk lama dialihkan 30 hari, pengguna diberi tahu lewat email |
| Waktu | Secepatnya, tidak menunggu |
| Biaya | Tidak ada biaya tambahan Cloudflare |
| Bio | Tetap di `/b/`. Namespace link dan bio terpisah, pengguna berbeda boleh punya slug sama |
| Database | Satu database bersama |
| Hub | hub.hel.ink, teknologi menunggu rekomendasi |
| Dashboard shortener | Wajib pindah ke link.hel.ink |
| API | API per app, ditambah API master yang mengatur, memeriksa, sanitasi, rate limit, watchdog |
| Repo | LinkHop dan BioHop repo sendiri. Repo hel.ink menjadi induk: daftar app, kegunaan, cara pasang, kelebihan dan kekurangan, plus website github.io untuk semua app |
| Pengguna | Hitungan jari, kunjungan ulang sedikit. Perubahan paksa dapat diterima |
| ToS | Tambahan klausul: gratis, perubahan wajib diikuti, pemberitahuan lewat dashboard sebelum perubahan |

## 8.2 Rekomendasi untuk poin yang masih terbuka

### Router hel.ink: nginx di server yang sama, Cloudflare hanya DNS dan proxy gratis

Alasan: nol biaya, semua app sudah di satu server, dan dengan `/l/` dan `/b/` sebagai prefix, aturannya hanya tiga baris. Cloudflare free tetap di depan untuk DNS, TLS, WAF, dan Turnstile seperti sekarang; tidak perlu Worker karena tidak ada lagi catch-all `/{slug}` yang harus dipilah.

```
hel.ink
  /l/*        -> upstream linkhop   (publik: redirect short link)
  /b/*        -> upstream biohop    (publik: halaman bio)
  /*          -> upstream hub       (beranda, marketing, /login -> hub.hel.ink)
  /{slug}  -> 301 /l/{slug}   (hanya untuk path satu segmen yang bukan path hub)

hub.hel.ink   -> hub
link.hel.ink  -> linkhop (dashboard + /api)
bio.hel.ink   -> biohop  (dashboard + /api)
p.hel.ink     -> pixelhop (sudah ada)
```

Satu catatan pada "link lama dihapus setelah 30 hari": pengalihan `hel.ink/{slug}` ke `/l/{slug}` adalah satu baris nginx tanpa biaya dan tanpa data. Menghapusnya setelah 30 hari tidak menghemat apa pun, hanya mematikan QR dan link yang sudah tercetak. Saran: email pemberitahuan tetap dikirim, bentuk kanonik berubah ke `/l/`, tetapi pengalihan dibiarkan hidup tanpa batas. Kalau tetap ingin dihapus, cukup hapus satu baris itu nanti; tidak perlu dirancang sekarang.

### Alias slug: prefix membuatnya selesai dengan sendirinya

Dengan `/l/` untuk link dan `/b/` untuk bio, hub memiliki seluruh root hel.ink dan tidak ada lagi tabrakan antara slug dan halaman marketing. `ReservedSlugs` tidak perlu lagi tahu path hub; cukup melarang slug kosong dan kata yang menyinggung. Ini manfaat terbesar dari keputusan `/l/`.

Risiko impersonasi (`/l/navi` dan `/b/navi` milik orang berbeda) sudah Anda terima. Mitigasi murah: halaman bio dan halaman pratinjau link menampilkan nama pemilik atau avatar, sehingga identitas terlihat dari isi, bukan dari slug.

### Hub: Laravel, satu app untuk hel.ink dan hub.hel.ink

Alasan: semua kode akun yang ada (password, Google, 2FA, Turnstile, verifikasi, reset, suspensi, admin pengguna, penerbit tiket SSO) sudah Laravel dan sudah teruji di produksi. Memindahkannya ke repo hub sebagian besar adalah salin, bukan tulis ulang, dan dengan database bersama tidak ada migrasi data. Bahasa lain berarti menulis ulang auth dari nol, yang paling berisiko dan paling tidak perlu.

Satu app Laravel melayani dua host lewat `Route::domain`:

- `hel.ink`: beranda HOP, daftar app, marketing (about, privacy, terms, help, contact, products), `/login` dan `/register` mengalihkan ke hub.hel.ink.
- `hub.hel.ink`: login, profil, keamanan, 2FA, hapus akun, ekspor data, admin pengguna, kelola token API, launcher ke setiap app, status layanan (watchdog), pengumuman (tempat "silakan cek dashboard hel.ink" dalam ToS mendapat bentuk nyata).

Komponen navigasi lintas app ("pill" atau sidebar) dibuat sekali sebagai paket Blade kecil (`hop/ui`) yang dipasang di hub, LinkHop, dan BioHop, membaca daftar app dari satu config, sehingga app baru cukup ditambahkan di satu tempat.

### Database bersama: aturan kepemilikan supaya tidak saling injak

Database bersama menghilangkan sinkronisasi pengguna, suspensi, dan hapus akun, dan itu alasan kuat memilihnya untuk tim satu orang. Yang harus dijaga adalah kepemilikan skema:

| Tabel | Pemilik migration |
| --- | --- |
| users, login_history, activity_log, api_tokens, sessions, cache, jobs, failed_jobs, password_reset_tokens | hub |
| links, link_clicks, folders, tags, comments, retired_slugs, seo_settings, domain_blacklist, ip_bans, ip_watchlist, abuse_* | LinkHop |
| bio_pages, bio_links, bio_clicks | BioHop |

Aturan:

- Setiap repo hanya menjalankan migration miliknya, dan memakai tabel pencatat migration sendiri (`migrations_hub`, `migrations_link`, `migrations_bio` lewat `database.migrations.table`), sehingga `migrate:rollback` di satu app tidak menyentuh app lain.
- Model `User` tetap ada di tiap app tetapi hanya baca; semua tulisan ke `users` lewat hub (UI) atau lewat API master.
- Abuse dan blacklist domain: tabelnya milik LinkHop, tetapi BioHop membacanya lewat paket bersama `hop/safety` (LinkSafety, blacklist, deteksi kata) supaya aturan keamanannya satu.
- Satu `APP_KEY` yang sama tidak diperlukan kalau login lintas app memakai tiket SSO (di bawah). Kalau nanti ingin sesi benar-benar satu cookie di `.hel.ink`, barulah `APP_KEY` dan nama cookie disamakan; jangan sekarang.

### Login lintas app: tiket SSO yang sudah ada, diterbitkan hub

Mekanisme PixelHop dipakai apa adanya: konsumen mengirim pengguna ke `hub.hel.ink/sso/{app}?state=...&return_to=...`, hub memeriksa sesi, menerbitkan tiket HMAC 2 menit, konsumen memverifikasi dan memanggil `Auth::loginUsingId($sub)` karena tabel users sama. Logout di hub mengirim pengguna ke endpoint logout tiap app secara berurutan (atau cukup sesi pendek per app). PixelHop hanya mengganti alamat penerbit dari hel.ink ke hub.hel.ink.

### API per app dan API master

- `link.hel.ink/api/v1` dan `bio.hel.ink/api/v1`: API produk, dokumentasi di app masing-masing.
- Token: tabel `api_tokens` milik hub, dibuat dan dicabut di hub.hel.ink, dengan kolom `scopes` (`link:*`, `bio:*`). Setiap app memvalidasi token dari tabel yang sama lewat paket bersama `hop/auth`.
- Rate limit: per token dan per IP di tiap app (sudah ada), ditambah batas harian per akun yang dihitung di tabel bersama supaya satu akun tidak menggandakan kuota dengan memakai dua app.
- Sanitasi: `hop/safety` dipakai semua app untuk URL, teks, dan HTML.
- Watchdog di hub: perintah terjadwal setiap menit memanggil `/up` tiap app dan satu endpoint uji fungsional (misalnya redirect satu link uji, render satu bio uji), mencatat ke tabel `service_checks`, menampilkan status di hub.hel.ink, dan mengirim peringatan lewat email atau Telegram kalau gagal tiga kali berturut. Tidak membangun gateway di depan API; itu akan menjadi titik gagal tunggal dan biaya tambahan.
- Pengalihan lama `hel.ink/api/*` 308 ke `link.hel.ink/api/*` selama 30 hari, sesuai kebijakan yang sama dengan short link.

### Bertahap atau sekali jadi: bertahap dalam pengerjaan, satu cutover

Dengan pengguna sedikit dan database bersama, cutover sekali jadi aman asalkan pengerjaannya berurutan dan diuji di subdomain staging dulu. Yang tidak boleh dilewati: Fase A di bawah dirilis lebih dulu di app yang sekarang, karena ia memulai jam 30 hari pemberitahuan dan membereskan `PUBLIC_URL` sebelum ada subdomain apa pun.

## 8.3 Rencana fase yang disesuaikan

### Fase A. Persiapan di repo yang ada, dirilis ke produksi sekarang (M)

- `PUBLIC_URL` terpisah dari `APP_URL`; semua URL publik (short link, bio, QR, og:url, sitemap) membacanya.
- Route baru `/l/{slug}`; `Link::short_url` menghasilkan `/l/`. Route lama `/{slug}` menjadi 301 ke `/l/{slug}`.
- Nama cookie sesi unik; `ReservedSlugs` disederhanakan.
- Pemberitahuan: halaman pengumuman di dashboard, email ke semua akun (sekali, plus pengingat di hari ke-23), teks ToS diperbarui, CHANGELOG.
- Bio dipindah ke paket Composer `hop/bio` di dalam repo (path repository), masih dipakai app ini. Kontrak ke inti: `ShortlinkCreator`, `UrlSafety`, `GeoLookup`, `UserAgentParser`.
- Kode keamanan bersama ditarik ke paket `hop/safety`; komponen navigasi ke `hop/ui`; validasi token dan klien SSO ke `hop/auth`. Paket-paket ini nanti dipublikasikan sebagai repo sendiri atau satu repo `hop-packages`.
- Rilis. Hari ini adalah hari ke-0 dari 30 hari.

### Fase B. Repo hub (hel.ink + hub.hel.ink) (L)

- Repo baru `hub` (atau repo hel.ink yang sekarang di-rename, lihat Fase E). Salin auth, profil, admin pengguna, token API, penerbit SSO, halaman marketing dari repo sekarang.
- Migration milik hub dengan tabel `migrations_hub`; migration tabel milik app lain tidak dibawa.
- Launcher, pengumuman, status layanan, watchdog.
- Uji di `hub-staging.hel.ink` dan `staging.hel.ink` terhadap database staging (salinan produksi).

### Fase C. Repo LinkHop (M)

- Fork repo sekarang, hapus bio, auth sebagai penerbit, admin pengguna, marketing. Jadi konsumen SSO dari `hop/auth`.
- `APP_URL=https://link.hel.ink`, `PUBLIC_URL=https://hel.ink`. Route publik hanya `/l/*` dan pengalihan lama. API di `link.hel.ink/api/v1`.
- Google OAuth tidak ada lagi di sini (pindah ke hub).
- Uji di `link-staging.hel.ink`.

### Fase D. Repo BioHop (M)

- App Laravel baru yang memasang `hop/bio`, `hop/auth`, `hop/safety`, `hop/ui`. Pembuatan short link dari blok bio memanggil `link.hel.ink/api/v1` dengan token layanan pengguna (dibuat otomatis oleh hub saat pertama dipakai, scope `link:create`).
- `APP_URL=https://bio.hel.ink`, `PUBLIC_URL=https://hel.ink`. Route publik `/b/*`. Admin bio, abuse scan bio, prune bio_clicks pindah ke sini.
- File upload bio dipindah ke disk milik BioHop (`rsync` folder `storage/app/public/bio*` saat cutover).
- Uji di `bio-staging.hel.ink`.

### Fase E. Cutover (S, satu jendela, dengan rencana mundur)

Urutan dalam satu jendela pemeliharaan singkat:

1. Mode pemeliharaan di app lama. Backup database dan folder storage.
2. Jalankan migration hub, LinkHop, BioHop (tiap app hanya miliknya) ke database produksi.
3. `rsync` upload bio ke BioHop.
4. Pasang nginx: upstream hub, linkhop, biohop; aturan di 8.2. Sertifikat untuk hub, link, bio (Cloudflare origin cert atau Let's Encrypt wildcard).
5. PixelHop: ganti `HELINK_SSO_URL` ke hub.hel.ink.
6. Google OAuth redirect URI ke hub.hel.ink; Turnstile tambah host.
7. Uji asap: beranda, login di hub, lompat ke LinkHop dan BioHop, buat link, buat bio, redirect `/l/`, redirect lama `/{slug}`, `/b/`, API dengan token, watchdog hijau.
8. Matikan mode pemeliharaan. Kirim email "migrasi selesai".

Rencana mundur: nginx kembali mengarah ke app lama (yang masih terpasang dan masih memahami `/l/` karena Fase A). Migration app baru hanya menambah tabel atau kolom, tidak menghapus, sehingga app lama tetap jalan. Penghapusan skema lama dilakukan paling cepat 30 hari kemudian.

### Fase F. Repo induk dan website (M, paralel dengan B sampai D)

- Repo `hel.ink` di GitHub menjadi induk: README daftar app (LinkHop, BioHop, PixelHop, hub), kegunaan, kelebihan dan kekurangan, cara pasang (docker-compose untuk satu server dengan nginx router), tautan ke tiap repo. Kode app lama dipindah ke repo LinkHop dengan riwayat git (`git filter-repo` atau fork lalu hapus).
- Website github.io: satu situs untuk semua app, dari `docs/` yang sudah ada, dengan halaman per app.
- Paket bersama (`hop/ui`, `hop/safety`, `hop/auth`, `hop/bio`) dipublikasikan agar `composer require` berjalan dari repo publik, atau satu monorepo paket dengan path repository di tiap app.

### Fase G. Setelah 30 hari (S)

- Hapus pengalihan `hel.ink/api/*` lama. Pengalihan `hel.ink/{slug}` disarankan dibiarkan.
- Hapus tabel dan kolom yang tidak lagi dipakai, hapus deployment app lama.

## 8.4 Yang berubah dari rekomendasi awal, dan mengapa saya setuju

- Akun langsung ke hub (bukan "tetap di app link dulu"): dengan database bersama, pemindahan auth adalah salin kode tanpa migrasi data, sehingga alasan utama menundanya hilang.
- Database bersama (bukan per app): untuk pengguna hitungan jari dan satu operator, sinkronisasi lintas app adalah pekerjaan yang tidak sepadan. Aturan kepemilikan migration di 8.2 adalah harga yang harus dibayar, dan itu murah.
- `/l/` untuk short link: menghilangkan seluruh masalah catch-all dan reserved slug, membuat router tiga baris. Satu-satunya keberatan saya adalah penghapusan pengalihan lama setelah 30 hari; biarkan hidup.

## 8.5 Hal yang masih perlu Anda putuskan sebelum Fase A dimulai

1. Nama paket dan vendor Composer: `hop/*` atau `helink/*`.
2. Email pemberitahuan dikirim dari domain mana dan apakah pengingat kedua diperlukan untuk pengguna yang sangat sedikit.
3. Pengalihan `hel.ink/{slug}` lama: dibiarkan permanen (saran) atau dihapus setelah 30 hari.
4. Apakah repo hel.ink yang sekarang di-rename menjadi `linkhop` (riwayat ikut) dan repo induk dibuat baru, atau sebaliknya.

---

# Bagian 9. Temuan server dan daftar persiapan (28 Oktober 2026, sore)

## 9.1 Temuan saat memeriksa server ini

- Mesin ini adalah server produksi. Produksi hel.ink dilayani nginx dari `/var/www/helink/shortlink-app/public` dengan PHP-FPM 8.3.
- Checkout produksi berasal dari repo **github.com/navi-crwn/LinkHop**, branch main, 172 commit, commit terakhir 28 Oktober (PR #44, "imperium-shield-tile"). `APP_NAME` di `.env` produksi sudah "LinkHop".
- Repo **navi-crwn/hel.ink** (tempat dokumen ini ditulis) adalah leluhur repo LinkHop: HEAD-nya (85fc92c, 16 Oktober) ada di riwayat LinkHop, dan LinkHop sudah 43 commit di depannya. Artinya repo hel.ink sudah tidak dipakai untuk kode, dan **Fase A harus dikerjakan di repo LinkHop**, bukan di sini.
- Keputusan 8.5 nomor 4 dengan demikian sudah terjawab: repo LinkHop sudah ada dan hidup; repo hel.ink tinggal dikosongkan menjadi repo induk.
- Subdomain yang sudah ada di nginx: `p.hel.ink` (PixelHop, proses `pixelhop` di port 8080), `imperium.hel.ink` (port 8090 atau 8100, sertifikat Let's Encrypt), `note.hel.ink`. Nama "Imperium" muncul di 5 commit LinkHop terakhir; posisinya dalam keluarga HOP perlu dijelaskan di README induk nanti.
- Sertifikat `hel.ink` di `/etc/ssl/certs/hel.ink.crt` adalah sertifikat origin dengan CN `hel.ink` saja, tanpa SAN, kedaluwarsa 14 Desember 2026. Ia **tidak** mencakup `hub.`, `link.`, `bio.`; perlu sertifikat origin Cloudflare baru untuk `hel.ink` dan `*.hel.ink`.
- Tidak ada deploy otomatis: CI hanya menjalankan test dan e2e. Deploy adalah `git pull` di server. Docker, Composer, Node, PHP 8.3/8.4/8.5 tersedia.
- Email produksi lewat SMTP Gmail (lihat `.env.example`); queue dan session memakai database.

## 9.2 Yang perlu Anda siapkan (hanya yang tidak bisa saya lakukan sendiri)

### Cloudflare (gratis)

1. DNS, semua tipe A ke IP server ini, proxied (awan oranye):
   `hub`, `link`, `bio`, dan untuk uji: `hub-staging`, `link-staging`, `bio-staging`, `staging`.
   Kalau wildcard `*.hel.ink` sudah ada, cukup pastikan ia proxied.
2. SSL/TLS → Origin Server → Create Certificate untuk `hel.ink` dan `*.hel.ink`, masa 15 tahun. Simpan `.pem` dan key ke server (misalnya `/etc/ssl/certs/hel.ink-origin.crt` dan `/etc/ssl/private/hel.ink-origin.key`) dan beri tahu saya path-nya. Mode SSL tetap Full (strict).
3. Turnstile → widget yang dipakai hel.ink → tambahkan hostname `hub.hel.ink`, `link.hel.ink`, `bio.hel.ink`, dan tiga host staging.
4. Tidak perlu Worker, Rules, atau paket berbayar.

### Google Cloud Console (OAuth)

5. Di client OAuth yang dipakai hel.ink, tambahkan Authorized redirect URI `https://hub.hel.ink/auth/google/callback` dan `https://hub-staging.hel.ink/auth/google/callback`. Jangan hapus yang lama sampai cutover selesai.

### GitHub

6. Repo baru, kosong, publik atau privat sesuai selera: `biohop` dan `hop-packages` (isi: `hop/ui`, `hop/safety`, `hop/auth`, `hop/bio`). Repo `hub` bisa memakai repo `hel.ink` yang sekarang, karena kodenya akan diganti total menjadi hub + dokumen induk, atau repo baru `hub` kalau Anda ingin `hel.ink` benar-benar hanya dokumen. Pilih salah satu dan beri tahu saya.
7. GitHub Pages untuk repo induk: Settings → Pages → source `docs/` (sudah begitu untuk hel.ink).
8. Akses push untuk saya ke repo LinkHop, biohop, hop-packages, dan repo induk, dengan identitas git yang sama dengan sekarang (navi-crwn).

### Server ini

9. Direktori untuk app baru dan izin tulis: `/var/www/helink/hub`, `/var/www/helink/biohop`, dan `/var/www/helink/*-staging`. Saya bisa membuatnya kalau diizinkan `sudo`, atau Anda buat dengan pemilik yang sama dengan `shortlink-app`.
10. Izin `sudo` untuk nginx (`/etc/nginx/sites-available`, `nginx -t`, `systemctl reload nginx`) dan PHP-FPM pool. Kalau tidak ingin memberi sudo, saya tulis file konfigurasinya dan Anda yang memasang.
11. Database: satu user DB yang dipakai bersama (bisa yang sekarang), plus satu database `helink_staging` sebagai salinan produksi untuk uji.
12. Rahasia untuk `.env` app baru: `HOP_SSO_SECRET` (32+ karakter, sama di semua app), kredensial SMTP, kunci Turnstile, Google client id dan secret, `SAFE_BROWSING_KEY` kalau dipakai. Boleh dikirim dengan cara apa pun yang Anda anggap aman; jangan tulis di chat kalau chat ini tersimpan di tempat lain.

### PixelHop

13. Path kode dan file `.env` PixelHop (proses di port 8080), supaya saat cutover alamat penerbit SSO diganti dari `hel.ink` ke `hub.hel.ink`.

### Keputusan kecil yang tersisa

14. Nama vendor Composer: `hop` (saran, pendek) atau `helink`.
15. Alamat pengirim email pemberitahuan: tetap alamat Gmail yang sekarang, atau alamat `@hel.ink` lewat Cloudflare Email Routing (gratis untuk terima, kirim tetap lewat SMTP yang ada).
16. Pengalihan `hel.ink/{slug}` lama: dihapus setelah 30 hari (keputusan Anda) atau dibiarkan.
17. Status Imperium dan note.hel.ink dalam keluarga HOP: ikut masuk launcher dan README induk, atau tidak.

## 9.3 Yang saya kerjakan tanpa perlu Anda siapkan

Fase A di repo LinkHop (branch dan PR), paket-paket `hop/*`, repo BioHop, kode hub, konfigurasi nginx dan PHP-FPM (dipasang kalau ada sudo, kalau tidak sebagai file untuk Anda), perintah migrasi, skrip cutover dan rollback, email pemberitahuan, teks ToS, README induk dan website github.io.

---

# Bagian 10. Jawaban 28 Oktober (malam) dan koreksi temuan repo

## 10.1 Koreksi: repo dan remote

- Repo GitHub lama `navi-crwn/hel.ink` sudah di-rename menjadi `navi-crwn/LinkHop`. Repo baru `navi-crwn/HEL.ink` (hub, kosong) mengambil nama lama, dan `navi-crwn/BioHop` (kosong) sudah dibuat.
- Akibatnya checkout lokal `/home/carawin/hel.ink` masih menunjuk `hel.ink.git`, yang kini adalah repo HEL.ink yang kosong, sehingga `git fetch` tidak pernah membawa 43 commit terbaru. Remote `origin` sudah diarahkan ke `LinkHop.git` pada 28 Oktober; `main` lokal tinggal di-fast-forward.
- Rencana repo final: `LinkHop` (dibersihkan, hanya shortener), `BioHop` (bio), `HEL.ink` (hub + dokumen induk + website github.io), `hop-packages` (belum dibuat; atau paket ditaruh sebagai folder `packages/` di HEL.ink dan dipakai lewat path repository dulu).

## 10.2 Jawaban pemilik

| Hal | Jawaban |
| --- | --- |
| Minggu ini | Hanya Fase A |
| Vendor Composer | `hop` |
| Email pemberitahuan | Satu alamat `@hel.ink` (MX sudah di Spacemail, SPF dan DKIM sudah ada); SMTP-nya perlu diisi di `.env` |
| Tata letak server | Satu folder bersama: `/var/www/helink/linkhop`, `/var/www/helink/biohop`, `/var/www/helink/hub`. `shortlink-app` di-rename saat cutover (root nginx ikut) |
| Imperium / citadel | Tempat login admin pemilik (Tailscale saja, `citadel.hel.ink` lewat Cloudflare Tunnel). Bukan bagian launcher publik; admin panel app bisa menyusul ke sana nanti |
| Google OAuth | Tambahkan redirect URI baru di samping yang lama; yang lama dihapus setelah cutover |

## 10.3 DNS Cloudflare (dari ekspor zona 28 Oktober)

Sudah ada dan proxied: `hel.ink`, `www`, `hub`, `link`, `bio`, `p`, `note`, `remote`, dan wildcard `*.hel.ink` (mencakup semua host staging). `imperium` menunjuk IP Tailscale tanpa proxy; `citadel` CNAME ke Cloudflare Tunnel. Tidak ada DNS yang perlu ditambah.

Masih perlu: sertifikat origin Cloudflare untuk `hel.ink` dan `*.hel.ink` (yang terpasang hanya `hel.ink`, habis 14 Desember 2026), hostname baru di widget Turnstile, dan redirect URI Google.

## 10.4 Nasib `hel.ink/{slug}` lama, dengan contoh

Link `hel.ink/promo` hari ini adalah baris `links.slug = promo`. Setelah Fase A:

1. Dashboard, QR baru, dan API menampilkan `hel.ink/l/promo`. Baris datanya sama; klik dan statistik lanjut.
2. Orang yang masih membuka `hel.ink/promo` dialihkan 301 ke `hel.ink/l/promo`, lalu ke tujuan. Dihitung sebagai klik biasa.
3. Hari ke-30: aturan pengalihan dicabut. `hel.ink/promo` menjadi 404 di hub. `hel.ink/l/promo` tetap hidup.
4. Email ke setiap pemilik link di hari ke-0 dan ke-23, berisi daftar link mereka dalam bentuk baru.

Tidak ada baris yang dihapus dari database; "menghapus link lama" hanya berarti mencabut aturan nginx di langkah 3.

## 10.5 Cara menambah redirect URI Google OAuth

1. Buka console.cloud.google.com, pilih project yang dipakai hel.ink.
2. Menu APIs & Services → Credentials → klik OAuth 2.0 Client ID yang dipakai (client id-nya sama dengan `GOOGLE_CLIENT_ID` di `.env` produksi).
3. Di Authorized redirect URIs klik Add URI, tambahkan `https://hub.hel.ink/auth/google/callback` dan `https://hub-staging.hel.ink/auth/google/callback`. Biarkan `https://hel.ink/auth/google/callback` tetap ada.
4. Di Authorized JavaScript origins tambahkan `https://hub.hel.ink` kalau ada entri untuk `https://hel.ink`.
5. Save. Perubahan aktif dalam beberapa menit sampai beberapa jam.
6. Kalau layar persetujuan (OAuth consent screen) berstatus Testing, pastikan akun uji terdaftar. Kalau sudah Production, tidak ada yang perlu diubah.
7. Setelah cutover berjalan stabil, hapus URI `https://hel.ink/...` yang lama. Tidak perlu client id baru; satu client boleh punya banyak redirect URI.
