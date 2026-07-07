# Perubahan: Portal Karyawan — Karyawanku, Pengajuan Absensi, Approval Inbox (2026-07-05)

Rencana lengkap: [`planning/2026-07-04-draft-role-job-position-mapping.md`](../../planning/2026-07-04-draft-role-job-position-mapping.md) (Pilar 3) dan [`planning/2026-07-05-portal-pengajuan-absensi.md`](../../planning/2026-07-05-portal-pengajuan-absensi.md).

## Ringkasan

Portal karyawan (`/dashboard`, dst) diperluas dari sekadar halaman statis jadi punya 2 fitur operasional penuh: **Karyawanku** (lihat & edit bawahan) dan **Pengajuan Absensi** (ajukan + approve Cuti/Izin/Lembur). Semua pakai Livewire biasa (guard `web`), bukan reuse API Sanctum yang sudah ada di `routes/api.php`.

## 1. Karyawanku (Pilar 3 — data scoping)

- `App\Services\OrgHierarchyService` — hitung bawahan rekursif dari `m_job_positions.parent_id`.
- `User::hasSubordinates()`, `User::canBrowseAllCompanies()` (khusus Direktur holding) — dipakai buat guard halaman & tampilan menu.
- `/karyawanku` (`MyEmployees.php`) — reuse `EmployeesTable::configure()` (kolom sama persis admin), query di-scope ke bawahan atau company pilihan (Direktur holding).
- `/karyawanku/{id}/edit` (`EditMyEmployee.php`) — reuse `EmployeeForm::configure()`, halaman penuh (bukan modal), guard: permission `edit_karyawan` + target harus bawahan.
- Field `company_id` di `EmployeeForm` sekarang cuma muncul untuk Admin/Super Admin — non-admin otomatis default ke company sendiri (memperbaiki bug crash saat form dipakai di luar konteks Resource asli — lihat §4).

## 2. Pengajuan Absensi (Cuti/Izin/Lembur)

- Route pindah dari `/pengajuan` ke **`/pengajuan-absensi`** (nama route tetap `my-attendance-requests.*`, cuma path yang berubah).
- 3 form submit (`SubmitLeaveRequest`, `SubmitPermissionRequest`, `SubmitOvertimeRequest`) — **Livewire biasa**, bukan reuse Filament Schema. UI-nya diambil dari mockup `planning/web-attendance/demo/form-{cuti,izin,lembur}.html`, CSS-nya ditambahkan ke `resources/css/app.css` (`.form-card`, `.form-input`, `.toggle`, `.task-row`, `.upload-zone`, dst).
- List (`MyAttendanceRequests`, reuse `AttendanceRequestsTable`) dan Detail (`AttendanceRequestDetail`, reuse `AttendanceRequestForm::editSchema()`).
- `AttendaceReqService::store{Leave,Permission,Overtime}Request()` diadaptasi terima `?User $actingUser` (dulu hardcode `auth('sanctum')`) — sekarang kompatibel guard `web` maupun `sanctum` (default fallback).
- `AttendanceRequestForm::leaveFormSchema()` / `overtimeFormSchema()` ditambahkan di admin (dulu cuma form Izin yang ada) — sekaligus menutup gap lama di `/admin/attendance-requests/create`.
- Section "Detail Cuti"/"Detail Izin" di `editSchema()` diratakan (dulu `RepeatableEntry` bikin card-di-dalam-card, padahal relasinya selalu 1 baris) — jadi `Grid`+`TextEntry` langsung pakai dot-notation (`leaveDetails.0.xxx`). "Detail Lembur" tetap `RepeatableEntry` (bisa multi-baris tugas).

## 3. Fitur Batalkan + Approval Inbox + Timeline Status

