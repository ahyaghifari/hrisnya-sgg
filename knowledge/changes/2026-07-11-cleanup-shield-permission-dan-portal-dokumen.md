# Perubahan: Cleanup Permission Legacy (Shield) + Portal Dokumen Karyawan (2026-07-11)

Lanjutan dari migrasi Filament Shield (`planning/2026-07-08-migrasi-filament-shield.md`, `planning/2026-07-09-lanjutan-shield-dan-admin-cleanup.md`) — sesi ini fokus **membersihkan sisa permission format lama** yang masih nyangkut di kode setelah migrasi Shield, plus menambah 1 fitur baru di portal (tab Dokumen).

## Ringkasan

**Status akhir sesi**: katalog permission sekarang 100% bersih (660 permission, semua format Shield `Verb:Subject`, tidak ada sisa `snake_case`) ✅. `RoleForm.php` (form assign permission ke role) diperbaiki total, grouping-nya sekarang dinamis dari `navigationGroup` Resource, bukan hardcode ✅. 6 cluster Filament dihapus, Resource-nya dipindah ke top-level ✅. 4 file `Gate::allows()` ke permission mati dibuka ✅. `RoleDefaultPermissionsSeeder.php` dihapus (sudah tidak bisa dipakai) ✅. Tab baru "Dokumen" ditambah ke halaman Data Diri/MyStaff, `UserDocumentResource` disembunyikan dari nav admin ✅. **Belum dikerjakan**: assign ulang permission ke 77 dari 79 role yang sekarang 0 permission — user akan kerjakan sendiri.

## 1. Migration hapus permission legacy

`database/migrations/2026_07_11_195830_remove_legacy_snake_case_permissions.php` — hapus semua baris `permissions` yang namanya TIDAK mengandung `:` (ciri format lama, mis. `view_karyawan`, `approve_pengajuan_absensi`). Permission Shield selalu berformat `Verb:Subject` (ada `:`), jadi filter ini aman dan tidak butuh daftar nama manual. `model_has_permissions` dibersihkan manual dulu (FK cascade-nya sudah dilepas di migration ULID lama), `role_has_permissions` ikut lewat FK cascade bawaan.

Setelah migration ini: **660 permission tersisa, semuanya format Shield, 0 sisa format lama** (diverifikasi lewat tinker).

## 2. `DatabaseSeeder.php` dibersihkan

- Panggilan `PermissionsSeeder::class` dihapus dari `$this->call([...])` — seeder itu isinya ~150 permission format lama, sebelumnya bikin ulang permission mati itu tiap kali `db:seed` dijalankan (nutupi efek migration di atas).
- Blok duplikat kecil (`view_role`, `create_role`, `edit_role`, `delete_role` di-`Permission::firstOrCreate()` langsung) juga dihapus, sumber masalah yang sama.
- File `PermissionsSeeder.php` sendiri **tidak dihapus fisik**, cuma sudah tidak terpanggil.

## 3. `RoleForm.php` — grouping permission diperbaiki total

Bug lama: parsing nama permission masih `explode('_', $name)` (asumsi format `verb_subject` lama). Sejak Shield, format jadi `Verb:Subject` — parsing lama gagal total, ~660 permission numpuk jadi satu checkbox list tanpa pengelompokan.

Perbaikan (`app/Filament/Resources/Roles/Schemas/RoleForm.php`):
- Parsing ganti ke `explode(':', $name)` — `action` = bagian sebelum `:`, `subject` = bagian sesudah.
- 12 verb Shield (`ViewAny`, `View`, `Create`, `Update`, `Delete`, `DeleteAny`, `ForceDelete`, `ForceDeleteAny`, `Restore`, `RestoreAny`, `Replicate`, `Reorder`) dapat label Indonesia + urutan tampil.
- **Container/grup** (Kepegawaian, Keuangan, LMS, dst) sekarang **tidak hardcode manual** — diambil langsung dari `navigationGroup` Resource yang memakai model tsb, lewat `Filament::getPanel('admin')->getResources()` + `class_basename($resource::getModel())` untuk cocokkan ke Subject permission. Kalau 1 model dipakai >1 Resource dengan `navigationGroup` beda (mis. `JobPositions` dipakai ManPower/Master/Competency), yang menang adalah Resource pertama yang navigationGroup-nya terisi — ambigu tapi deterministik, bukan bug baru (modelnya memang dipakai lintas domain).
- Konsekuensi tidak langsung: Resource yang belum punya `$navigationGroup` (baru lepas dari cluster, lihat §4) tampil sebagai section berdiri sendiri di form ini juga — bukan cuma di sidebar.
- Test baru: `tests/Feature/RoleFormPermissionGroupingTest.php` — generate katalog Shield asli lalu assert halaman edit Role render 200.

