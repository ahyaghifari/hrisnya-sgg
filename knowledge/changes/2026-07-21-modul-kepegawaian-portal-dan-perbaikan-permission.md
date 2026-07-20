# Perubahan: Modul Kepegawaian di Portal, Perbaikan Permission Super Admin, dan Housekeeping (2026-07-20 s/d 2026-07-21)

Sesi ini dimulai dari troubleshoot bug permission `sistem_kepegawaian` yang berujung ke masalah lebih besar di production (duplikat role "Super Admin"), lalu dilanjutkan membangun modul Kepegawaian sungguhan di portal karyawan (`/kepegawaian/karyawan`, `/kepegawaian/dokumen`) sesuai `planning/2026-07-20-portal-modul-kepegawaian-karyawan-dan-navigasi-dashboard.md`, plus beberapa perbaikan bug & penyesuaian UI di luar rencana awal yang ditemukan sepanjang jalan.

## Ringkasan Status Akhir

- **Bug permission `sistem_kepegawaian` di production**: root cause ditemukan & diperbaiki — bukan bug kode, tapi 2 baris role "Super Admin" duplikat (1 global `company_id=NULL`, 1 holding) dengan permission yang tidak sinkron, ditambah temuan penting soal `filament-shield` config `define_via_gate=false` (lihat §1).
- **Modul Kepegawaian portal**: 4 halaman baru (`Karyawan` list, `Tambah Karyawan`, `Dokumen Kepegawaian` list, `Tambah/Edit Dokumen`) — semua sudah jalan, ter-test (25 test baru, semua lolos), sudah masuk navigasi desktop & mobile.
- **Soft-delete diaktifkan untuk `UserDocuments`** + fitur filter Aktif/Tidak Aktif + Pulihkan di 2 halaman (Karyawan & Dokumen).
- **Belum dieksekusi**: halaman Dashboard sungguhan per modul (Penggajian/Absensi/Kepegawaian) — baru skeleton nav "(segera)", dan halaman Edit Karyawan belum dipindah ke `/karyawan/{id}/edit` (masih `/my-staff/{record}/edit`, sengaja, lihat planning §5).

## 1. Bug Permission `sistem_kepegawaian` — Duplikat Role "Super Admin"

User menambahkan permission `sistem_kepegawaian` ke role "Super Admin" lewat `/admin/roles`, tapi tombol menu tidak muncul di portal. Investigasi bertahap:

1. **Ditemukan**: ada 2 baris role bernama sama persis "Super Admin" — satu `company_id = NULL` (global), satu `company_id` terisi (holding). Akun user ternyata terpasang ke role global, sementara permission baru ditambahkan ke role holding (salah pilih di UI karena nama sama persis, kolom "Perusahaan" di list gampang kelewat).
2. User minta role yang usernya cuma 1 (role global) **dihapus**, lalu role global itu (id `85` di lokal) dieksekusi hapus, dan user di-reassign ke role holding (id `1`) via command tinker.
3. **Setelah itu sidebar `/admin` malah kosong total** — walau `hasRole('Super Admin')` sudah `YES`. Ternyata `config/filament-shield.php` set `'define_via_gate' => false` — artinya **Shield TIDAK bikin bypass otomatis buat role "Super Admin"**, role itu harus benar-benar punya SETIAP baris permission ter-assign secara eksplisit. Role holding yang tersisa ternyata permission-nya tidak lengkap (beda dari role global yang barusan dihapus, yang justru lengkap).
4. **Perbaikan**: `$role->givePermissionTo(Permission::all())` ke role holding (pakai `givePermissionTo`, bukan `syncPermissions`, supaya tidak menghapus assignment lain yang sudah ada).
5. Temuan ini disimpan ke memory (`shield-super-admin-tidak-bypass-otomatis.md`) supaya sesi berikutnya tidak salah asumsi Shield auto-bypass untuk role "Super Admin".

**Command yang dipakai** (referensi kalau kejadian lagi di production): cek role duplikat by nama + company_id, cek `hasRole()`/`can('ViewAny:X')` langsung (bukan cuma `hasRole()`), `givePermissionTo(Permission::all())` buat nambal role yang kurang lengkap.

## 2. Bug "GET method not allowed for livewire/update" (Mitigasi)

Ditemukan saat pakai halaman Karyawan: error muncul kalau user ganti filter (memicu request Livewire AJAX) lalu langsung reload manual (F5) sebelum request selesai. Root cause pastinya ada di internal JS Livewire (race antara `wire:navigate` dan commit request saat reload), bukan sesuatu yang bisa ditambal langsung dari kode aplikasi.

