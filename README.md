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
  Filament/         # Resource, Page, Widget untuk admin panel
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
- **Payroll** — payroll reguler, komponen gaji (katalog + penempatan ke Company/Pangkat/Jabatan, eligibility per tipe karyawan), insentif, fee submission (termasuk dokter, sudah unifikasi ke siklus payroll biasa)
- **Shift** — master shift, template, jadwal, tukar shift
- **Rekrutmen** — job opening publik → candidate → recruitment process
- **LMS** — kursus, kuis, sertifikat, ranking, trainer
- **Assessment, Coaching, Counseling, Task tracking**
- **Kanban** (`/kanban`) — board project (multi-member, role owner/assigner/member per board) + task individual tanpa board ("BoardKu"). Checklist per task, komentar, upload lampiran, multi-assignee, kontrol status berlapis (owner/pembuat bebas, member cuma maju 1 langkah, `done` terkunci total kecuali komentar), warna board custom. **"Kanban Staff"** — atasan bisa assign task langsung ke satu bawahan tanpa bikin board (`board_id = null`, muncul di BoardKu staff & section khusus di `/kanban` sisi atasan). `wire:poll.10s` di board detail & list (bukan realtime websocket — project belum pasang broadcasting). Detail lengkap: `knowledge/changes/2026-07-13-modul-kanban-verifikasi-dan-revisi.md`, rencana awal: `planning/2026-07-13-modul-kanban-project-task.md`.

