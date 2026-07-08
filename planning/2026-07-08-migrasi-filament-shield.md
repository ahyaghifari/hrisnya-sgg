# Planning: Migrasi Otorisasi `/admin` ke Filament Shield — 2026-07-08

Status (update 2026-07-08, malam): **Pivot keputusan** — subject mapping custom (`PermissionSubjectMap`, closure penamaan) yang dibangun di Fase 0-4 **dibongkar lagi atas permintaan eksplisit**: permission sekarang pakai **penamaan default Filament Shield sendiri** (PascalCase, berbasis nama Model — `Update:Employee`, bukan `edit_karyawan`), bukan mempertahankan nama lama. Konsekuensinya: ~197 permission lama (dari `PermissionsSeeder`) jadi orphan, semua `role_has_permissions` di 8 company perlu di-assign ULANG manual ke permission baru. Ini keputusan yang **sudah dikonfirmasi eksplisit** (lihat §9). §2-§8 di bawah ini historis (menjelaskan pendekatan yang SEMPAT dipakai lalu dibatalkan) — baca §9 buat kondisi final.

## Latar Belakang

Otorisasi di `/admin` sekarang serba manual: tiap Filament Resource nulis sendiri `canAccess()`/`canView()`/`canEdit()`/`canDelete()` yang isinya `Gate::allows('nama_permission')` (lihat `RoleResource.php`, `UserResource.php` yang sudah dihapus, dst), dan katalog permission-nya (~100 baris) dirawat manual di `database/seeders/PermissionsSeeder.php`. Setiap kali ada Resource baru, dua tempat itu harus diupdate manual dan gampang lupa.

**Filament Shield** (`bezhansalleh/filament-shield`) otomatis:
- Scan semua Resource/Page/Widget yang terdaftar di panel, generate baris `Permission` sesuai konvensi (`{verb}_{subject}`).
- Auto-wiring `canAccess()`/`can()` di tiap Resource tanpa perlu tulis manual — cukup pasang policy/trait yang Shield sediakan.

Shield **bukan** sistem permission baru — tetap di atas tabel Spatie (`permissions`, `roles`, `role_has_permissions`) yang sama persis, tidak ada perubahan skema inti.

### Keputusan yang sudah dikonfirmasi (dari diskusi sebelumnya)

1. **Scope dibatasi ke `/admin` saja.** Portal karyawan (Livewire, di luar Filament) TIDAK ikut migrasi — sudah diverifikasi cuma ada 4 titik permission-string yang dipakai portal (lihat §3), sisanya role-check/guard-custom yang di luar jangkauan Shield sama sekali.
2. **`RoleResource`, `RoleForm`, `PermissionResource`, dan logic company-scoping (`company_id` di model `Role`) TIDAK diganti.** Shield kalau install standar nawarin generate ulang Resource Role/Permission miliknya sendiri — itu **tidak dipakai** karena tidak punya filter `company_id` per company yang sudah ada di `RoleResource::getEloquentQuery()`. `RoleForm` sudah dinamis (baca `Permission::query()->get()` lalu grouping otomatis), jadi otomatis "melihat" permission baru dari Shield tanpa kode tambahan — cuma perlu tambah entry ke `$navGroupMap` di `RoleForm.php` kalau ada subject baru.
3. **Nama permission yang sudah live TIDAK boleh berubah** — datanya sudah dipakai 8 company (`role_has_permissions` bukan data uji). Verb yang dipakai sekarang: `view`, `create`, `edit`, `delete` (bukan default Shield `view_any`/`update`/`restore`/dst).
4. **Company-tenancy Spatie (`teams`) TIDAK diaktifkan** — `config/permission.php` sudah `teams: false`, dan project pakai kolom `company_id` custom di tabel `roles`, bukan fitur resmi Spatie. Ini tidak diubah, supaya tidak menyentuh cara kerja inti package di seluruh aplikasi.

## 1. Verifikasi Kondisi Saat Ini

