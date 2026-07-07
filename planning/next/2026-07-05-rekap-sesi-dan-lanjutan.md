# Rekap Sesi 2026-07-05 & Rencana Lanjutan

## Context

Dokumen ini adalah rekap/handoff untuk menutup sesi kerja tanggal 2026-07-05 (panjang, banyak topik: otorisasi/permission, navigasi Filament, dua analisis mendalam, lima demo dashboard). Tujuannya supaya sesi berikutnya bisa langsung eksekusi tanpa harus menggali ulang riwayat percakapan.

**Status akhir sesi**: Cleanup otorisasi (Policy dead-code, portal-pages registry, PermissionResource) ✅. Navigasi Filament direorganisasi total ✅. Role "Super Admin" vs "Admin" sudah dibetulkan dengan aman ✅. Dua dokumen analisis teknis mendalam (Approval Engine, Payroll) ✅. Lima demo dashboard statis diperbarui & didiferensiasi per role ✅. **Belum ada satu pun rekomendasi dari analisis Payroll yang diimplementasikan ke kode nyata** — itu pending besar berikutnya.

## Ringkasan Apa yang Sudah Dikerjakan

### 1. Cleanup Otorisasi (Policy dead-code + portal-pages + PermissionResource)
- `app/Policies/` (41 file) dihapus total — sebelumnya dead code kecuali satu call site (`resources/views/livewire/organization-chart-widget.blade.php:56`, sudah diganti jadi `@can('create_struktur_organisasi')` biasa).
- Habis hapus, sempat kena **stale Composer classmap** (`vendor/composer/autoload_classmap.php` masih nyebut file yang sudah dihapus) → fixed dengan `composer dump-autoload`.
- Registry `config/portal-pages.php` (route name → `{label, permission, guard}`) + middleware `App\Http\Middleware\EnsurePortalPageAccess` (alias `portal.access`, dipasang di `bootstrap/app.php`, dibungkus ke grup route portal di `routes/web.php`). **Route yang tidak terdaftar di registry lolos begitu saja** — ini keputusan desain sengaja, bukan whitelist ketat.
- `PermissionResource` baru (`app/Filament/Resources/Permissions/`) supaya permission bisa di-CRUD lewat Filament UI. **Wajib** query-nya `->withoutGlobalScope('company')` karena semua permission yang di-seed punya `company_id = NULL` tapi model `Permission` punya global scope company.
- Planning file lengkap: `planning/2026-07-05-cleanup-otorisasi-policy-dan-permission.md`.
- Test: `tests/Feature/PortalPageAccessTest.php` (5 test, semua lolos).

### 2. Role "Super Admin" vs "Admin" — dibetulkan dengan aman
- Konvensi baru: **"Super Admin"** cuma untuk company holding. **"Admin"** untuk semua company lain (permission ceiling sama, cuma beda label).
- `RenameCompanySuperAdminToAdminSeeder.php` sudah **dijalankan** — rename role existing (bukan hapus+buat baru) supaya assignment user tidak putus. Alasan pilih rename: ada 3 user nyata di RSU Banjarbaru yang pegang role itu.
- `GrantPermissionManagementToSuperAdminSeeder.php` sudah **dijalankan** — kasih 4 permission manajemen-permission baru (`view/create/edit/delete_permission`) ke semua role Super Admin lintas company.
- `RoleSeeder.php` diupdate (const `HOLDING_COMPANY_ID`) supaya company baru ke depannya otomatis dapat label yang benar.
- **Masih ditunda, BELUM dijalankan**: `RoleDefaultPermissionsSeeder.php` — mapping default permission per role, sengaja belum di-run, nunggu user review dulu isi mapping-nya sebelum eksekusi ke data real.

