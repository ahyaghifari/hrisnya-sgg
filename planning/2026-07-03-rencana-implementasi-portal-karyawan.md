# Rencana Implementasi Portal Karyawan — Tahap 1 (2026-07-03)

## Context

Setelah eksplorasi desain (demo HTML statis: login, dashboard karyawan, 4 dashboard manajerial, form cuti/izin/lembur) dan sebagian perbaikan backend (company-scoping, rename role Admin/Super Admin — lihat `planning/2026-07-03-perbaikan-backend-scoping-akses.md`), sekarang masuk ke implementasi sungguhan di Laravel (bukan HTML statis lagi). Disepakati urutan pengerjaan: **Login → Dashboard Karyawan → Attendance** — bukan langsung semua fitur/semua level dashboard sekaligus, supaya tiap tahap bisa diuji sebelum lanjut.

Alasan urutan ini:
1. **Login lebih dulu** karena semua fitur lain bergantung ke `Auth::user()` (identitas, company, role) untuk scoping data.
2. **Dashboard level Karyawan dulu** (bukan Manager/Direktur/Supervisor) — karena itu satu-satunya level yang datanya sudah pasti benar sekarang. Manager/Direktur butuh Fase 1 (company-scoping) selesai total dulu, Supervisor butuh Fase 3 (hierarki) yang belum dibangun. Lihat `planning/2026-07-03-arsitektur-dashboard-per-role.md` untuk model Level×Domain-nya.
3. **Attendance (cuti/izin/lembur) terakhir** — fitur inti alasan portal ini dibangun, tapi butuh login+dashboard shell dulu sebagai kerangka.

## Tech Stack & Keputusan PWA

Pakai **Blade + Tailwind + Alpine.js + Livewire**, konsisten dengan pola yang sudah dipakai Filament di codebase ini. Tapi ada 1 pembagian penting terkait rencana PWA offline di masa depan (khusus fitur attendance):

- **Login, navigasi, dashboard shell** — bebas pakai Livewire biasa (`wire:submit`, dst), tidak butuh offline.
- **Khusus fitur Attendance (form cuti/izin/lembur, list pengajuan, detail pengajuan)** — interaksinya HARUS pakai **Alpine.js + `fetch()` langsung ke endpoint JSON di `routes/api.php`** (endpoint yang sama persis dipakai mobile app), BUKAN `wire:submit`/komponen Livewire. Alasannya: Livewire round-trip ke server tiap interaksi, kalau nanti dipasangi service worker untuk offline, Livewire akan mati total saat tidak ada koneksi. Kalau interaksinya sudah lewat JSON API dari awal, nanti gampang ditambah cache (Cache API untuk GET) dan queue submit (IndexedDB/Background Sync API untuk POST) TANPA perlu menulis ulang form-nya dari nol.
- **PWA installable (murah, kerjakan sekarang)**: tambah `public/manifest.json` + service worker dasar (cache-first untuk asset statis, network-first untuk GET API) — bikin portal bisa di-"Add to Home Screen" dan berasa seperti app. Ini independen dari pilihan Livewire/Alpine, aman dikerjakan kapan saja.
- **PWA offline beneran (submit form saat tanpa koneksi, auto-sync nanti)** — **DITUNDA**, jangan dibangun di Tahap 1. Baru dikerjakan kalau requirement-nya sudah konkret (skenario pemakaian offline yang jelas), karena butuh desain queue/sync yang lebih matang. Karena fondasi di atas (fetch ke JSON API) sudah disiapkan dari awal, penambahan nanti sifatnya aditif — tidak perlu migrasi ke Vue/Inertia kalau ternyata belum diperlukan.

**Koreksi saat eksekusi Tahap 1b**: Alpine.js ternyata tidak pernah benar-benar dipakai di file demo manapun (semua masih vanilla JS polos) dan belum ter-install (`npm ls alpinejs` kosong). Daripada nambah dependency baru untuk sesuatu yang vanilla JS + helper `apiFetch` kecil (`resources/js/api.js`) sudah cukup, widget dashboard ditulis pakai vanilla JS — konsisten dengan semua demo yang sudah ada. Alpine bisa dipertimbangkan lagi nanti kalau interaksinya makin kompleks (Tahap 1c form cuti/izin/lembur, dsb).

## Tahap 1b — Dashboard Karyawan ✅ SELESAI (2026-07-03)