- **Filament**: `filament/filament: 4.0`, PHP `^8.2`, Laravel `^12.0`, `spatie/laravel-permission: ^6.21`. `User` model sudah pakai trait `HasRoles`.
- **Resource `/admin`** (di luar panel Candidate yang terpisah, tidak ikut migrasi ini): `ApprovalFlows, Assessment, AttendanceRecords, AttendanceRequests, Coachings, Competency, Counselings, Employees, LMS/*, ManPower/*, Master/*, Payroll/*, Permissions, Roles, TaskRequests, UserDocuments, UserShiftSchedules, UserShiftSwaps`, plus Resource di dalam Cluster (`Assessment, EmployeeDocument, ManPowerPlaning, MasterSalaryComponent, ShiftPattern, UserTransitions`).
- **Tidak ada custom Page** di `app/Filament/Pages/` (folder kosong) — cuma `Dashboard::class` bawaan Filament yang dipakai (sudah dibersihkan dari widget custom di sesi ini).
- **Widget custom yang tersisa**: tidak ada lagi (`PerformanceChart`, `NotificationWidget`, `ShiftCalendarWidget`, `AttendancePoliciesWidget` semua sudah dihapus di sesi ini).
- **Katalog permission existing** (`PermissionsSeeder.php`, ~100 baris): mayoritas berpola CRUD 4-verb (`view_x/create_x/edit_x/delete_x`), TAPI ada beberapa yang **bukan** pola CRUD standar Shield:
  - `approve_pengajuan_absensi`, `approve_pengajuan_pelatihan`, `approve_manajemen_lowongan_kerja` — verb `approve`, tidak ada di generator Shield.
  - `view_report_data_absensi`, `import_data_absensi` — subject sama (`data_absensi`) tapi verb tambahan di luar CRUD biasa.
  - Beberapa subject cuma punya sebagian verb (`struktur_organisasi` cuma `view`+`create`, `coaching`/`counseling` cuma `view/create/edit` — delete sengaja tidak ada).
  - `view_data_pribadi`/`edit_data_pribadi` — **sudah orphan**: cuma dipakai `UserResource` yang barusan dihapus di sesi ini. Sekarang cuma nongkrong di 2 seeder, tidak ada Gate check runtime lagi. Di luar scope plan ini, tapi dicatat supaya tidak bingung kalau Shield generate ulang permission untuk subject yang sama dari Resource lain.

## 2. Konsep Migrasi

### 2.1 Konvensi verb

Konfigurasi Shield (`config/filament-shield.php` → `permission_prefixes.resource`) dibatasi cuma 4 verb yang dipakai sistem sekarang:

```php
'permission_prefixes' => [
    'resource' => ['view', 'create', 'edit', 'delete'],
    ...
],
```

Menghilangkan default Shield (`view_any`, `update`, `delete_any`, `restore`, `restore_any`, `replicate`, `reorder`, `force_delete`, `force_delete_any`) yang tidak relevan — supaya katalog tidak membengkak dengan permission yang tidak akan pernah dipakai (YAGNI, konsisten sama cara kerja sistem sekarang).

### 2.2 Subject/slug per Resource — titik paling kritis

Permission existing pakai subject Bahasa Indonesia (`karyawan`, bukan `employee`; `data_absensi`, bukan `attendance_record`). Shield secara default menurunkan subject dari nama Resource/Model, yang di project ini **berbahasa Inggris** — hasil generate default kemungkinan besar akan beda nama dari yang sudah live (`edit_employee` bukan `edit_karyawan`).

**Ini harus dipastikan dulu sebelum generate massal** (lihat Fase 0 — Spike). Mekanisme override subject per Resource ada di package (lewat properti/atribut khusus di kelas Resource), tapi detail persis (nama properti/atribut) perlu dikonfirmasi terhadap versi paket yang benar-benar ter-install saat instalasi nanti — dokumentasi/API package bisa berubah antar versi, jangan diasumsikan dari ingatan tanpa dicek ke kode package yang ter-install.

**Kalau override subject per-Resource tidak memungkinkan / terlalu ribet**, jalur amannya: biarkan Shield generate dengan nama barunya sendiri (subject bahasa Inggris) sebagai permission BARU, lalu tulis migration yang me-*rename in-place* baris `permissions` (pola yang sama seperti di `planning/2026-07-08-redesign-struktur-payroll.md` §7 — `UPDATE permissions SET name = ... WHERE name = ...`, bukan hapus+buat baru, supaya `role_has_permissions` yang sudah ada otomatis ikut).

### 2.3 Permission non-CRUD (`approve_*`, `view_report_*`, `import_*`)

Didaftarkan manual lewat `config/filament-shield.php` → `entities.custom_permissions`, supaya tetap "dikenal" Shield (muncul di listing dia) walau bukan hasil auto-generate dari Resource CRUD biasa. Tidak ada perubahan pada permission ini — cuma didaftarkan supaya tidak dianggap yatim oleh tooling Shield ke depannya.

### 2.4 Resource Role/Permission bawaan Shield — tidak dipakai

Saat `php artisan shield:install`, ada opsi generate Resource Role (dan kadang Permission). **Pilih tidak / skip generate Resource itu** — tetap pakai `RoleResource`/`PermissionResource` custom yang sudah ada (alasan lengkap di §Keputusan poin 2).

## 3. Dampak ke Portal (Livewire) — Sudah Tidak Ada Risiko Sama Sekali

