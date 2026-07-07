# Rencana Perbaikan Backend HRIS — Scoping & Akses (2026-07-03)

## Context

Selama analisis sebelumnya (didokumentasikan di `knowledge/markdown/approval-flow-role-access.md` dan `knowledge/dev/filament-scoping-analysis.md`) ditemukan beberapa masalah nyata di backend yang lebih mendesak diperbaiki dulu daripada lanjut membangun tampilan portal absensi:

1. **Company-scoping tidak konsisten** — banyak Filament Resource (LMS, Assessment Template/Type, Competency, hampir semua sub-resource konfigurasi Payroll, dan `UserShiftScheduleResource` yang bocor total) tidak memfilter `company_id`, padahal sistem ini multi-company (holding + rumah sakit + hotel + agro). Pola yang dipakai sekarang ditulis manual & copy-paste di tiap Resource (`if ($user->company_id && !PermissionHelper::hasGlobalRole($user)) { $query->where('company_id', ...) }`), jadi rawan "lupa" — dan sudah terbukti banyak yang lupa.
2. **`PermissionHelper::hasGlobalRole()` keliru secara desain** — cuma mengecek `$user->company_id === '01J2F0C8KQ9M7D4S8RBX6E1ZVP'` (ID company holding), BUKAN mengecek role. Artinya siapa saja yang kebetulan company-nya holding otomatis bisa lihat semua data lintas company, bukan cuma role tertentu (misal Super Admin).
3. **IDOR (akses lintas record tanpa otorisasi)** di `UserResource` (edit profil karyawan lain lewat URL langsung) dan `UserShiftSwapResource` (lihat/edit pengajuan tukar shift karyawan lain) — `canEdit()` di kedua Resource cuma cek permission blanket, tidak cek kepemilikan record.
4. **Tidak ada visibilitas berbasis hierarki organisasi** — kolom `parent_id` di `m_org_structures` & `m_job_positions` sudah ada di skema tapi tidak pernah dipakai untuk membatasi data (supervisor lihat semua data company, bukan cuma tim bawahannya).
5. **Bug & gap kecil di approval flow**: `scope_type = same_department` tidak benar-benar difilter di `ApprovalPermissionService`/`NotificationService`; 1 job position ("Finance Analyst Staff") salah nunjuk `parent_id` ke company lain; company holding kehilangan flow default-nya (berdampak ke user top-of-hierarchy yang tidak match flow spesifik manapun).

Keputusan yang sudah diambil bareng pemilik project:
- **Hierarki dibangun dinamis** dari `parent_id` di `m_org_structures`/`m_job_positions` (bukan manual assignment seperti approval flow), supaya auto-scale kalau struktur organisasi berubah.
- **Tetap pakai `spatie/laravel-permission`**, jangan diganti library lain. Yang kurang bukan permission engine-nya (spatie sudah cukup untuk "siapa boleh lakukan aksi X") — yang kurang adalah **row-level data scoping** (multi-tenancy per company) dan **query hierarki organisasi**, keduanya domain logic custom yang tidak disediakan library manapun. Solusinya: kode custom kecil di atas Spatie yang sudah ada, bukan dependency baru.
- **Cara eksekusi: pelan-pelan, bertahap, satu model/resource pada satu waktu**, diverifikasi sebelum lanjut ke berikutnya — bukan perubahan besar sekaligus yang berisiko mengganggu fitur yang sudah jalan (holding company punya 24 user aktif, RSU Banjarbaru 460+ user aktif memakai sistem ini).

## Fase 1 — Company-Scoping Root Fix + IDOR (dikerjakan lebih dulu, satu per satu)

### 1a. Trait `BelongsToCompany`

Buat `app/Models/Concerns/BelongsToCompany.php` — trait dengan global scope, meniru pola yang SUDAH ADA dan benar di `app/Models/Permission.php`:

```php
protected static function bootBelongsToCompany()
{
    static::addGlobalScope('company', function (Builder $query) {
        $user = Auth::user();
        if ($user && $user->company_id && !$user->hasRole('Super Admin')) {
            $query->where($query->getModel()->getTable() . '.company_id', $user->company_id);
        }
    });
}
```

- Pakai `hasRole('Super Admin')` (Spatie), BUKAN `PermissionHelper::hasGlobalRole()` — memperbaiki masalah #2 sekaligus.
- **Cara pasang bertahap**: tempel trait ke SATU model dulu (paling aman & paling jelas dampaknya untuk uji: `ShiftScheduleAssignments`, karena `UserShiftScheduleResource` saat ini bocor TOTAL — tidak ada filter apa pun, jadi menambah trait di sini murni perbaikan, risiko regresi minim). Verifikasi dulu (lihat bagian Verifikasi), baru lanjut ke model berikutnya satu-satu: LMS courses & terkait → Assessment Template/Type → Competency → model salary component di `app/Models/Payroll/`.
- Untuk Resource yang SUDAH benar filter manualnya (mis. `AttendanceRequestResource`, `PayrollResource`) — **jangan disentuh dulu** di fase ini, biar tidak ada risiko ganda/konflik pada bagian yang sudah jalan. Migrasi ke trait untuk Resource yang sudah benar bisa menyusul belakangan sebagai cleanup, bukan prioritas.