Diimplementasikan: `app/Http/Controllers/Portal/DashboardController.php`, `resources/views/portal/dashboard.blade.php` (welcome card server-rendered, card Absensi Hari Ini jadi **placeholder statis** karena `attendance_records` ternyata tidak punya `user_id` terisi & tidak ada endpoint clock-in/out sama sekali — keputusan user: tetap tampil visual tapi jujur "Belum terintegrasi otomatis", tombol dinonaktifkan), saldo cuti & riwayat pengajuan di-fetch dari API yang sudah ada.

**Temuan & fix arsitektur penting**: endpoint `/api/attendance/*` pakai `auth:sanctum` yang didesain buat Bearer token (mobile). Supaya `fetch()` dari portal (session-based) bisa lolos autentikasi pakai cookie session (bukan token), diaktifkan `$middleware->statefulApi()` di `bootstrap/app.php`. Sempat ketemu bug tersembunyi: `$middleware->group('api', [...])` yang sudah ada di `bootstrap/app.php` ternyata **mengganti seluruh isi grup middleware `api`** (bukan menggabung), jadi diam-diam menghapus efek `statefulApi()` — diperbaiki jadi `$middleware->appendToGroup('api', [...])`. Sudah diverifikasi lewat curl: fetch pakai session cookie → 200 OK; mobile app pakai Bearer token → tetap 200 OK (tidak ada regresi).

Sekalian ditambahkan test untuk endpoint attendance yang sudah lama ada tapi belum ada test-nya sama sekali (`tests/Feature/Api/AttendanceApiTest.php`), plus `tests/Feature/Portal/DashboardTest.php`. Total test project sekarang 24 (semua lolos, SQLite in-memory).

## Refactor — Layout Komponen + Konsolidasi CSS ✅ SELESAI (2026-07-03)

Dashboard di-refactor supaya sama persis dengan demo (nav + footer, yang sebelumnya belum ada) dan CSS custom dipindah dari inline `<style>` per-halaman ke `resources/css/app.css` (1 sumber, dipakai lintas halaman portal). Blade dipecah jadi komponen: `x-portal.layout` (shell halaman), `x-portal.nav` (topnav — link "Admin Panel" cuma tampil kalau `hasRole(['Admin','Super Admin'])`, logout dipindah jadi dropdown di avatar), `x-portal.footer`. `dashboard.blade.php` sekarang jauh lebih ringkas, cuma isi bento + script fetch di dalam `<x-portal.layout>`.

Nav belum dibuatkan konten dinamis penuh per Level×Domain (masih hardcode link Karyawan-level: Dashboard/Pengajuan/Riwayat) — link Pengajuan & Riwayat masih arah ke `#` karena rute Tahap 1c belum ada, akan disambungkan begitu form/list dibangun. Struktur komponen sudah siap diperluas kalau nanti Manager/Direktur/Supervisor butuh nav berbeda.

Test bertambah jadi 27 (3 baru: render nav/footer, Admin Panel disembunyikan untuk karyawan biasa, muncul untuk role Admin). Semua lolos + diverifikasi manual via curl (nav, footer, Admin Panel conditional).

## Tahap 1a — Login ✅ SELESAI (2026-07-03)

Diimplementasikan: `app/Http/Controllers/Auth/LoginController.php`, `resources/views/auth/login.blade.php` (Tailwind utility langsung, bukan CSS class custom, sesuai keputusan `@tailwindcss/forms`), route `login`/`logout`/`dashboard` (stub) di `routes/web.php`, `/` diubah redirect ke `/login`, `canAccessPanel()` dibatasi ke role `Admin`/`Super Admin`.

Sudah diverifikasi end-to-end via curl (session cookie + CSRF): login gagal → pesan error + email tetap terisi; login benar → redirect `/dashboard`; logout → session invalid, `/dashboard` redirect balik ke `/login`; karyawan biasa akses `/admin` → 403; Super Admin akses `/admin` → 200; rate limit `throttle:5,1` kena di percobaan ke-6.

Belum dikerjakan (sengaja, di luar scope tahap ini): forgot/reset/change password.