## 4. 6 Cluster Filament dihapus, Resource dipindah ke top-level

Sebelumnya `Assessment`, `EmployeeDocument`, `ManPowerPlaning`, `MasterSalaryComponent`, `ShiftPattern`, `UserTransitions` masing-masing punya `*Cluster.php` sendiri dengan `canAccess()` manual (`Gate::allows('view_xxx_lama')`) — permission itu sudah dihapus di §1, jadi cluster-cluster ini sudah efektif hilang dari nav untuk semua orang kecuali Super Admin.

Dieksekusi: keenam `*Cluster.php` + folder-nya dihapus total. Resource yang tadinya nempel (`protected static ?string $cluster = XxxCluster::class;`) dilepas propertinya. `ShiftPattern` dan `UserTransitions` punya Resource **di dalam** folder cluster-nya sendiri (`Clusters/ShiftPattern/Resources/*`, `Clusters/UserTransitions/Resources/*`) — dipindah dulu ke `app/Filament/Resources/` (flat, sejajar `MasterDataShifts`, `ScheduleShifts`, `ShiftSwapApprovals`, `ShiftTemplates`, `UserTransitions`) sebelum foldernya dihapus, namespace ikut diupdate.

`config/filament-shield.php` — 6 baris `exclude` untuk cluster ini (di section `pages`) ikut dihapus (referensi ke class yang sudah tidak ada).

**Efek nyata**: URL Resource-Resource ini berubah, dari (kemungkinan) prefix cluster jadi flat — mis. `/admin/shift-templates` bukan `/admin/shift-pattern/shift-templates`. Filament yang urus routing internal jadi tidak ada link mati, tapi kalau ada bookmark/link hardcoded ke URL lama, itu akan 404.

**Belum dikerjakan (catatan, bukan bug)**: Resource-Resource ini sekarang tidak punya `$navigationGroup` sama sekali (dulu dapat grouping dari cluster) — jadi baik di sidebar `/admin` maupun di `RoleForm.php`, mereka tampil ungrouped/berdiri sendiri. Kalau mau rapi lagi, tinggal set `$navigationGroup` yang sesuai per Resource.

## 5. `RoleDefaultPermissionsSeeder.php` dihapus

File ini isinya mapping default permission per role, tapi **isinya masih nama permission format lama** (sudah dihapus di §1) — kalau dijalankan bakal error `PermissionDoesNotExist`. Sudah tidak dipanggil di `DatabaseSeeder` sejak dulu (nunggu review manual), sekarang filenya dihapus total karena tidak bisa dipakai lagi tanpa ditulis ulang dari nol pakai nama Shield.

## 6. 4 file `Gate::allows()` ke permission mati — dibuka

Ditemukan (di luar 6 cluster di atas) 4 file yang masih pakai `Gate::allows('nama_lama')` buat gating visibility tombol/menu — karena permission-nya sudah dihapus, ini semua otomatis selalu `false` untuk semua orang kecuali Super Admin (bypass Gate). Atas instruksi user, gate-nya **dihapus** (bukan diganti ke permission Shield setara) — visibility sekarang cuma bergantung ke kondisi bisnis asli yang sudah ada di sebelahnya:

- `AttendanceRequestResource::canApprove()` → `return true;` (dulu `Gate::allows('approve_pengajuan_absensi')`). Dipakai buat scope badge count nav (company-wide vs milik sendiri) — sekarang semua yang bisa akses Resource ini dapat scope company-wide.
- `AttendanceRecordResource::getNavigationItems()` → nav item "Data Absensi" (grup Kepegawaian) sekarang selalu didaftarkan, dulu digate `view_report_data_absensi`.
- `LmsCoursePlanItemResource::getNavigationItems()` → nav item "Pelatihan" (grup Manajemen Organisasi) sekarang selalu didaftarkan, dulu digate `approve_pengajuan_pelatihan`.
- `ManPower/JobOpenings/Pages/ViewCandidateApplicant.php` (7 tempat) → tombol approve/reject/edit proses kandidat sekarang cuma bergantung status data (`approval_status`, `ProcessStatus`), gate `approve_manajemen_lowongan_kerja`/`edit_manajemen_lowongan_kerja` dihapus.

Semua import `use Illuminate\Support\Facades\Gate;` yang jadi tidak terpakai ikut dibersihkan.

**Penting**: approval per-record yang sungguhan (siapa boleh approve request ATTENDANCE_REQUEST ini spesifik) masih dijaga penuh oleh `ApprovalPermissionService::canApprove($snapshotId, $user)` — method itu **beda** dari `AttendanceRequestResource::canApprove()` (nama mirip, isi beda total), tidak disentuh sama sekali di sesi ini.

## 7. Portal: tombol navigasi pengganti tab + tab "Dokumen" baru

Halaman Data Diri/MyStaff (`app/Livewire/EmployeeAttendanceStatistics.php` + `employee-attendance-statistics.blade.php`, satu komponen dipakai untuk kedua route) direvisi:

- Tab bergaris-bawah (`border-b-2` + warna `blue-700` generik) diganti jadi **pill button sungguhan** (`.section-nav` + `.section-nav-btn`, CSS baru di `app.css`) — target sentuh lebih besar untuk mobile. Tiap tombol dapat warna domain sendiri (`navy`=Profil, `green`=Presensi & Absensi, `rust`=Riwayat Pengajuan, `teal`=Dokumen), ikon Font Awesome, state aktif = fill solid. `.section-nav` pakai `flex-wrap` (bukan grid kolom tetap) supaya nambah tombol baru nanti otomatis wrap ke baris berikutnya tanpa ubah CSS.
- Tab baru **Dokumen**: reuse `UserDocuments` model (sebelumnya cuma bisa dilihat/diunggah lewat Filament). Portal-nya **lihat & unduh saja** (belum ada upload dari portal — itu masih lewat `/admin/user-documents` langsung). Layout: kiri daftar 5 jenis dokumen (Pengunduran Diri/Surat Pensiun/Surat Tugas/Surat Keterangan Kerja/Surat Kontrak Kerja — persis 5 menu yang dulu ada di Filament, badge angka jumlah dokumen per jenis), kanan daftar dokumen dari jenis yang dipilih (nomor dokumen, tanggal terbit, tombol unduh) — CSS baru `.doc-type-layout`/`.doc-type-nav`/`.doc-type-btn`. Mobile: jenis dokumen jadi pill row scrollable di atas, desktop (768px+): sidebar vertikal kiri.
- Model `UserDocuments` (`app/Models/User/UserDocuments.php`) dapat accessor baru `getFileUrlAttribute()` (`Storage::disk('public')->url($file_path)`), pola yang sama dengan `TaskAttachment`/`TaskProof`/`UserTransitionAttachment`.
- `UserDocumentResource::$shouldRegisterNavigation = false;` — Resource-nya **tidak dihapus** (route tetap ada, HC/admin masih bisa upload dokumen baru lewat `/admin/user-documents` langsung), cuma tidak lagi muncul di sidebar admin karena fungsi lihat/unduh sudah pindah ke portal. Sekalian dibersihkan 1 dead import (`use App\Models\UserDocument;` — class yang sama sekali tidak pernah ada).
- Test baru: `EmployeeAttendanceStatisticsTest::test_documents_tab_lists_only_the_records_own_documents` — pastikan dokumen milik user lain tidak ikut ketarik.

## Belum Dikerjakan / Ditunda

Lihat `planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md` untuk daftar prioritas lanjutan.
