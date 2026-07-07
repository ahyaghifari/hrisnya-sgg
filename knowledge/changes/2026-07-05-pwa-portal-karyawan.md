# Perubahan: PWA Portal Karyawan `/pwa` (2026-07-05)

Rencana awal & diskusi lengkap: [`planning/2026-07-05-pwa-portal-karyawan.md`](../../planning/2026-07-05-pwa-portal-karyawan.md) — baca itu dulu kalau mau lanjut sesi berikutnya, dokumen ini cuma rekap "apa yang sudah jadi kode".

## Ringkasan

Dibuat sub-route baru `/pwa/home` (shell mobile, installable sebagai PWA) — **bukan** membuat seluruh sistem jadi PWA. Sisi lain (`/admin`, `/dashboard`, `/karyawanku`, dll) tidak berubah sama sekali. Stack tetap Livewire + Blade (bukan Vue/SPA) — instalabilitas PWA tidak butuh SPA framework, dan codebase ini sengaja konsisten tanpa satu.

**Status akhir sesi**: shell mobile + halaman Beranda + instalabilitas (manifest/SW/ikon) ✅ selesai dan bisa dicoba. Ganti password, push notification, dan integrasi presensi Hikvision **belum dikerjakan** — itu next steps.

## 1. Kenapa route-nya `/pwa/home`, bukan `/pwa`

Awalnya route Livewire didaftarkan di `/pwa` persis. Begitu folder statis `public/pwa/` dibuat (buat manifest/ikon/SW), muncul **404 dari web server, bukan dari Laravel** — `.htaccess` bawaan Laravel punya `RewriteCond %{REQUEST_FILENAME} !-d` yang melewatkan request ke rewrite `index.php` kalau path itu cocok dengan direktori nyata. Karena `public/pwa/` sekarang ada secara fisik, request ke `/pwa` ditangani langsung oleh web server (coba cari index di folder itu, gagal) — tidak pernah sampai ke Laravel.

**Perbaikan**: route Livewire dipindah ke `/pwa/home` (nama route tetap `pwa.home`). Path bare `/pwa` sekarang "milik" folder statis saja; halaman aplikasinya di sub-path yang tidak bentrok. Kalau ke depannya mau nambah static asset PWA lagi, ingat aturan ini: **jangan pernah nama folder statis persis sama dengan path route Livewire**.

## 2. Struktur halaman & layout

File baru:
- `app/Livewire/Pwa/Home.php` + `resources/views/livewire/pwa/home.blade.php` — halaman Beranda.
- `resources/views/components/pwa-layout.blade.php` — layout terpisah dari `components/layout.blade.php` (yang itu buat sidebar/topnav desktop). Tidak ada `<x-nav/>`/`<x-footer/>` desktop di sini.
- `resources/views/components/pwa-app-bar.blade.php` — app bar custom (bukan reuse `.topnav` desktop): solid navy `#042c6c`, logo badge + judul halaman dinamis (`$title`, prop yang sama dipakai `<title>` tab browser) di kiri, ikon bell dekoratif + avatar inisial (link ke Akun) di kanan. `padding-top: env(safe-area-inset-top)` buat device dengan notch.
- `resources/views/components/pwa-bottom-nav.blade.php` — 4 tab: **Beranda** (aktif, route `pwa.home`), **Presensi** (anchor ke `#absen-section` di halaman Beranda — belum ada halaman sendiri), **Pengajuan** & **Akun** (masih mengarah ke route desktop existing `my-attendance-requests`/`data-diri` — belum ada versi mobile sendiri, jadi begitu diklik user akan lihat tampilan desktop, bukan shell PWA).

Route (`routes/web.php`), di dalam grup middleware `auth`+`portal.access` yang sama dengan halaman portal lain:
```php
Route::get('/pwa/home', PwaHome::class)->name('pwa.home');
```
Terdaftar juga di `config/portal-pages.php` (key `pwa.home`, permission null, guard null — cukup login, sama seperti `/dashboard`).