**Kesepakatan baru soal testing**: mulai sekarang tiap "paket" fitur (Login, Dashboard, Attendance, dst — bukan per Resource/model kecil) wajib disertai unit/feature test sebelum dianggap selesai. Login sudah dapat `tests/Feature/Auth/LoginTest.php` (12 test: render halaman, login sukses/gagal, guest middleware, logout, proteksi dashboard, rate limit, `canAccessPanel()` per role). Ditemukan & diperbaiki juga: `phpunit/phpunit` ternyata belum ter-install di `vendor/` (sudah `composer install` ulang), dan migration `2026_03_15_135856_create_extra_hour_allowance_rules_table.php` pakai SQL mentah khusus MySQL (`SET FOREIGN_KEY_CHECKS`) yang bikin SEMUA test berbasis `RefreshDatabase` gagal di SQLite testing DB — sudah dibungkus pengecekan driver supaya tetap jalan sama di MySQL tapi tidak error di SQLite.

## Tahap 1a — Login (detail rencana awal)

- Reuse auth backend yang sudah ada (`app/Services/Auth/AuthService.php`, `app/Http/Controllers/Api/AuthController.php`) — itu untuk API/mobile (Sanctum token). Portal web pakai **session-based login** (guard `web`, sama seperti Filament admin), BUKAN Sanctum, supaya konsisten dengan pola Blade/Livewire yang sudah disepakati untuk portal ini (lihat catatan arsitektur di `planning/web-attendance/PLANNING.md`).
- View login ikuti gaya `planning/web-attendance/demo/login.html` (neumorphism + bento, brand navy/hijau/magenta).
- **Sekalian benerin `canAccessPanel()`** di `app/Models/User.php` (saat ini selalu `true` untuk siapa saja) — restrict jadi cuma role `Admin`/`Super Admin` yang boleh masuk `/admin`, sesuai kesepakatan Filament cuma untuk master data.
- **Ubah `routes/web.php`**: `/` yang sekarang redirect ke `/admin/login` diubah jadi redirect ke `/login` (portal), sebagai pintu masuk utama untuk karyawan. Admin/Super Admin tetap bisa akses `/admin` langsung via URL.
- Setelah login sukses → redirect ke `/dashboard`.

## Tahap 1b — Dashboard (Level Karyawan)

- 1 route `/dashboard`, render berdasarkan Level+Domain user login (untuk tahap ini fokus render Level Karyawan dulu — Manager/Direktur/Supervisor menyusul).
- Konten mengikuti `planning/web-attendance/demo/dashboard.html`: welcome card, card absensi (hijau, terpisah), saldo cuti/sakit (neuro), stat pending/disetujui, riwayat pengajuan terbaru.
- Data absensi & saldo cuti: sambungkan ke backend yang sudah ada (`AttendanceRecords`, `LeaveUserBalances`, `AttendanceRequestController` API) — bukan data dummy lagi. Widget absensi & saldo cuti di dashboard ini termasuk "domain attendance", jadi ambil datanya juga lewat `fetch()` ke JSON API (bukan Livewire), konsisten dengan keputusan tech stack di atas — bagian dashboard lain (welcome card, nav) tetap boleh Livewire.

## Koreksi Final Tech Stack (2026-07-03, setelah Tahap 1b jalan)

Klarifikasi dari user: **dashboard BUKAN bagian PWA** — cuma fitur pengajuan attendance (submit/list/detail cuti-izin-lembur, Tahap 1c) yang akan jadi PWA dan pantas pakai fetch ke JSON API. Semua halaman lain (termasuk dashboard) pakai **Blade + Livewire biasa, server-rendered, tanpa Alpine.js** kecuali benar-benar perlu.

Dashboard yang tadinya pakai vanilla JS + `window.apiFetch()` untuk saldo cuti & riwayat pengajuan sudah **diganti jadi 2 komponen Livewire** (`App\Livewire\LeaveBalanceCard`, `App\Livewire\RecentRequestsCard`) yang query data langsung server-side lewat `AttendaceReqService` yang sudah ada (bukan manggil API sendiri via HTTP). Halaman dashboard sendiri sekarang di-routing langsung ke Livewire full-page component (`App\Livewire\Dashboard` dengan atribut `#[Layout('components.layout')]`), BUKAN lewat Controller lagi — navigasi antar halaman pakai `wire:navigate` di link nav supaya berasa SPA tanpa full reload.

**Sekaligus rename**: istilah "Portal" dihapus dari struktur kode (ini bukan sub-portal, ini aplikasi utama karyawan) — `App\Livewire\Portal\*` → `App\Livewire\*` (flat, konsisten sama `OrganizationChartWidget` yang sudah ada), `resources/views/components/portal/*` → `resources/views/components/*` (`<x-portal.nav>` → `<x-nav>`, dst), class CSS `body.portal` → `body.site`.