**Mitigasi** (`resources/views/components/layout.blade.php`, berlaku portal-wide): `Livewire.hook('request', ...)` menghitung request yang masih menggantung, lalu `beforeunload` memicu dialog konfirmasi bawaan browser kalau user coba reload/tutup tab selagi masih ada request jalan. Bukan perbaikan akar masalah, cuma menahan trigger-nya.

## 3. Investigasi Data (Informational, Tidak Ada Perubahan)

- Dicek: ada 5 user (semua sudah soft-deleted juga, kemungkinan data uji lama) yang `job_position_id`-nya menunjuk ke jabatan "Staff" yang sudah di-soft-delete. Tidak ada user **aktif** yang datanya rusak seperti ini — dianggap bersih untuk kondisi produksi saat ini.

## 4. Modul Kepegawaian — Halaman Karyawan

- **`/kepegawaian/karyawan`** (`app/Livewire/Kepegawaian/KaryawanPage.php` + `resources/views/livewire/kepegawaian/karyawan-page.blade.php`) — list SEMUA karyawan company (beda dari `/my-staff` yang cuma bawahan), gated `sistem_kepegawaian`, filter Perusahaan (khusus global role)/Departemen/search, pola desktop-tabel/mobile-card sama seperti MyEmployees.
- **`/kepegawaian/karyawan/tambah`** (`CreateKaryawan.php` + blade) — form Tambah Karyawan **didesain ulang** (bukan port Filament schema admin), wizard 3 step custom pakai token desain portal (`.form-input`, `.section-nav-btn` sebagai indikator step, `.custom-checkbox`, `.toggle`). Logic simpan (merge user soft-deleted by email/NIP, `syncRoles`, `UserJobPositions`, validasi kuota jabatan) direplikasi persis dari `App\Filament\Resources\Employees\Pages\CreateEmployee` biar perilakunya tidak menyimpang dari admin.
- **Halaman detail karyawan** (`EmployeeAttendanceStatistics`) di-rename route-nya dari `/my-staff/{record}` jadi `/karyawan/{record}` (nama route `karyawan.show`) — sekarang dipakai bareng oleh 3 entry point (Data Diri, MyStaff, Karyawan). `canManage()` di `EmployeeAttendanceStatistics.php` & `EditMyEmployee.php` diperluas: HC dengan `sistem_kepegawaian` sekarang bisa lihat/edit siapa saja di company sendiri (bukan cuma bawahan). Tombol "Kembali" pakai query param `?from=karyawan` biar balik ke list yang benar tergantung asal.

## 5. Modul Kepegawaian — Halaman Dokumen

- **`/kepegawaian/dokumen`** (`DocumentsPage.php`) — padanan portal dari admin `UserDocumentResource` yang sebelumnya cuma view-only lewat tab "Dokumen" di Data Diri/MyStaff. Panel kiri jenis dokumen (reuse `.doc-type-nav`/`.doc-type-btn` yang sudah ada), kanan filter + list, tombol Lihat/Edit/Hapus.
- **`/kepegawaian/dokumen/tambah`** & **`/kepegawaian/dokumen/{record}/edit`** (`DocumentFormPage.php`, satu komponen dipakai 2 route) — field & validasi kondisional (retirement_date/type, reason, set_in) diambil dari `UserDocumentForm` admin, upload berkas pakai pola `.upload-zone` yang sudah ada di form Lembur.
- **Bug ditemukan & diperbaiki**: dokumen milik karyawan yang sudah di-soft-delete (kasus nyata: "Dimas Permadi") bikin form Edit gagal auto-isi field Karyawan & Perusahaan, DAN bikin HC company-scoped kena 403 diam-diam — root cause: `canManage()` & resolusi `company_id` di `mount()` pakai relasi `user()` yang default exclude soft-deleted. Diperbaiki pakai `$document->user()->withTrashed()->first()` di kedua tempat, plus opsi karyawan nonaktif tetap muncul di dropdown (ditandai "(nonaktif)") kalau memang sedang di-assign ke dokumen yang diedit.

## 6. Soft-Delete `UserDocuments` + Filter Aktif/Tidak Aktif

- Tabel `user_documents` ternyata sudah punya kolom `deleted_at` dari migration awal, tapi modelnya belum pakai trait `SoftDeletes` — jadi tombol Hapus sebelumnya hard-delete walau kolomnya nganggur. Diaktifkan (`use SoftDeletes` di `UserDocuments.php`).
- Tombol Hapus sekarang soft-delete, **berkas fisik SENGAJA tidak ikut dihapus** (supaya kalau dipulihkan, link file-nya masih valid).
- **Filter status Aktif/Tidak Aktif** ditambahkan di 2 halaman (Karyawan & Dokumen) — default **Aktif**, "Tidak Aktif" pakai `onlyTrashed()` dan tombol aksinya berubah jadi **Pulihkan** (restore, dicek permission+company sama seperti aksi lain). Sempat dibuat pakai `.section-nav-btn` (pill besar berwarna) tapi user minta diperkecil karena ini cuma filter sekunder — diganti jadi toggle kecil netral (segmented control 2 opsi, bukan pill besar).