**Update 2026-07-08**: 4 titik permission-string yang sebelumnya dipakai portal (`edit_karyawan` di `EditMyEmployee.php`/`MyEmployees.php`, `view_struktur_organisasi` di `OrganizationStructure.php`, `create_struktur_organisasi` di `organization-chart-widget.blade.php`, `edit_master_data_jabatan` di `organization-structure.blade.php`) **sudah sengaja dicabut** — sekarang dianggap bisa diakses semua orang yang login, sampai ada keputusan otorisasi portal yang baru (belum dikerjakan, disengaja: "belum mengerjakan di depan").

Konsekuensinya: portal sekarang **0 permission-string check** (`Gate::allows`/`@can`/`->can(` sudah diverifikasi nihil di seluruh `app/Livewire` dan `resources/views`). Jadi bagian §2.2 (kekhawatiran "nama subject harus 100% sama biar portal tidak rusak") **tidak relevan lagi** — migrasi Shield sekarang murni urusan internal `/admin`, tidak ada lagi dependency ke portal yang perlu dijaga.

Yang tersisa di portal (levelnya beda, di luar Spatie permission, tidak tersentuh Shield sama sekali):
- `hasRole(['Admin', 'Super Admin'])` — `nav.blade.php`, `pwa-sidebar.blade.php` (role-check langsung, bukan permission).
- `PermissionHelper::hasGlobalRole()`, `User::canAccessKaryawanMenu()` — guard custom, didaftarkan di `config/portal-pages.php`.
- `ApprovalPermissionService::hasPermission()` — approval engine sendiri, bukan Spatie permission.

## 4. Langkah Eksekusi

### Fase 0 — Spike (wajib, sebelum apa pun yang lain)

1. Install Shield di environment dev/lokal (bukan langsung ke migration production).
2. Coba di **1 Resource kecil** dulu (saran: `ReligionsResource` — paling simpel, subject `master_data_agama`).
3. Jalankan generator, lihat nama permission yang dihasilkan. Konfirmasi:
   - Apakah subject bisa di-override ke `master_data_agama` (match existing) — dan **bagaimana caranya** persis di versi package yang ter-install.
   - Apakah verb yang dihasilkan cuma 4 (`view/create/edit/delete`) setelah config `permission_prefixes` diubah.
4. Kalau override subject **tidak** memungkinkan dengan wajar → putuskan pakai jalur "generate nama baru + migration rename in-place" (§2.2).
5. **Baru lanjut ke fase berikutnya setelah salah satu dari dua jalur di atas terkonfirmasi jalan.**

### Fase 1 — Install & Konfigurasi Dasar

- `composer require bezhansalleh/filament-shield` (cek compatibility tag utuk Filament 4 saat instalasi — jangan asumsikan versi dari dokumen ini).
- `php artisan vendor:publish --tag=filament-shield-config`.
- Set `permission_prefixes.resource` ke 4 verb (§2.1).
- Set `super_admin.name` ke `'Super Admin'` (role yang sudah ada, bukan bikin role baru) — cek juga apakah perlu `super_admin.define_via_gate` supaya tidak bentrok dengan logic `PermissionHelper::hasGlobalRole()` yang sudah ada (role `Super Admin` maupun `Admin` per-company sama-sama dianggap "global" di helper itu — pastikan konsep "super admin" Shield tidak override/bentrok logic ini).
- **Skip** generate Resource Role/Permission bawaan Shield (§2.4).

### Fase 2 — Mapping Subject per Resource

Susun tabel referensi (berdasarkan `$navGroupMap` yang sudah ada di `RoleForm.php:82-145`, itu sudah persis peta subject → grup) untuk tiap Resource yang ikut migrasi:

| Resource | Subject existing (jangan berubah) |
|---|---|
| `EmployeeResource` | `karyawan` |
| `Master/JobPositionResource` | `master_data_jabatan` |
| `Master/DepartmentsResource` | `master_data_departemen` |
| `Master/DivisionsResource` | `master_data_divisi` |
| `Master/SubDivisionsResource` | `master_data_sub_divisi` |
| `Master/PangkatsResource` | `master_data_pangkat` |
| `Master/GolongansResource` | `master_data_golongan` |
| `Master/KelasResource` | `master_data_kelas` |
| `Master/EmployeeTypesResource` | `master_data_jenis_karyawan` |
| `Master/ReligionsResource` | `master_data_agama` |
| `Master/BanksResource` | `master_data_bank` |
| `Master/LeaaveTypesResource` | `master_data_jenis_cuti` |
| `Master/PermissionTypesResource` | `master_data_jenis_perizinan` |
| `Master/OvertimeTypesResource` | `master_data_jenis_lembur` |
| `Master/TaskServiceResource` | `master_data_layanan_penugasan` |
| `Master/CompaniesResource` | `master_data_perusahaan` |
| `AttendanceRecordResource` | `data_absensi` (+ custom `view_report_data_absensi`, `import_data_absensi`) |
| `AttendanceRequestResource` | `pengajuan_absensi` (+ custom `approve_pengajuan_absensi`) |
| `TaskRequestResource` | `penugasan_individu` |
| `ManPowerPlaning/*` cluster | `man_power_planning` |
| `UserTransitions/*` cluster | `perubahan_status_karyawan` |
| `UserDocumentsResource` | `dokumen_karyawan` |
| `ManPower/JobOpeningResource` | `manajemen_lowongan_kerja` (+ custom `approve_manajemen_lowongan_kerja`) |
| `Assessment/*` cluster | `penilaian_karyawan` |
| `ShiftPattern/*` cluster | `master_data_pola_shift`, `template_shift` |
| `UserShiftScheduleResource` | `jadwal_shift` |
| `UserShiftSwapResource` | `manajemen_tukar_shift` |
| `Coachings/CoachingResource` | `coaching` |
| `Counselings/CounselingResource` | `counseling` |
| `Payroll/DoctorFeeSubmissions` *(catatan: nama tabel/resource ini rencananya berubah lewat `planning/2026-07-08-redesign-struktur-payroll.md` — sinkronkan urutan eksekusi, jangan tabrakan)* | `pengajuan_jasa_dokter` |
| `Payroll/TrainerSalaries` | `gaji_trainner` |
| `MasterSalaryComponent/*` cluster | `master_data_komponen_gaji` |
| `Payroll/Payrolls`, `Payroll/DoctorPayrollPeriods` *(idem, ikut redesign payroll)* | `payroll`, `doctor_payroll` |
| `Payroll/ProgramIncentives` | `program_incentive` |
| `LMS/LmsCourses`, dst | `lms` |
| `LMS/QuestionBanks` | `bank_soal` |
| `LMS/Quizzes` | `quiz` |
| `LMS/LmsCoursePlans` | `rencana_pelatihan` |
| `Competency/*` | `jabatan_dan_kompetensi` |
| `ApprovalFlowResource` | `manajemen_persetujuan` |
| `RoleResource` | `role` |
| `PermissionResource` | `permission` |

Catatan: tabel di atas **belum final** — perlu di-cross-check ulang satu-per-satu pas eksekusi (ada kemungkinan 1 Resource meng-cover lebih dari 1 subject, atau sebaliknya, terutama untuk Cluster yang isinya banyak Resource turunan).

### Fase 3 — Terapkan Override Subject (atau Siapkan Migration Rename)

Sesuai hasil Fase 0:
- **Jalur A (override subject berhasil)**: terapkan ke semua Resource di tabel Fase 2. Generate ulang, permission yang dihasilkan harus 100% sama nama dengan yang sudah ada di `PermissionsSeeder` (0 permission baru, 0 rename dibutuhkan).
- **Jalur B (override tidak memungkinkan)**: biarkan Shield generate nama barunya, lalu tulis migration rename in-place (contoh pola, per pasangan lama→baru):
  ```php
  DB::table('permissions')->where('name', 'edit_employee')->update(['name' => 'edit_karyawan']);
  ```
  Dibungkus 1 migration idempotent (cek dulu baris lama ada sebelum update, supaya aman di-run ulang / aman kalau sebagian sudah pernah di-apply).

### Fase 3b — Role "Super Admin" Global (`company_id` NULL)

Sudah dicek ke DB: role `Super Admin` yang ada sekarang **`company_id`-nya terisi** (ID company holding), bukan NULL. Constraint unique tabel `roles` itu `['name', 'guard_name', 'company_id']` (lihat migration `2025_09_11_113956_create_permission_tables.php:31`) — jadi role `Super Admin` dengan `company_id` NULL itu **baris yang benar-benar beda**, tidak bentrok dengan yang sudah ada.

Tambahkan ke migration Fase 4: kalau role `Super Admin` dengan `company_id` NULL **belum ada**, buat baru dan `syncPermissions()` ke **seluruh** permission yang sudah di-generate Shield (bukan cuma subset) — pola yang sama seperti `PermissionsSeeder.php:314-320` (`Role::where('name','Super Admin')->...->syncPermissions($allPermissions)`), tapi query-nya ditambah `whereNull('company_id')` dan `firstOrCreate` dulu kalau belum ada barisnya. Idempotent — aman di-run ulang, tidak menyentuh role `Super Admin` yang sudah terikat ke company holding.

```php
$superAdmin = Role::firstOrCreate(
    ['name' => 'Super Admin', 'guard_name' => 'web', 'company_id' => null]
);
$superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());
```