- **Batalkan** — fitur yang tadinya cuma scaffolding (`cancelled_by`/`cancelled_at`/enum `CANCELLED` ada tapi tidak dipakai). Ditambahkan branch `'cancelled'` di `ApprovalFlowService::advanceStep()` (reuse pola branch `'rejected'` yang sudah ada). **Catatan ejaan penting**: `approval_activities.status` pakai `canceled` (1 L), `approval_snapshots.status` pakai `cancelled` (2 L) — beda enum per tabel, sudah ditangani benar di kode.
- **Approval Inbox** — `ApprovalPermissionService::pendingApprovalsFor(User $user, string $moduleType)` (baru), query "semua approval pending milik saya" dengan filter ulang pakai `checkUserPermission()` yang sudah ada (tidak duplikasi logic scope/department). Ditampilkan lewat widget `PendingApprovalsCard` di Dashboard.
- **Timeline status** — `ApprovalTimelineService::build()` (extract dari logic yang tadinya nempel di `AttendanceRequestDetail`, sekarang reusable), dirender lewat Blade component `<x-approval-timeline>`. Dipakai di 2 tempat: Dashboard (`MyAttendanceStatusCard`, cuma pengajuan yang masih `submitted`/`in_review`, sekalian tampilkan jenis spesifik + tanggal + tombol ke detail — semua di dalam 1 card, bukan di luar) dan halaman Detail Pengajuan.
- **Approve/Reject/Batalkan dari portal** — `AttendanceRequestDetail` diperluas: guard akses jadi "submitter ATAU (pernah/sedang) approver", plus 3 tombol aksi kalau `ApprovalPermissionService::canApprove()` true. Reuse `ApprovalFlowService` yang sama dengan admin — **tetap ada di admin JUGA**, dua-duanya jalan berdampingan (keputusan eksplisit, bukan saling gantiin).

## 4. Bug yang ditemukan & diperbaiki

- **CSS Filament tidak ke-load di luar Panel** — `@filamentStyles`/`@filamentScripts` cuma register asset yang di-scope ke Panel aktif; CSS utama (`fi-*` classes) harus di-load manual (`<link href="{{ asset('css/filament/filament/app.css') }}">`) di tiap halaman portal yang pakai komponen Filament. Sudah diterapkan konsisten di semua halaman yang reuse Filament Table/Schema.
- **`.fi-badge` padding kurang lega** — override CSS ditambahkan (`padding-inline: 12px; padding-block: 5px;`) di `app.css`.
- **Tab "Riwayat Approval" kosong** — root cause: pakai `Repeater` (komponen FORM Filament, bukan infolist) dengan `->relationship()`, butuh `fill()` manual buat hydrate data relasinya (beda dari `TextEntry`/`RepeatableEntry` yang otomatis baca record tanpa fill). Fix: panggil `$this->getSchema('infolist')->fill($record->attributesToArray())` di `mount()` dan ulang lagi setelah approve/reject/cancel.
- **4 lapis container nested di halaman Detail** — wrapper card kita sendiri di sekitar `{{ $this->getSchema('infolist') }}` dihapus, karena Filament Section sudah render card sendiri — dari 4 lapis jadi 3.
- **Livewire butuh root tag selalu ada** — komponen kecil (`PendingApprovalsCard`) yang isinya `@if(...) <div>...</div> @endif` doang (tanpa root tag saat kondisi false) bikin Livewire error "missing root tag". Fix: root `<div>` selalu ada, `@if` di dalamnya.

## 5. ⚠️ Insiden & pelajaran (PENTING)

Sempat mengubah `phpunit.xml` (`DB_CONNECTION` dari sqlite ke mysql, mengarah ke database dev asli) untuk keperluan testing manual, lalu menjalankan `php artisan test` **tanpa filter** — ternyata ada test lama (`AttendanceApiTest`, `LoginTest`, `DashboardTest`) yang pakai `RefreshDatabase`, yang otomatis `migrate:fresh` ke database yang sedang dikonfigurasi. **Seluruh isi database dev (490 users, 8 companies, dst) terhapus.** User memulihkan sendiri secara manual.

**Perbaikan permanen**: `phpunit.xml` dikembalikan ke sqlite `:memory:`. Semua test attendance/portal yang baru ditulis ulang total pakai `RefreshDatabase` + data fixture sendiri (`Companies::create()`, `User::factory()`, dst) — **tidak pernah lagi bergantung ke data dev asli**, konsisten dengan pola test lain yang sudah ada di project ini sebelumnya.

**Pelajaran buat sesi berikutnya**: jangan pernah ubah `DB_CONNECTION`/`DB_DATABASE` di `phpunit.xml` ke koneksi yang menunjuk ke database sungguhan — cek dulu SEMUA test file (termasuk yang di subfolder, gampang kelewat) apakah ada yang pakai `RefreshDatabase`/`DatabaseTransactions` sebelum menyentuh config testing.

## Status

Semua di atas sudah diimplementasikan & dites (42 test lolos, sqlite in-memory). Belum ada verifikasi visual manual di browser — disarankan cek langsung sebelum dianggap benar-benar final.