**Tidak dihapus/tidak berubah**: `resources/js/api.js` (`window.apiFetch`), `statefulApi()` di `bootstrap/app.php`, `tests/Feature/Api/AttendanceApiTest.php` — semua tetap dipakai di Tahap 1c nanti (form cuti/izin/lembur, yang memang PWA-designated). Endpoint `getMasterAttendanceReqTypes()` di `AttendanceReqRepository`/`AttendaceReqService` dapat parameter opsional `$companyId` (backward-compatible, endpoint API existing tidak berubah perilaku) supaya bisa dipanggil dari konteks guard `web` (Livewire) tanpa bergantung `auth('sanctum')`.

Test bertambah jadi 29 (2 baru: assert angka saldo cuti & baris riwayat pengajuan muncul di HTML awal — dibuktikan lewat curl yang TIDAK PERNAH menjalankan JS, jadi ini bukti nyata server-rendered, bukan cuma asumsi).

## Penyamaan Struktur Dashboard dengan Demo ✅ SELESAI (2026-07-03)

User cek ulang hasil Tahap 1b vs demo asli (`planning/web-attendance/demo/dashboard.html`) dan menemukan beberapa elemen hilang saat refactor: 2 stat card terpisah (Menunggu Persetujuan/Disetujui Bulan Ini), tombol "Aksi Cepat" di welcome card, icon-box + badge tahun di card saldo cuti, dan link "Lihat semua" di riwayat pengajuan. Semua dikembalikan persis seperti demo, tanpa dikurangi/ditambah:

- Komponen baru `App\Livewire\AttendanceStatCards` (pending & approved-bulan-ini, dipisah dari `RecentRequestsCard`).
- CSS grid `.bento` dikembalikan ke layout 5 kolom asli (welcome 2x2, absensi 1x2, saldo 1x1x2, stat 1x1x2, recent full-width row 3).
- Rename tampilan: card "Absensi Hari Ini" → **"Presensi Hari Ini"** (presensi = kehadiran), menu nav "Pengajuan" → **"Absensi"**.

Menyusul, user minta warna card Presensi **tetap hijau** seperti demo (`#047C54 → #024732`) meskipun belum terintegrasi ke data clock-in nyata (sebelumnya sempat diubah ke abu-abu sebagai penanda "belum aktif" — dikoreksi karena user mau visualnya tetap sama dengan demo). Ditambahkan juga 3 elemen dekoratif (`.a-deco-1/2/3` — lingkaran putih transparan, lingkaran navy transparan, kotak magenta transparan diputar) yang sebelumnya ada di demo tapi belum dipindahkan ke card ini. Diverifikasi lewat curl manual (login → GET `/dashboard`) bahwa markup `a-deco-1/2/3` dan warna `#047C54` benar-benar muncul di HTML, plus 29 test tetap lolos semua.

## Tahap 1c — Attendance (Cuti/Izin/Lembur)

- Form Cuti/Izin/Lembur (`form-cuti.html`, `form-izin.html`, `form-lembur.html`) disambungkan ke endpoint yang sudah ada di `routes/api.php` (`POST /attendance/leave|permission|overtime`).
- List pengajuan (`list-pengajuan.html`) dan detail pengajuan (`detail-pengajuan.html`) disambungkan ke `GET /attendance/requests` dan `GET /attendance/requests/{id}`.
- Approval flow di baliknya SUDAH ada & jalan (lihat `knowledge/markdown/approval-flow-role-access.md`) — portal ini cuma perlu menampilkan status/histori, tidak perlu bikin ulang logic approval.

## Referensi

- `planning/web-attendance/PLANNING.md` — spesifikasi awal fitur attendance (endpoint, validasi per jenis, draft route)
- `planning/web-attendance/demo/*.html` — acuan visual tiap halaman
- `planning/2026-07-03-arsitektur-dashboard-per-role.md` — model Level×Domain dashboard
- `planning/2026-07-03-perbaikan-backend-scoping-akses.md` — progres company-scoping (Fase 1/2/3)
- `knowledge/markdown/approval-flow-role-access.md` — cara kerja approval flow yang sudah ada
- `knowledge/dev/urutan-implementasi-portal.md` — pengingat singkat urutan tahap ini