**Login**: tidak perlu route `/pwa/login` terpisah. Middleware `auth` bawaan otomatis redirect ke `/login` kalau belum login, dan `LoginController::store()` sudah pakai `redirect()->intended(route('dashboard'))` — Laravel otomatis simpan URL tujuan asal (`/pwa/home`) dan balik ke situ setelah login berhasil.

## 3. Halaman Beranda — sengaja TIDAK share partial dengan `/dashboard`

Awalnya sempat diekstrak jadi partial bersama (`resources/views/livewire/partials/home-cards.blade.php`, masih dipakai `dashboard.blade.php`), tapi begitu user konfirmasi kontennya akan terus beda, `pwa/home.blade.php` dipisah jadi **copy independen** — supaya bebas diubah tanpa risiko merusak `/dashboard` desktop.

Isi halaman Beranda `/pwa` (urutan dari atas):
1. `<livewire:my-attendance-status-card />` (reuse, tidak berubah).
2. **Welcome card** — sama seperti Dashboard, tapi bagian **"Aksi Cepat"** (tombol Cuti/Izin/Lembur/Struktur Organisasi) **dihapus** atas permintaan user.
3. **Card Presensi** — placeholder statis (`id="absen-section"`, ditarget bottom-nav tab Presensi). **Tombol "Absen Pulang" dihapus** — dikonfirmasi user: presensi nanti murni ditarik dari sinkronisasi Hikvision (lihat §6), tidak ada aksi manual karyawan, jadi tombol itu tidak relevan. Pakai class baru `.absen-card-compact` (padding & `min-height` dikecilkan) supaya card tidak makan tempat — versi desktop tetap pakai `.absen-card` polos.
4. **2 card saldo cuti** (Tahunan & Sakit) — `LeaveBalanceCard` dapat prop baru **`compact`** (default `false`, dipakai cuma di sini): padding/font/icon lebih kecil, progress bar & badge tahun disembunyikan. Dipakai `:compact="true"` cuma di `/pwa`, tempat lain (Dashboard, Karyawanku) tidak berubah.
5. **Task Saya** — list rows (`.pwa-list`/`.pwa-list-row`, hairline divider + chevron kanan), **bukan** card-in-card seperti versi desktop — pola native mobile list (mirip menu Settings iOS/Android).
6. **Kursus Saya** — horizontal scroll carousel (`.pwa-scroll-row` + `.pwa-course-chip`, `scroll-snap-type:x`), bukan list vertikal — pola carousel umum di app mobile. **Sempat pakai trik bleed edge-to-edge (`margin:0 -16px`)** supaya carousel nempel ke tepi layar, tapi **dibatalkan** — trik itu cuma cocok dengan padding mobile (16px), begitu breakpoint desktop (padding 40px) atau di-preview di browser lebar, elemennya lepas dari batas container secara tidak konsisten. Sekarang carousel tetap di dalam padding halaman seperti elemen lain.
7. Stat presensi (`attendance-stat-cards`), "Butuh Persetujuan Anda" (`pending-approvals-card`), dan "Riwayat Pengajuan Terbaru" (`recent-requests-card`) — **dihapus semua** dari `/pwa` atas permintaan user (masih ada di `/dashboard`).

Task Saya & Kursus Saya di `/pwa` maupun `/dashboard` **sama-sama masih placeholder statis** — belum ada modul Task/LMS "milik sendiri" yang terhubung ke data asli.

CSS baru (semua di `resources/css/app.css`, tidak menyentuh class shared yang sudah ada):
- `.absen-card-compact`, `.pwa-bento` (gap lebih lebar khusus `/pwa`, class tambahan di atas `.bento` supaya tidak ganggu Dashboard).
- `.pwa-app-bar*`, `.pwa-bottom-nav`/`.pwa-nav-item`, `.pwa-content` (padding-bottom `calc(96px + env(safe-area-inset-bottom))` — dihitung dinamis biar konsisten sama tinggi bottom-nav + safe-area, bukan angka tebakan tetap).
- `.pwa-section-title/-link`, `.pwa-list`/`.pwa-list-row`, `.pwa-scroll-row`/`.pwa-course-chip`.