### 1b. Fix IDOR / kebocoran akses

- `app/Filament/Resources/Users/UserResource.php` — `canEdit()`/`canView()` cek `$record->id === Auth::id()`. Diverifikasi: permission `edit_data_pribadi`/`view_data_pribadi` dipegang 29 dari 84 role (broad, self-service) — restriksi kepemilikan aman diterapkan. **Selesai.**
- `app/Filament/Resources/UserShiftSwaps/UserShiftSwapResource.php` — **koreksi dari rencana awal**: setelah dicek data (`role_has_permissions`), permission `view_manajemen_tukar_shift`/`edit_manajemen_tukar_shift` ternyata cuma dipegang 4 role manajerial (Super Admin, Direktur, HC Manager, Supervisor/Kepala Ruangan) per company, BUKAN broad self-service seperti `edit_data_pribadi`. Restriksi ke kepemilikan (requester/target) akan merusak alur approval manajer (mereka memang harus lihat/proses pengajuan bawahan, bukan cuma milik sendiri). Masalah sebenarnya adalah model `ShiftSwapRequests` tidak company-scoped sama sekali — HC Manager company A bisa akses shift-swap company B. **Fix yang diterapkan: pasang trait `BelongsToCompany` ke model `ShiftSwapRequests`** (bukan ownership check di Resource). Diverifikasi lewat simulasi query: user non-Super-Admin cuma dapat data company sendiri. **Selesai.**
- Dua fix ini independen dari sisa model lain di 1a, sudah dikerjakan & diverifikasi.

### 1c. Bug tambahan yang ditemukan saat eksekusi: role "Super Admin" duplikat nama lintas company

`database/seeders/RoleSeeder.php` ternyata membuat role **"Super Admin" per company** (8 baris, masing-masing dikasih 184 permission via `RolePermissionSeeder.php`), dengan niat jadi "admin lokal per company". Tapi Spatie `hasRole()` cuma cek `name` + `guard_name`, TIDAK peduli kolom custom `company_id` di tabel `roles` (fitur `teams` Spatie juga tidak diaktifkan — `config/permission.php: 'teams' => false`). Akibatnya 8 role yang sama-sama bernama "Super Admin" itu tidak bisa dibedakan sistem — user "Super Admin" di RSU Banjarbaru/Barabai ikut ter-treat sebagai superadmin global oleh `PermissionHelper::hasPermission()` (bug lama, sudah ada sebelum sesi ini) maupun trait `BelongsToCompany` yang baru dibuat.

**Fix yang diterapkan**: dijalankan dulu manual via tinker untuk verifikasi cepat di lokal, lalu diresmikan jadi **migration** `database/migrations/2026_07_03_140000_rename_company_scoped_super_admin_roles_to_admin.php` (BUKAN seeder — sesuai keputusan user untuk tidak menjalankan seeder lagi) supaya bisa dijalankan otomatis di production lewat `php artisan migrate` biasa, reversible via `down()`.

7 role di-rename dari "Super Admin" → "Admin" (tetap simpan 184 permission & tetap company-scoped by design). Cuma role holding (id=1) yang tetap "Super Admin" — itu satu-satunya yang sekarang bener-bener bypass company-scope. Sudah diverifikasi: `hasRole('Super Admin')` cuma match 2 user holding, user "Admin" RSU Banjarbaru sekarang `hasRole('Admin')=YA` / `hasRole('Super Admin')=TIDAK`.

**Belum disentuh (menunggu keputusan/di luar scope sesi ini)**: seeder `RoleSeeder.php`/`RolePermissionSeeder.php` masih akan membuat "Super Admin" per company kalau di-seed ulang — tidak diperbaiki karena user memutuskan tidak akan seed ulang lagi sama sekali. Kalau suatu saat butuh provisioning company baru, cara manual (bukan lewat seeder ini) perlu disiapkan.

### 1d. Progres pemasangan trait per model (2026-07-03)

Model yang sudah dapat `BelongsToCompany` (company_id wajib, non-nullable) + diverifikasi lewat simulasi query:
- `ShiftScheduleAssignments`, `ShiftSwapRequests` (batch pertama)
- `PayrollPeriod`, `AssessmentTemplates`, `ProgramIncentive`, `LmsCourseBaselines`, `ExtraHourAllowanceRules` (batch kedua)