Otorisasi `/admin` memakai **Filament Shield** ([bezhansalleh/filament-shield](https://filamentphp.com/plugins/bezhansalleh-shield)) sejak 2026-07-08 — permission di-generate otomatis dari Resource/Page/Widget yang terdaftar, format `Verb:Subject` (mis. `View:Employee`, bukan `view_karyawan`), 12 verb standar per Resource, Policy ikut di-generate otomatis (layer Policy custom lama sudah dihapus total, digantikan Shield). Permission dikelola lewat `RoleResource` (`/admin/roles`) — form assign permission-nya (`RoleForm.php`) mengelompokkan checkbox per `navigationGroup` Resource secara dinamis. Akses `/admin` (Filament) dibatasi ke role **Admin/Super Admin** saja (`User::canAccessPanel()`); role lain (Supervisor, Manager, HC, Karyawan, dst) beraktivitas lewat **portal karyawan** (`/dashboard`, `/my-staff`, `/pengajuan-absensi`, `/data-diri`, dst — lihat bawah). Route portal dijaga registry `config/portal-pages.php` + middleware `EnsurePortalPageAccess` (route yang tidak terdaftar di registry lolos tanpa cek — desain sengaja, bukan whitelist ketat).

**Catatan penting**: hanya role "Super Admin" (2 baris: holding + global) yang saat ini punya permission terisi — 77 role lain di 8 company punya 0 permission sejak migrasi Shield, belum di-assign ulang. Detail & prioritas lanjutan di [`planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md`](planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md).

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
- **Mobile** (`<768px`): `<x-pwa-app-bar>` (app bar, tanpa tombol hamburger lagi) + `<x-pwa-bottom-nav>` (bottom nav 5 slot — Beranda/Presensi/Menu (tombol tengah, buka bottom sheet shortcut sesuai role)/Pengajuan/Akun; drawer off-canvas lama sudah dihapus 2026-07-12, lihat [`DESIGN.md`](DESIGN.md) bagian "Mobile navigation").
- Instalabilitas PWA (manifest `public/pwa/manifest.json`, service worker `public/sw.js`, `start_url` ke `/dashboard`) kini berlaku situs-lebar, bukan cuma satu shell terpisah.
- Header tiap halaman (tanggal + jam live yang update tiap detik + judul) pakai satu komponen `<x-page-header title="..." />` (`resources/views/components/page-header.blade.php`) — jangan tulis ulang markup date/title manual per halaman.
- Logo resmi: `public/assets/logo-sgg.png`, dipakai di `<x-nav>`.

Pola "mobile card vs desktop table" dipakai konsisten di semua list/detail: lihat [`DESIGN.md`](DESIGN.md) bagian *Responsive requirement* untuk pola persisnya (kapan pakai `.pwa-list`/`.pwa-list-row` vs Filament Table, cara gabung query Filament Table + query manual mobile lewat satu `baseQuery()`, dan jebakan cascade-layer Tailwind `md:hidden` vs custom CSS unlayered).

Halaman yang sudah berjalan:
- `/dashboard` — ringkasan (saldo cuti + jumlah izin/lembur bulan ini berdampingan presensi, status pengajuan aktif dengan timeline visual — vertikal di mobile, horizontal di desktop, kotak "Butuh Persetujuan Anda" buat atasan, Task Saya/Kursus Saya placeholder).
- `/my-staff` + `/my-staff/{id}/edit` + `/my-staff/{id}` — daftar (Filament Table di desktop, card list di mobile), edit, dan **detail** karyawan (profil lengkap + presensi harian + statistik cuti/izin/lembur + riwayat pengajuan + dokumen karyawan, dinavigasi lewat 4 tombol `.section-nav` warna-warni, bukan tab bergaris bawah), scoped ke bawahan (rekursif via `OrgHierarchyService`) atau semua company (khusus Direktur holding). Widget "Butuh Persetujuan Anda" juga tampil di halaman list-nya, sudah responsive juga.
- `/data-diri` — halaman "detail karyawan" yang sama (`EmployeeAttendanceStatistics`) tapi dipakai untuk **lihat data diri sendiri** (`record` default ke user yang login), bukan cuma untuk atasan melihat bawahan.
- `/pengajuan-absensi` (list riwayat **milik sendiri**, Filament Table di desktop / card list + Aksi Cepat Cuti-Izin-Lembur di mobile) + `/pengajuan-absensi/{cuti,izin,lembur}` (form pengajuan, custom — bukan reuse Filament, header-nya senada warna dengan tombol Aksi Cepat: navy/gold/hijau) + `/pengajuan-absensi/{id}` (detail: timeline status + tombol Approve/Reject/Batalkan buat atasan — manager juga bisa **lihat** detail pengajuan bawahannya walau bukan approver).
- `/akun` + `/akun/ubah-password` — ganti foto profil & ubah password, cuma ada di portal (belum ada padanan di admin panel).
- Approve/Reject/**Batalkan** tersedia di **dua tempat** (admin `/admin/attendance-requests` DAN portal) — reuse service yang sama (`ApprovalFlowService`, `ApprovalPermissionService`), tidak ada logic ganda.
- `/kanban` (daftar board + tombol "Kanban Staff" + section task titipan atasan↔staff) + `/kanban/baru` (buat board, guard `canAccessKaryawanMenu`) + `/kanban/{board}` (papan kanban 4 kolom status) + `/kanban/pribadi` ("BoardKu" — task tanpa board) — lihat detail di § Modul Utama di atas.

Detail lengkap tiap bagian ada di `knowledge/changes/` (lihat tanggal terbaru) dan `planning/2026-07-05-portal-pengajuan-absensi.md` / `planning/2026-07-04-draft-role-job-position-mapping.md` / `planning/2026-07-05-statistik-karyawanku.md`. Catatan: `knowledge/changes/2026-07-05-pwa-portal-karyawan.md` mendokumentasikan shell PWA terpisah yang **sudah tidak berlaku** (sudah dilebur ke atas) — baca sebagai histori, bukan state saat ini.

## Design System

Konvensi visual portal karyawan (palet warna, aturan kontras, identitas warna per kartu, komponen shared) didokumentasikan di [`DESIGN.md`](DESIGN.md) — baca sebelum menambah/mengubah tampilan portal, termasuk aturan penting soal inline style vs class utility Tailwind baru (butuh `npm run watch`/`build` sebelum terlihat).

**Dark mode** (2026-07-11) sudah aktif untuk seluruh portal karyawan (tidak untuk `/admin`/`/candidate`) — toggle manual (localStorage, class `dark` di `<html>`) di nav desktop & app bar mobile, warna lewat CSS custom property di `app.css`. **Wajib baca bagian "Dark mode" di [`DESIGN.md`](DESIGN.md) sebelum menambah warna baru** — warna literal (bukan `var(--token)`) di komponen portal gampang jadi nyaris tak kelihatan di dark mode (sudah kejadian sekali, lihat catatan di sana).

## Known Gaps

- Test coverage sudah ada untuk alur portal karyawan & attendance request (`tests/Feature/`, jalan di sqlite in-memory, `RefreshDatabase` + factory) — modul lain (payroll, LMS, shift, dll) masih belum punya test.
- CI (Bitbucket Pipelines) hanya deploy, tidak ada test/lint gate.
- Redis dikonfigurasi tapi tidak dipakai (cache/queue/session masih driver `database`).
- `GET /attendance/template/download` tidak ada middleware `auth`, berbeda dengan endpoint import lain.
- **PENTING buat siapapun yang mengubah `phpunit.xml`**: beberapa test (`tests/Feature/Api/AttendanceApiTest.php`, `Auth/LoginTest.php`, `Portal/DashboardTest.php`, dll) pakai `RefreshDatabase` — kalau `DB_CONNECTION`/`DB_DATABASE` di `phpunit.xml` diarahkan ke database asli (bukan sqlite `:memory:`), `RefreshDatabase` akan **migrate:fresh dan menghapus semua data** di database itu. Sudah pernah kejadian (2026-07-05) — jangan diulang.
- Kalau perubahan CSS/style di portal "tidak muncul" di browser padahal kodenya sudah benar, cek dulu apakah `public/build/assets/app-*.css` sudah lebih baru dari `resources/css/app.css` — kemungkinan besar cuma butuh `npm run watch`/`build` ulang, bukan bug kode.
- `m_companies` belum ada kolom kategori (hospital vs non-hospital) untuk fitur jasa dokter — gap ini dikonfirmasi nyata tapi baru terdokumentasi sebagai rekomendasi (`knowledge/markdown/analisis-payroll.md` §6.1), belum diimplementasikan. (Update 2026-07-12: eligibility per **employee type** untuk komponen gaji **sudah diimplementasikan** — 1 komponen gaji punya daftar tipe karyawan eligible via `salary_component_employee_types`, dicek `PayrollService::isEligibleForEmployeeType()`. Lihat addendum di [`planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md`](planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md).)
- Approval Engine: path reject nyoba nulis `rejected_by`/`rejected_at` ke `attendance_requests` tapi kolom itu tidak ada (silently dropped), dan ejaan status `canceled`/`cancelled` beda antara `approval_activities` vs `approval_snapshots` — detail di `knowledge/markdown/manajemen-persetujuan.md`.
- Beberapa Resource bekas cluster Filament (Assessment, EmployeeDocument, ManPowerPlaning, ShiftPattern, UserTransitions — cluster-nya dihapus 2026-07-11) belum punya `$navigationGroup`, jadi tampil ungrouped di sidebar `/admin` dan di `RoleForm.php`. (`MasterSalaryComponent` sudah beres — direname & masuk grup "Keuangan", lihat modul Payroll di atas.)
- Dokumen karyawan (`UserDocuments`) sekarang dilihat/diunduh lewat portal (tab "Dokumen" di Data Diri/MyStaff), tapi **upload masih cuma lewat Filament** (`/admin/user-documents`, disembunyikan dari nav tapi route-nya aktif) — belum ada form upload dari portal.
- Kanban (`/kanban`) pakai `wire:poll.10s` buat sinkron antar user, **bukan realtime websocket sungguhan** — project belum pasang broadcasting (`BROADCAST_CONNECTION=log` di `.env.example`, tidak ada Pusher/Reverb/Echo). Juga belum ada drag-and-drop asli (masih tombol/dropdown "pindah status").
