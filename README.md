# HRIS — Syifa Global Group

Sistem HRIS (Human Resource Information System) internal untuk **Syifa Global Group**. Mengelola data karyawan, absensi & pengajuan (cuti/izin/lembur), payroll (reguler & dokter), shift, rekrutmen, LMS (learning management), assessment, coaching/counseling, hingga approval flow lintas modul.

## Tech Stack

**Backend**
- Laravel 12 (PHP 8.2+)
- [Filament 4](https://filamentphp.com) — admin panel utama (`/admin`), plus panel terpisah untuk Candidate
- Laravel Sanctum — auth berbasis token untuk API mobile/portal karyawan
- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) — role & permission (ULID-based)
- Livewire — komponen interaktif custom di luar Filament
- Maatwebsite Excel, barryvdh/dompdf, simple-qrcode, guava/calendar

**Frontend**
- Vite 7 + Tailwind CSS 4
- Belum ada SPA framework (Vue/React) — sisi interaktif memakai Livewire

**Database & Infra**
- MySQL, primary key **ULID** di seluruh tabel
- Queue, cache, session: driver `database` (Redis tersedia di config tapi belum dipakai)
- Deploy: Bitbucket Pipelines (SSH ke staging) — belum ada test gate di CI

## Struktur Proyek

Pola arsitektur konsisten: **Controller → Service → Repository → Model**, dikelompokkan per domain.

```
app/
  Filament/         # Resource, Cluster, Page, Widget untuk admin panel
  Http/Controllers/  # Controller web & API (Api/ untuk mobile/portal)
  Models/            # Dikelompokkan per domain (Attendance, Payroll, Shift, Candidate, LMS, ...)
  Services/          # Business logic per domain
  Repositories/       # Data access layer per domain
  Jobs/              # Proses berat via queue (payroll, import Excel, email)
routes/
  web.php            # Sangat tipis — Filament daftarkan route sendiri di /admin
  api.php            # REST API untuk mobile app / portal karyawan
database/migrations/ # 140+ migration, granular & terus berevolusi
planning/            # Dokumen perencanaan fitur yang belum dikerjakan
```

## Modul Utama

- **User & Profil** — data personal, riwayat jabatan, dokumen, mutasi/transisi
- **Attendance** — record kehadiran, pengajuan cuti/izin/lembur (`attendance_requests` + tabel detail per tipe), saldo cuti
- **Approval Engine** — alur approval generik & reusable, dipakai lintas modul (attendance, task, dll)
- **Payroll** — payroll reguler & payroll dokter (skema fee-based terpisah)
- **Shift** — master shift, template, jadwal, tukar shift
- **Rekrutmen** — job opening publik → candidate → recruitment process
- **LMS** — kursus, kuis, sertifikat, ranking, trainer
- **Assessment, Coaching, Counseling, Task tracking**

Otorisasi memakai **Spatie Permission** langsung (tidak ada layer Policy — sudah dihapus, dulu cuma dead code). Permission bisa dikelola lewat Filament (`PermissionResource`, `/admin/permissions`), role via `RoleResource`. Akses `/admin` (Filament) dibatasi ke role **Admin/Super Admin** saja (`User::canAccessPanel()`); role lain (Supervisor, Manager, HC, Karyawan, dst) beraktivitas lewat **portal karyawan** (`/dashboard`, `/my-staff`, `/pengajuan-absensi`, `/data-diri`, dst — lihat bawah). Route portal dijaga registry `config/portal-pages.php` + middleware `EnsurePortalPageAccess` (route yang tidak terdaftar di registry lolos tanpa cek — desain sengaja, bukan whitelist ketat).