## 7. Navigasi

- **Desktop** (`nav.blade.php`): dropdown "Kepegawaian" ditata ulang — Karyawan (atas) → divider → Izin/Cuti/Lembur (bawah, peninggalan perubahan 2026-07-19). Dropdown Penggajian/Absensi/Kepegawaian masing-masing dapat 1 item "Dashboard" placeholder (skeleton nav, non-klik, "(segera)") di posisi paling atas.
- **Mobile** (`pwa-bottom-nav.blade.php`): ditemukan bug lama — sheet menu mobile **tidak pernah** mengecek permission `sistem_kepegawaian` sama sekali (beda dari desktop yang sudah py). Sudah ditambah gate + section Kepegawaian (Karyawan, Dokumen, Izin/Cuti/Lembur), dikelompokkan pakai label divider per sistem (`.pwa-menu-grid-divider`, CSS baru) — "Sistem Keuangan"/"Sistem Absensi"/"Sistem Kepegawaian", biar rapi kayak dropdown desktop yang sudah pakai divider.

## 8. Redesign Card "Pelatihan Saya" di Beranda

Di luar rencana Kepegawaian — user minta card pelatihan di halaman Beranda (`home-cards.blade.php`) pakai bahasa visual yang sama dengan halaman Pelatihan (`course-card`: spine warna aksen kiri per kategori, media thumbnail), tapi versi ringkas. Dibuat `.course-card-mini` (CSS baru, `app.css`) — lebar 168px (vs `.pwa-course-chip` lama 215px), tanpa kode/tanggal/roster peserta/CTA, cuma media+eyebrow kategori+judul+progress bar+badge status. Warna aksen pakai hash `categoryAccent()` yang sama persis dengan `TrainingList::categoryAccent()` (disalin ke `Dashboard.php`) supaya warna kategori konsisten di kedua halaman.

## 9. Test Baru

25 test di `tests/Feature/Kepegawaian/` (semua lolos):
- `KaryawanPageTest` (7): permission, scoping company, search, default-aktif, switch tidak-aktif, restore, restore diblokir beda company.
- `CreateKaryawanTest` (4): permission, validasi step, wizard lengkap sukses, kuota jabatan penuh diblokir.
- `DocumentsPageTest` (7): permission, scoping company, switch jenis dokumen, soft-delete + file tetap ada, default-aktif, switch tidak-aktif, restore.
- `DocumentFormPageTest` (7): permission, file wajib saat create, create+file sukses, detail pensiun tersimpan, edit tanpa file baru, **prefill company/karyawan meski karyawannya sudah nonaktif** (regresi §5), stranger beda company diblokir.

## 10. Belum Dikerjakan / Ditunda

1. **Halaman Dashboard sungguhan** per modul (Penggajian/Absensi/Kepegawaian) — baru nav skeleton "(segera)", belum ada halaman/komponennya.
2. **Halaman Edit Karyawan** belum dipindah ke `/karyawan/{id}/edit` — masih di `/my-staff/{record}/edit` (`EditMyEmployee`), keputusan planning §5 cuma minta halaman **detail** (show) yang pindah.
3. **UI restore untuk `job_position_id` yang jabatannya soft-deleted** (temuan §3) — belum ada tindakan, cuma dicatat sebagai informasi bersih untuk data aktif.
4. **Force-delete/hapus permanen** untuk `UserDocuments` yang sudah soft-deleted — belum ada tombolnya (baru soft-delete + restore).
5. Verifikasi manual di browser sungguhan (Playwright/manual) untuk seluruh modul Kepegawaian belum dilakukan sesi ini — baru diverifikasi lewat `php artisan test`.

## Referensi

- `planning/2026-07-20-portal-modul-kepegawaian-karyawan-dan-navigasi-dashboard.md` — rencana lengkap modul ini, termasuk 5 keputusan desain yang dikonfirmasi user sebelum eksekusi.
- `app/Filament/Resources/Employees/` — baseline admin yang direplikasi logic-nya ke `CreateKaryawan`.
- `app/Filament/Resources/UserDocuments/` — baseline admin yang direplikasi logic-nya ke `DocumentsPage`/`DocumentFormPage`.
- `DESIGN.md` — token & pola desain portal yang dipakai konsisten di semua halaman baru (`.form-input`, `.section-nav-btn`, `.doc-type-btn`, `.upload-zone`, responsive requirement).
- Memory: `shield-super-admin-tidak-bypass-otomatis.md` — detail teknis §1.