Model yang di-scope TANPA migration (lewat relasi ke parent yang sudah company-scoped, pola sama seperti `AttendanceRecords`):
- `AssessmentUsers` — scope custom `whereHas('assessments', ...)` karena tabel ini tidak punya `company_id` sendiri, tapi relasi ke `assessments` (yang sudah company-scoped) cukup untuk membatasi data.

Model yang butuh **migration tambah kolom** (`m_doctor_services`, `m_salary_components`) — dikerjakan via migration `2026_07_03_141000_add_nullable_company_id_to_master_doctor_services_and_salary_components.php`:
- **Temuan penting sebelum eksekusi**: data yang ada di kedua tabel ini ternyata sudah dipakai bersama oleh holding & RSU Banjarbaru (payroll/jasa dokter dari 2 company sama-sama mereferensikan baris yang sama). Kolom `company_id` dibuat **nullable**, data lama TIDAK di-backfill (dibiarkan `NULL` = "berlaku untuk semua company", perilaku existing tidak berubah). Dibuat trait baru `app/Models/Concerns/BelongsToCompanyOrShared.php` (varian dari `BelongsToCompany`: baris `company_id IS NULL` selalu ikut kelihatan, baris yang company_id-nya terisi cuma kelihatan company itu + Super Admin).
- Sudah diverifikasi: data lama tetap kelihatan di semua company (shared), dan simulasi 1 baris company-specific berhasil tersembunyi dari company lain.

Kategori yang **ditahan dulu** (kemungkinan memang shared/global taxonomy by design, bukan bug — menunggu keputusan): `lms_categories`, `m_competencies`, `assessment_types`. Belum diinvestigasi lebih lanjut: `lms_question_banks`, `lms_quizzes` (terhubung ke `lms_categories` yang statusnya masih ditahan).

## Fase 2 — Data Hygiene Approval Flow (setelah Fase 1 stabil)

1. Perbaiki `parent_id` job position "Finance Analyst Staff" (holding) yang salah nunjuk ke company RSU Banjarbaru.
2. Perbaiki bug `scope_type = same_department` tidak difilter di `ApprovalPermissionService::checkRolePermission()` dan `NotificationService::getFirstApproverFromSnapshot()`.
3. Tambahkan flow `default` untuk company holding (hilang) dan 6 company lain yang belum punya flow `attendance_request` sama sekali, supaya tidak ada user (termasuk top-of-hierarchy) yang exception saat submit.

## Fase 3 — Hierarki Organisasi Dinamis (fitur baru, effort terbesar, dikerjakan terakhir)

1. Buat `app/Services/Organization/OrgHierarchyService.php` dengan `subordinateUserIds(User $user): Collection` — reuse relasi `parent()`/`children()` yang sudah ada di `JobPositions` & `OrgStructure` (jangan tulis ulang traversal manual).
2. Pakai di Policy (otorisasi aksi) dan query scope tambahan di Resource terkait (AttendanceRequest, Task, Coaching, Counseling), di atas company-scope dari Fase 1.
3. Cache hasil traversal per-request saja (`once()`/static cache) — jangan pasang Redis/cache layer baru di fase ini.

## File-File Kunci

| File | Perubahan |
|---|---|
| `app/Models/Concerns/BelongsToCompany.php` | **Baru** — trait global scope company |
| `app/Helpers/PermissionHelper.php` | Perbaiki/pensiunkan `hasGlobalRole()` |
| `app/Models/Shift/ShiftScheduleAssignments.php` | Model pertama yang dapat trait (paling aman diuji) |
| Model LMS/Assessment/Payroll lain | Menyusul satu-satu setelah verifikasi model pertama |
| `app/Filament/Resources/Users/UserResource.php` | Fix `canEdit()`/`canView()` |
| `app/Filament/Resources/UserShiftSwaps/UserShiftSwapResource.php` | Fix `canEdit()`/`canView()` |
| `app/Services/Approval/ApprovalPermissionService.php`, `app/Services/NotificationService.php` | Fix filter `same_department` |
| `m_job_positions`, `m_approval_flows`/`m_approval_flow_steps` (data) | Fix data hygiene |
| `app/Services/Organization/OrgHierarchyService.php` | **Baru** — traversal hierarki dinamis |

## Verifikasi

- **Tiap model yang dapat trait**: login sebagai user company A (bukan Super Admin), buka Resource terkait, pastikan cuma data company A yang tampil; login sebagai Super Admin, pastikan tetap lihat semua company.
- **IDOR**: coba akses `/admin/users/{id-user-lain}/edit` sebagai user biasa — harus ditolak (403).
- **Fase 2**: query tinker audit ulang (cross-company `parent_id`, company tanpa flow aktif) — pastikan 0 hasil.
- **Fase 3**: login sebagai user dengan bawahan, listing AttendanceRequest cuma tampilkan request tim-nya.