### Fase 4 — Migration Katalog Permission (Production-Safe)

Baik Jalur A maupun B, hasil akhirnya harus dituangkan ke **1 migration** (bukan artisan command manual yang harus diingat-ingat dijalankan di server — ini persis masalah yang bikin `RoleDefaultPermissionsSeeder` sengaja belum di-run, lihat README Known Gaps):

- Migration `up()` men-generate ulang penuh (panggil logic setara `shield:generate` secara terprogram, atau minimal `Permission::firstOrCreate()` loop untuk seluruh subject di tabel Fase 2 × 4 verb + custom permission) — idempotent, aman di-run ulang, aman untuk `php artisan migrate` standar di pipeline deploy.
- **Tidak** menghapus permission lama sebelum insert yang baru (kalau Jalur A, seharusnya memang tidak ada yang perlu dihapus karena nama sama persis).
- Kalau Jalur B: migration terpisah (jalan **setelah** migration generate) khusus untuk rename in-place, supaya `role_has_permissions` yang sudah live tetap nyambung ke ID permission yang sama (rename, bukan hapus-buat-baru).

### Fase 5 — Ganti `canAccess()`/`Gate::allows()` Manual di Tiap Resource (Kode, Bukan Migration)

- Setelah katalog permission dipastikan cocok (Fase 3-4), baru hapus method `canAccess()`/`canView()`/`canEdit()`/`canDelete()` manual di tiap Resource, ganti pola auto Shield (policy atau trait — tergantung opsi generator yang dipilih di Fase 1: "policies and permissions" vs "permissions only").
- Ini murni perubahan kode (bukan migration), dilakukan **resource per resource**, bukan sekaligus — supaya kalau ada 1 Resource yang subject-nya ternyata tidak match, gampang di-rollback tanpa ganggu Resource lain yang sudah beres.

### Fase 6 — Custom Permission (`approve_*`, `view_report_*`, `import_*`)

Karena bukan hasil auto-generate CRUD, Gate check manual untuk verb-verb ini (misal di `AttendanceRequestResource` untuk approve) **tetap ditulis manual seperti sekarang** — Shield cuma bikin permission-nya "dikenal" (masuk config `custom_permissions`), tidak auto-wiring cek aksesnya karena bukan pola standar dia.

## 5. Verifikasi

- `php artisan migrate` jalan bersih di DB dev (sqlite `:memory:` untuk test — **jangan** arahkan ke DB asli buat uji coba, lihat Known Gaps README).
- Diff isi tabel `permissions` sebelum vs sesudah migration: harus **0 baris hilang**, subject/nama sama persis (Jalur A) atau ter-rename in-place dengan ID tetap sama (Jalur B) — cek lewat `SELECT id, name FROM permissions ORDER BY id` sebelum/sesudah.
- Spot-check role yang sudah live (misal `Admin` di RSU Banjarbaru, `HC Manager` di 2-3 company beda) — pastikan `role_has_permissions` masih nyambung ke permission yang benar setelah migration (jumlah permission per role tidak berubah, kecuali memang ada penambahan permission baru dari Resource yang sebelumnya tidak punya Gate check).
- Login sebagai role non-Super-Admin, cek 1-2 Resource acak di `/admin` — pastikan `canAccess()` versi Shield hasilnya sama dengan yang sebelumnya (yang punya akses tetap bisa masuk, yang tidak tetap ditolak).
- Cek 4 titik permission portal di §3 — pastikan tidak ada perubahan perilaku (user yang bisa `edit_karyawan` sebelumnya, tetap bisa; yang tidak bisa, tetap tidak bisa).
- Cek `RoleForm` (`/admin/roles/{id}/edit`) — pastikan permission baru (kalau ada, dari Resource yang sebelumnya belum punya Gate check sama sekali) muncul di grup yang benar (update `$navGroupMap` kalau ada subject baru yang belum terdaftar di situ, supaya tidak jatuh ke fallback "Ungrouped").
- Cek role `Super Admin` dengan `company_id` NULL sudah ada (`SELECT * FROM roles WHERE name='Super Admin' AND company_id IS NULL`) dan permission-nya lengkap (`COUNT` permission role ini harus sama dengan `COUNT(*) FROM permissions`). Pastikan juga run ulang migration ini tidak duplikat baris (idempotent check).

## 6. Risiko & Catatan Terbuka