Konvensi nama role: **"Super Admin"** cuma dipakai company holding, **"Admin"** untuk semua company lain (permission ceiling sama, beda label saja).

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev      # atau: composer run dev (server + queue + vite sekaligus)
```

## Portal Karyawan (Livewire, bukan API/SPA — satu set halaman responsive, bukan PWA terpisah)

Berbeda dari rencana awal (portal berbasis token Sanctum/SPA — lihat [`planning/web-attendance/PLANNING.md`](planning/web-attendance/PLANNING.md) untuk histori rencana lama), implementasi aktualnya pakai **session (guard `web`) + Livewire penuh**, konsisten dengan sisi admin. Endpoint di `routes/api.php` tetap ada untuk mobile app, tapi portal web TIDAK memakainya — portal manggil Service (`AttendaceReqService`, dll) langsung.

Sempat ada percobaan shell PWA terpisah di `/pwa/*` (app bar + bottom nav sendiri, konten sengaja beda dari `/dashboard`) — itu sudah **dilebur total** ke satu set halaman responsive. Tidak ada lagi route/komponen `/pwa/*` yang duplikat; setiap halaman portal sekarang otomatis menyesuaikan tampilan lewat breakpoint `md:` (768px), diatur di satu `components/layout.blade.php`:
- **Desktop** (`≥768px`): `<x-nav>` — topnav dengan dropdown/link biasa.
- **Mobile** (`<768px`): `<x-pwa-app-bar>` (app bar + tombol hamburger) + `<x-pwa-sidebar>` (drawer off-canvas, bukan bottom-nav lagi).
- Instalabilitas PWA (manifest `public/pwa/manifest.json`, service worker `public/sw.js`, `start_url` ke `/dashboard`) kini berlaku situs-lebar, bukan cuma satu shell terpisah.
- Header tiap halaman (tanggal + jam live yang update tiap detik + judul) pakai satu komponen `<x-page-header title="..." />` (`resources/views/components/page-header.blade.php`) — jangan tulis ulang markup date/title manual per halaman.
- Logo resmi: `public/assets/logo-sgg.png`, dipakai di `<x-nav>` dan `<x-pwa-sidebar>`.

Pola "mobile card vs desktop table" dipakai konsisten di semua list/detail: lihat [`DESIGN.md`](DESIGN.md) bagian *Responsive requirement* untuk pola persisnya (kapan pakai `.pwa-list`/`.pwa-list-row` vs Filament Table, cara gabung query Filament Table + query manual mobile lewat satu `baseQuery()`, dan jebakan cascade-layer Tailwind `md:hidden` vs custom CSS unlayered).

Halaman yang sudah berjalan:
- `/dashboard` — ringkasan (saldo cuti + jumlah izin/lembur bulan ini berdampingan presensi, status pengajuan aktif dengan timeline visual — vertikal di mobile, horizontal di desktop, kotak "Butuh Persetujuan Anda" buat atasan, Task Saya/Kursus Saya placeholder).
- `/my-staff` + `/my-staff/{id}/edit` + `/my-staff/{id}` — daftar (Filament Table di desktop, card list di mobile), edit, dan **detail** karyawan (profil lengkap + presensi harian + statistik cuti/izin/lembur + riwayat pengajuan dalam 3 tab), scoped ke bawahan (rekursif via `OrgHierarchyService`) atau semua company (khusus Direktur holding). Widget "Butuh Persetujuan Anda" juga tampil di halaman list-nya, sudah responsive juga.
- `/data-diri` — halaman "detail karyawan" yang sama (`EmployeeAttendanceStatistics`) tapi dipakai untuk **lihat data diri sendiri** (`record` default ke user yang login), bukan cuma untuk atasan melihat bawahan.
- `/pengajuan-absensi` (list riwayat **milik sendiri**, Filament Table di desktop / card list + Aksi Cepat Cuti-Izin-Lembur di mobile) + `/pengajuan-absensi/{cuti,izin,lembur}` (form pengajuan, custom — bukan reuse Filament, header-nya senada warna dengan tombol Aksi Cepat: navy/gold/hijau) + `/pengajuan-absensi/{id}` (detail: timeline status + tombol Approve/Reject/Batalkan buat atasan — manager juga bisa **lihat** detail pengajuan bawahannya walau bukan approver).
- `/akun` + `/akun/ubah-password` — ganti foto profil & ubah password, cuma ada di portal (belum ada padanan di admin panel).
- Approve/Reject/**Batalkan** tersedia di **dua tempat** (admin `/admin/attendance-requests` DAN portal) — reuse service yang sama (`ApprovalFlowService`, `ApprovalPermissionService`), tidak ada logic ganda.

Detail lengkap tiap bagian ada di `knowledge/changes/` (lihat tanggal terbaru) dan `planning/2026-07-05-portal-pengajuan-absensi.md` / `planning/2026-07-04-draft-role-job-position-mapping.md` / `planning/2026-07-05-statistik-karyawanku.md`. Catatan: `knowledge/changes/2026-07-05-pwa-portal-karyawan.md` mendokumentasikan shell PWA terpisah yang **sudah tidak berlaku** (sudah dilebur ke atas) — baca sebagai histori, bukan state saat ini.

## Design System

Konvensi visual portal karyawan (palet warna, aturan kontras, identitas warna per kartu, komponen shared) didokumentasikan di [`DESIGN.md`](DESIGN.md) — baca sebelum menambah/mengubah tampilan portal, termasuk aturan penting soal inline style vs class utility Tailwind baru (butuh `npm run watch`/`build` sebelum terlihat).

## Known Gaps

- Test coverage sudah ada untuk alur portal karyawan & attendance request (`tests/Feature/`, jalan di sqlite in-memory, `RefreshDatabase` + factory) — modul lain (payroll, LMS, shift, dll) masih belum punya test.
- CI (Bitbucket Pipelines) hanya deploy, tidak ada test/lint gate.
- Redis dikonfigurasi tapi tidak dipakai (cache/queue/session masih driver `database`).
- `GET /attendance/template/download` tidak ada middleware `auth`, berbeda dengan endpoint import lain.
- **PENTING buat siapapun yang mengubah `phpunit.xml`**: beberapa test (`tests/Feature/Api/AttendanceApiTest.php`, `Auth/LoginTest.php`, `Portal/DashboardTest.php`, dll) pakai `RefreshDatabase` — kalau `DB_CONNECTION`/`DB_DATABASE` di `phpunit.xml` diarahkan ke database asli (bukan sqlite `:memory:`), `RefreshDatabase` akan **migrate:fresh dan menghapus semua data** di database itu. Sudah pernah kejadian (2026-07-05) — jangan diulang.
- Kalau perubahan CSS/style di portal "tidak muncul" di browser padahal kodenya sudah benar, cek dulu apakah `public/build/assets/app-*.css` sudah lebih baru dari `resources/css/app.css` — kemungkinan besar cuma butuh `npm run watch`/`build` ulang, bukan bug kode.
- Payroll belum ada eligibility check per **employee type** (permanent/kontrak) untuk tunjangan, dan `m_companies` belum ada kolom kategori (hospital vs non-hospital) untuk fitur jasa dokter — dua gap ini dikonfirmasi nyata tapi baru terdokumentasi sebagai rekomendasi (`knowledge/markdown/analisis-payroll.md` §6.1), belum diimplementasikan.
- Approval Engine: path reject nyoba nulis `rejected_by`/`rejected_at` ke `attendance_requests` tapi kolom itu tidak ada (silently dropped), dan ejaan status `canceled`/`cancelled` beda antara `approval_activities` vs `approval_snapshots` — detail di `knowledge/markdown/manajemen-persetujuan.md`.
- `database/seeders/RoleDefaultPermissionsSeeder.php` sudah ditulis tapi **sengaja belum dijalankan** — nunggu review manual mapping default permission per role sebelum dieksekusi ke data real.