### 3. Reorganisasi Navigasi Filament
- 7 nav group final: **Master Data, Kepegawaian, Manajemen Organisasi** (sekarang termasuk Manajemen Persetujuan, `navigationSort=9`, paling bawah), **Jadwal Shift Karyawan, Manajemen Pengguna** (tinggal Role + Permission), **Keuangan** (cluster dalamnya di-rename "Master Data Gaji"), **LMS**.
- `UserResource` ("Data Diri") sengaja **tanpa grup**, `navigationSort=-1`, berdiri sendiri tepat di bawah Dashboard.
- `RoleForm.php` (form assign permission ke role) di-refactor: `$navGroupMap` (~47 entry) memetakan setiap permission-group ke container yang sama persis dengan nav group di atas, tiap container dapat `Fieldset` per sub-grup di dalam `Section` collapsible, plus **Toggle "Pilih Semua"** per container (live, bulk-toggle semua checkbox di dalamnya) — ini fitur yang diminta user secara eksplisit ("ganti toggle aja hehe").
- `RolesTable.php` — tambah kolom "Jumlah User" (`counts('users')`, sortable) + `DeleteAction` yang cuma muncul kalau `users_count === 0`.

### 4. Dua Dokumen Analisis Teknis Mendalam
- **`knowledge/markdown/manajemen-persetujuan.md`** (+ versi HTML) — dokumentasi lengkap Approval Engine: tabel config (`m_approval_flows`, `_flow_steps`, `_flow_assignments`) vs tabel runtime (`approval_snapshots`+`approval_activities` yang di-mutate in-place, `approval_histories` yang append-only). Ketemu bug nyata: path reject coba tulis `rejected_by`/`rejected_at` ke `attendance_requests` tapi kolom itu **tidak ada** — silently dropped. Juga ketemu inkonsistensi ejaan enum status: `canceled` (1L) di `approval_activities` vs `cancelled` (2L) di `approval_snapshots`.
- **`knowledge/markdown/analisis-payroll.md`** (+ versi HTML) — analisis gap payroll, mengonfirmasi 2 dugaan user:
  1. **Tidak ada pembatasan hospital-only** untuk fitur jasa dokter/pajak — `m_companies` sama sekali tidak punya kolom tipe/kategori perusahaan.
  2. **Tidak ada eligibility employee-type** (permanent/contract) untuk tunjangan (Jabatan, Transportasi, dll) — `PayrollService::generatePayroll()` tidak pernah baca `employee_type_id` di mana pun.
  - §6.1 berisi rekomendasi jangka panjang: skema scope 3-tier (company-specific `company_id` → company-category kolom baru → global/NULL), meniru pola `scope_type` yang sudah ada di Approval Engine. **Ini baru rekomendasi terdokumentasi, belum ada satu baris kode pun yang berubah.**

### 5. Lima Demo Dashboard Statis Diperbarui
Folder: `planning/web-attendance/demo/dashboard{,-hc-manager,-direktur,-finance,-lnd}.html` — pure static mockup (bukan Livewire), tetap pakai vocabulary CSS yang sama dengan `DESIGN.md` (`.bento`, `.neuro`, `.stat-card`, `.panel`, `.data-table`, `.kpi-hero-card`).
- Semua tombol "Admin Panel" dihapus (desktop + mobile nav) di kelima file.
- KPI-bento grid yang identik di semua role **sengaja didiferensiasi** — HC tetap pakai KPI grid asli (+ 2 section baru: "Beban Approval per Approver" chart + "Cuti 7 Hari ke Depan"). Direktur, Finance, L&D masing-masing dapat "hero" baru yang beda bentuk:
  - **Direktur**: "Peta Kesehatan Perusahaan" — grid 4 `.stat-card` per entitas (RS Syifa Pusat/Bekasi, Klinik Bogor, Akademi) dengan health-dot warna + footer stat-chip row, plus panel baru "Approval Final Menunggu Anda".
  - **Finance**: "Progres Payroll Bulan Ini" — pipeline horizontal 5 langkah (Generate→Verifikasi→Review HC→Approve Direktur→Eksekusi Bayar), plus 4 stat-chip, plus panel baru "Tren Biaya Payroll 6 Bulan" (chart bar) di samping "Komposisi Payroll Mei 2026" (row jadi `.bento-charts` 2fr/1fr lagi).
  - **L&D**: "Progres Kompetensi 2026" — gauge radial CSS `conic-gradient` (71,4%) + 3 stat-chip inline.