- **Risiko terbesar**: mekanisme override subject per-Resource di Shield belum dikonfirmasi persis caranya (§2.2) — makanya Fase 0 (spike di 1 Resource kecil) **wajib** sebelum menyentuh Resource lain, apalagi production.
- Migrasi ini **tidak berurutan wajib** dengan `planning/2026-07-08-redesign-struktur-payroll.md` (redesign payroll), tapi ada overlap Resource (`DoctorFeeSubmissions`, `DoctorPayrollPeriods` akan berubah nama/hilang di redesign payroll) — kalau dua-duanya mau dieksekusi, urutan yang lebih aman: **redesign payroll dulu, baru migrasi Shield** (supaya subject mapping di Fase 2 tidak perlu direvisi dua kali untuk area yang sama).
- Permission `view_data_pribadi`/`edit_data_pribadi` sudah orphan (§1) — di luar scope plan ini, tapi kalau nanti ada Resource baru yang kebetulan butuh subject `data_pribadi` lagi, cek dulu 2 permission ini masih ada isinya di role mana saja sebelum dipakai ulang.

## 8. Hasil Eksekusi Fase 0-4 (2026-07-08)

- **Package**: `bezhansalleh/filament-shield ^4.2` terinstall, kompatibel Filament 4.0. Struktur config-nya beda dari asumsi awal plan (bukan `permission_prefixes.resource`, tapi `permissions.separator`/`permissions.case` + `policies.methods`), tapi ketemu mekanisme kontrol penuh: `FilamentShield::buildPermissionKeyUsing(Closure)` — didaftarkan di `App\Providers\AppServiceProvider::configureShieldPermissionNaming()`, pakai mapping dari `App\Support\Shield\PermissionSubjectMap::resources()`.
- **Subject mapping**: berhasil 100% — 0 rename dibutuhkan (Jalur A dari §2.2/§3 terpakai, bukan Jalur B fallback-rename). Verb `edit` (bukan `update` default Shield) juga berhasil di-maksa lewat closure yang sama.
- **Config final** (`config/filament-shield.php`): `policies.methods` dibatasi 4 verb (`view/create/update/delete`), `resources.manage` override buat `CoachingResource`/`CounselingResource` (3 verb, tanpa delete), `custom_permissions` diisi 5 permission non-CRUD, `pages.exclude` nambah 6 Cluster (supaya tidak generate permission `view_x_cluster` yang tidak akan kepakai — Cluster tetap pakai `canAccess()` manual), `super_admin.name` diarahkan ke `'Super Admin'` (bukan default Shield), `panel_user.enabled` dimatikan (tidak dipakai, akses panel sudah diatur `User::canAccessPanel()`).
- **Migration**: `2026_07_08_224846_sync_filament_shield_permission_catalog.php` — sudah dijalankan (`php artisan migrate`), idempotent (pakai `Artisan::call('shield:generate', ...)` + `firstOrCreate` role). Total permission: **188 → 197** (9 permission baru, bukan rename/hilang).
- **Role Super Admin global** (`company_id` NULL, id=85) sudah dibuat, dapat semua 197 permission.
- **9 permission baru yang ke-generate** — semuanya kasus "Resource yang sebelumnya tidak punya Gate check di action tertentu" (sesuai keputusan yang sudah dikonfirmasi: generate permission-nya tapi TIDAK auto-assign ke role selain Super Admin global):
  - `create_penugasan_individu` — `TaskRequestResource` sebelumnya tidak punya `canCreate()` (create selalu terbuka).
  - `edit_pengajuan_absensi`, `delete_pengajuan_absensi` — `AttendanceRequestResource` sebelumnya tidak punya `canEdit()`/`canDelete()` (selalu terbuka).
  - `view_trainers`, `create_trainers`, `edit_trainers`, `delete_trainers` — `TrainerExternalResource` (model `Trainers`) sebelumnya tidak ada Gate check sama sekali, tidak ada permission existing yang cocok juga (beda dari 15 Resource "terbuka" lainnya yang ternyata sudah ke-cover gate Cluster induknya).
- **6 permission cluster yang sempat ke-generate lalu dihapus**: `view_assessment_cluster`, `view_employee_document_cluster`, `view_man_power_planing_cluster`, `view_master_salary_component_cluster`, `view_shift_pattern_cluster`, `view_user_transitions_cluster` — dari Cluster yang ikut ke-scan sebagai "Page" oleh Shield. Sudah di-exclude di config supaya tidak muncul lagi.
- **Belum dikerjakan**: Fase 5 (hapus `canAccess()`/`canView()`/dst manual di tiap Resource, ganti ke Policy auto-generate Shield) dan Fase 6 (custom permission approve/report/import tetap manual). Ini bagian yang paling besar blast radius-nya (60+ file, ubah perilaku otorisasi per Resource) — sengaja dijadikan checkpoint terpisah, dilakukan resource-per-resource sesuai desain awal §4 Fase 5, bukan sekaligus.

## 7. Referensi