## 4. Instalabilitas (manifest + service worker + ikon)

File baru:
- `public/pwa/manifest.json` — `start_url: /pwa/home`, `scope: /pwa/`, `theme_color: #042c6c`, referensi 3 ikon.
- `public/pwa/icons/icon-192.png`, `icon-512.png`, `icon-maskable-512.png` — **placeholder**, di-generate pakai PHP GD (`scripts/generate-pwa-icons.php`, one-off script, tidak dipanggil dari mana pun di app): background navy + huruf "S", font `Arial Bold` bawaan Windows (`C:/Windows/Fonts/arialbd.ttf`). Ganti kalau nanti ada logo resmi.
- `public/pwa/sw.js` — service worker **minimal**, cuma penuhi syarat instalabilitas (register + listener `fetch`), **belum ada cache/offline strategy** (ditandai komentar `ponytail:` di file itu sendiri).
- `pwa-layout.blade.php` ditambah: `<link rel="manifest">`, meta `theme-color`, meta `apple-mobile-web-app-*` (buat iOS, yang tidak baca manifest sepenuhnya), dan script register SW (`scope: '/pwa/'`).

Semua file statis ini di-serve langsung oleh web server (bukan lewat routing Laravel) — makanya penting menjaga jangan ada nama folder yang collide dengan route (lihat §1).

## 5. Belum dikerjakan / next steps (urutan prioritas)

1. **Ganti password** — reuse logic `AuthController::changePassword` (API, guard `sanctum`), adaptasi guard-nya jadi terima `User $user` dari `Auth::user()` (guard `web`), taruh di halaman Akun `/pwa`. Paling cepat dikerjakan dari sisa daftar ini.
2. **Presensi Hikvision** — masih blocker riset besar, belum dianalisis detail. Yang perlu digali: jenis akses ke device (konek langsung ke DB on-prem software Hikvision, kemungkinan SQL Server, vs ISAPI/event push), koneksi DB kedua di `config/database.php` (read-only!), mapping NIP↔employee ID Hikvision, mekanisme & frekuensi sync (scheduled command vs queue listener), dan apakah server hosting bisa reach jaringan alat tersebut (bisa jadi blocker infrastruktur, bukan cuma kode). Detail lengkap ada di §D `planning/2026-07-05-pwa-portal-karyawan.md`.
3. **Push notification** (approve/reject pengajuan absensi ke requester) — butuh 1 dependency baru `laravel-notification-channels/webpush` (wrap `minishlink/web-push`, perlu buat signing VAPID). Perlu: generate VAPID key pair, migration `push_subscriptions`, endpoint simpan subscription, dan **hook baru di `ApprovalFlowService`** — saat ini `NotificationService` cuma kirim notifikasi ke approver saat submit, belum ada notifikasi balik ke requester saat status berubah sama sekali.
4. **Halaman Presensi & Pengajuan versi mobile sendiri** — sekarang tab bottom-nav "Pengajuan"/"Akun" masih lempar ke halaman desktop existing (fungsional, tapi keluar dari shell PWA, kehilangan bottom-nav/app-bar). Perlu dibikin versi Livewire yang pakai `pwa-layout` juga, reuse service/component yang sama seperti dijelaskan di planning §E.
5. Ikon & branding resmi (bukan placeholder "S" navy).

## Catatan proses

- User berulang kali minta iterasi kecil cepat (perkecil card, hapus tombol, ubah gaya jadi "lebih mobile", perbaiki spacing) — semua perubahan itu di-scope ketat ke file-file `/pwa` saja, tidak menyentuh `/dashboard` desktop kecuali ada instruksi eksplisit.
- **Jangan jalankan `npm run build`/dev server** — user selalu jalankan `npm run watch` sendiri.
- **Jangan jalankan/usulkan `php artisan test`** kecuali diminta eksplisit.
- Belum ada `php artisan test` dijalankan buat perubahan ini (murni visual/routing, tidak ada logic baru yang butuh test unit — satu-satunya logic baru adalah prop `compact` di `LeaveBalanceCard`, presentational branching, bukan business logic).