- Warna identitas hero per role (final): HC=hijau (tetap), Direktur=navy-black monokrom, Finance=gold/bronze, L&D=magenta.
- Semua perubahan sudah diverifikasi tag-balance (`<div>`/`</div>` match) dan canvas-count = Chart.js-init-count.

## Belum Dikerjakan / Ditunda (urutan prioritas yang disarankan)

1. **Review & jalankan `RoleDefaultPermissionsSeeder.php`** — sudah ditulis, sengaja belum dieksekusi. User harus review dulu mapping default permission per role sebelum di-run ke data real (bisa berdampak ke banyak user sekaligus).
2. **Implementasi rekomendasi §6.1 `analisis-payroll.md`** — scope 3-tier untuk salary component:
   - Migration: kolom tipe/kategori baru di `m_companies` (mis. `category` enum hospital/non-hospital), kolom `company_category` nullable di `m_salary_components` & `m_doctor_services`.
   - Update lookup logic di `PayrollService` / `DoctorPayrollService` supaya resolve company-specific → company-category → global (NULL), bukan cuma company-specific vs global seperti sekarang.
3. **Fix gap employee-type eligibility di Payroll** — tambah kolom semacam `required_employee_type_id` (nullable) ke tabel salary component/tunjangan, lalu cek di `PayrollService::handleJobPositionFixedComponents()` / `...Percentage...()` sebelum masukin komponen ke slip gaji karyawan kontrak/permanent yang tidak eligible.
4. **Fix bug reject path Approval** (dari `manajemen-persetujuan.md`) — tambah kolom `rejected_by`/`rejected_at` ke `attendance_requests` (migration) supaya tidak silently dropped, atau ubah kode supaya tidak nulis ke kolom yang tidak ada.
5. **Samakan ejaan enum status** `canceled` vs `cancelled` antara `approval_activities` dan `approval_snapshots` — pilih satu ejaan, migration data + update semua reference di kode.
6. Pertimbangkan apakah 5 demo HTML dashboard perlu di-porting jadi Livewire component sungguhan (saat ini masih murni mockup statis, belum terhubung ke data asli) — belum ada keputusan/rencana untuk ini, perlu didiskusikan dulu dengan user kalau mau lanjut ke situ.

## Cara Cepat Lanjut Sesi Berikutnya

- **Jangan jalankan `npm run build`** atau ad-hoc dev server — user selalu punya `npm run watch` jalan sendiri, biarkan dia yang cek tampilan visual di browser sendiri.
- **Jangan jalankan atau usulkan test** kecuali diminta eksplisit — terutama kalau lagi iterasi cepat CSS/styling.
- **JANGAN PERNAH** arahkan `phpunit.xml` ke database asli — `RefreshDatabase` akan menghapus isinya. Pernah kejadian sekali (2026-07-05), DB dev ke-wipe. Selalu cek `phpunit.xml` masih `sqlite`/`:memory:` sebelum jalankan test apa pun.
- Kalau mau lanjut ke item #2/#3 (payroll scope 3-tier / employee-type eligibility): baca dulu `knowledge/markdown/analisis-payroll.md` §6.1 lengkap sebelum bikin migration, jangan re-derive dari nol.
- Kalau mau lanjut ke item #4/#5 (approval bug): baca dulu `knowledge/markdown/manajemen-persetujuan.md` bagian yang menjelaskan `approval_snapshots` vs `approval_activities` vs `approval_histories` — jangan disamakan, ketiganya punya peran beda.
- Role naming: kalau bikin/edit seeder company baru, ingat konvensi "Super Admin" cuma holding, "Admin" untuk yang lain (`RoleSeeder.php` const `HOLDING_COMPANY_ID` sudah pakai ini).