- `app/Filament/Resources/Roles/Schemas/RoleForm.php` — sumber mapping subject→grup navigasi (`$navGroupMap`), dipakai sebagai basis tabel Fase 2.
- `database/seeders/PermissionsSeeder.php` — katalog permission existing lengkap (~100 baris), acuan nama yang tidak boleh berubah.
- `database/seeders/RoleDefaultPermissionsSeeder.php` — pola "sengaja belum dijalankan, nunggu review manual" yang jadi alasan kenapa Fase 4 di plan ini harus lewat migration (bukan artisan command manual).
- `planning/2026-07-08-redesign-struktur-payroll.md` — rencana lain yang beririsan Resource-nya (lihat §6 soal urutan eksekusi).

## 9. Pivot — Pakai Penamaan Default Shield (Bukan Preservasi Nama Lama)

**Keputusan (dikonfirmasi eksplisit oleh user, 2026-07-08 malam)**: batalkan pendekatan "pertahankan nama permission lama" di §2.2/§8. Alasannya: kompleksitas mapping manual (`PermissionSubjectMap`, closure `buildPermissionKeyUsing`) dianggap tidak sepadan — lebih simpel terima konvensi Shield apa adanya.

**Yang dibongkar:**
- `app/Support/Shield/PermissionSubjectMap.php` — **dihapus total**.
- `App\Providers\AppServiceProvider::configureShieldPermissionNaming()` (closure `buildPermissionKeyUsing`) — **dihapus**, `boot()` kembali kosong.
- `config/filament-shield.php` → `permissions.separator`/`permissions.case` — dikembalikan ke default Shield (`':'` / `'pascal'`), bukan `'_'`/`'snake'` yang tadinya dipakai buat menjaga kemiripan penamaan lama.
- `custom_permissions` di config — tetap ada (5 permission non-CRUD approve/report/import), sekarang di-inline langsung di config (bukan lewat class yang sudah dihapus).
- `pages.exclude` (6 Cluster) — **tetap dipertahankan**, itu independen dari isu penamaan.
- `resources.manage` override (Coaching/Counseling 3 verb) — **dihapus juga**, lihat §10 (bug fail-open).

**Awalnya verb dibatasi 4** (`view/create/update/delete`) — **ternyata ini bug, sudah diperbaiki di §10, sekarang balik ke default penuh 12 verb.**

**Hasil generate ulang (`shield:generate --panel=admin --all --option=permissions`)**:
- Total permission: **206 → 422** (setelah reset ke default Shield, ditambah generate penuh).
- Contoh nama baru: `View:Religions`, `Create:Religions`, `Update:Religions`, `Delete:Religions` (dulu: `view_master_data_agama` dkk).
- **~197 permission lama (Indonesia, snake_case) TIDAK dihapus** — masih ada di tabel `permissions`, masih ter-assign ke role di 8 company (`role_has_permissions` tidak disentuh), tapi jadi **orphan** begitu Fase 5 (ganti `canAccess()` ke Policy Shield) dieksekusi — tidak ada lagi Gate check yang membaca nama lama itu.
- Role Super Admin global (`company_id` NULL) sudah di-sync ulang, sekarang punya semua 422 permission.

**Konsekuensi nyata yang perlu diketahui sebelum lanjut Fase 5:**
- Begitu Fase 5 jalan (hapus `canAccess()` manual, Filament otomatis pakai Policy Shield yang baru), **semua role selain Super Admin (global maupun yang company_id lama) kehilangan akses** ke seluruh Resource `/admin` — karena mereka masih pegang permission lama (`view_karyawan`), sedangkan Policy baru cek permission baru (`View:Employee`).
- Ini konsisten dengan pola yang sudah beberapa kali dikonfirmasi user di sesi ini ("aku sudah tidak peduli lagi dengan RoleDefaultPermissionsSeeder", "cabut aja semua permission portal, anggap terbuka dulu") — arahnya memang reset total, bukan migrasi mulus tanpa gangguan.
- Kalau mau ada masa transisi (role lama tetap bisa akses sampai di-assign ulang manual), perlu langkah tambahan di luar plan ini (belum diminta): script yang assign permission baru yang relevan ke role-role yang sebelumnya sudah punya permission lama yang senilai, sebagai baseline sementara.

## 10. Bug Ditemukan & Diperbaiki — `viewAny`/`deleteAny` Fail-Open

Saat eksekusi Fase 5 (hapus `canAccess()`/`canView()`/dst manual di 49 Resource, biar Filament fallback ke Policy Shield), ketemu bug serius dari keputusan trim verb ke 4 di §9:

**Akar masalah**: `canAccess()` bawaan Filament (`vendor/filament/filament/src/Resources/Resource/Concerns/HasAuthorization.php:17-20`) internally manggil `canViewAny()` yang cek ability **`viewAny`** — BUKAN `view`. Trim verb di §9 cuma generate `view/create/update/delete`, tidak ada `viewAny`. Begitu Filament cek ability yang metode-nya tidak ada di Policy (`vendor/filament/filament/src/helpers.php:39-67`), dan strict mode tidak aktif, dia **fail-open** (`Response::allow()` default) — semua Resource jadi bisa diakses SIAPA SAJA, bukan cuma yang punya permission.

Sama halnya `deleteAny` (bulk-delete) — dicek 60 dari 65 Table lewat `DeleteBulkAction`, kena bug yang sama.

**Cara ketemu**: tes fungsional manual (`Auth::login($user)` lalu panggil `EmployeeResource::canAccess()`) — user role "Admin" biasa (bukan Super Admin) hasilnya `true`, padahal `$user->can('View:Employee')` `false`. Ketidakcocokan itu yang mengarah ke root cause.

**Perbaikan**:
- `config/filament-shield.php` → `policies.methods` dikembalikan ke **default penuh Shield (12 method)**: `viewAny, view, create, update, delete, deleteAny, restore, forceDelete, forceDeleteAny, restoreAny, replicate, reorder` — bukan cuma 4. Trim 4-verb yang tadinya dikira "YAGNI" ternyata bukan soal jumlah kosmetik, tapi ability yang beneran dicek Filament di banyak titik.
- `resources.manage` override (Coaching/Counseling dibatasi 3 verb) **dihapus** — semua Resource sekarang seragam pakai default penuh, supaya tidak ada lagi yang "kurang lengkap" Policy-nya dan berisiko fail-open serupa di titik lain yang belum ketauan.
- `app/Policies/**` (65 file) di-generate ulang dari nol (`shield:generate --option=policies_and_permissions`) dengan config baru.
- Migration `2026_07_08_230229_sync_filament_shield_permission_catalog.php` di-rollback & dijalankan ulang (logic-nya sendiri tidak berubah, cuma perlu re-run karena total permission bertambah 422 → 862 setelah 12 verb).
- Diverifikasi ulang: `EmployeeResource::canAccess()` untuk role "Admin" biasa sekarang `false` (benar, sesuai konsekuensi yang sudah disepakati), Super Admin tetap `true`.

**Pelajaran buat siapa pun yang lanjutkan migrasi Shield di project lain**: jangan trim `policies.methods` tanpa cek dulu ability apa saja yang benar-benar dipanggil Filament (`canAccess`→`viewAny`, bulk delete→`deleteAny`, dst) — dan Filament defaultnya **fail-open**, bukan fail-closed, kalau Policy method-nya tidak ada. Kalau mau strict, ada `Filament::isAuthorizationStrict()` yang bisa diaktifkan supaya error eksplisit ketimbang diam-diam allow — worth dipertimbangkan sebagai lapisan keamanan tambahan (belum dipasang di plan ini).

## 11. Fase 5 — Selesai Dieksekusi (2026-07-08 malam)

- 49 file Resource (`app/Filament/Resources/**/*Resource.php` + `app/Filament/Clusters/**/*Resource.php`) sudah dibersihkan dari `canAccess()`/`canView()`/`canCreate()`/`canEdit()`/`canDelete()` manual — total 1272 baris terhapus, 49 file, semua lolos `php -l`.
- Import yang jadi tidak kepakai (`use ...\Gate;`, `use ...\Model;`) ikut dibersihkan otomatis per file (dicek: tidak ada Resource yang masih import tapi tidak pakai).
- Method **custom** yang bukan CRUD standar (`canApprove()` di `AttendanceRequestResource`, masih pakai `Gate::allows('approve_pengajuan_absensi')`) **sengaja tidak disentuh** — itu di luar cakupan Shield (bukan salah satu dari 5 method target), sesuai Fase 6.
- Cluster (`*Cluster.php`, bukan Resource di dalamnya) **tidak disentuh** — tetap pakai `canAccess()` manual sendiri sesuai keputusan di §2.4/Fase 0.
- Diverifikasi lewat `php artisan route:list --path=admin` (tidak ada fatal error) dan tes fungsional login-as-user (§10).
- **Status keseluruhan migrasi Shield sekarang: Fase 0-5 selesai.** Fase 6 (custom permission approve/report/import) sudah otomatis "selesai" karena memang sengaja tidak diubah (tetap manual). Yang tersisa di luar plan ini: proses **assign ulang permission baru ke role-role yang ada di 8 company** — itu kerja terpisah (lewat `/admin/roles`), belum dikerjakan, dan memang belum diminta.
