# Planning: Redesign Payroll — Simplifikasi Nama, Provenance `payroll_items`, Unifikasi Doctor — 2026-07-12

Status: **dieksekusi penuh (migration + kode), plus lanjutan besar di layer Filament UI yang tidak tercakup rencana awal — lihat "Addendum: Redesign Filament UI (lanjutan sesi yang sama)" di paling bawah dokumen ini untuk apa yang berubah dari desain awal.**

Lanjutan dari [`planning/2026-07-08-redesign-struktur-payroll.md`](2026-07-08-redesign-struktur-payroll.md) (unifikasi doctor→payroll) — digabung dengan 2 permintaan baru dari diskusi 2026-07-12: simplifikasi nama tabel/model yang membingungkan (prefix `m_`/`Master` yang nyaris kembar), dan penjelas sumber tiap baris `payroll_items` (dari salary component, insentif, dst). Dokumen ini menggantikan rencana lama sebagai rencana eksekusi tunggal — jangan eksekusi `2026-07-08-redesign-struktur-payroll.md` secara terpisah, sudah termasuk di sini dengan penyesuaian.

**Penyesuaian dari rencana lama** (karena kerjaan sesi 2026-07-11): referensi `MasterSalaryComponentCluster` sudah tidak berlaku (cluster dihapus, Resource-nya pindah ke `app/Filament/Resources/` flat — lihat `knowledge/changes/2026-07-11-cleanup-shield-permission-dan-portal-dokumen.md`). Bagian permission juga diganti total: **tidak ada lagi** `PermissionsSeeder.php`/`RoleDefaultPermissionsSeeder.php` (sudah dihapus) atau rename manual nama permission — format permission sekarang Shield (`Verb:Subject`), jadi cukup jalankan ulang `shield:generate` di akhir, permission lama otomatis orphan (konsisten dengan keputusan: 77 role sedang 0 permission, di-assign ulang manual belakangan oleh user).

## Kenapa Sekarang (gabungan 3 alasan)

1. **Modul payroll belum dipakai produksi** (76 `payrolls`, 75% kosong, semua data jasa dokter dummy — lihat `knowledge/markdown/analisis-payroll-2-redesign-struktur.md`) — momen paling murah untuk rombak struktur sebelum ada data nyata terikat.
2. **Penamaan `m_salary_components` vs `salary_components` membingungkan** — dua tabel beda peran (katalog vs assignment) yang namanya nyaris kembar, cuma beda prefix `m_`. Sudah pernah direkomendasikan diperbaiki di `analisis-payroll-2-redesign-struktur.md` §3.1, belum pernah dieksekusi.
3. **`payroll_items` snapshot buta** — tidak ada cara tahu 1 baris di slip gaji asalnya dari mana (komponen jabatan? insentif? lembur?) tanpa baca kode. User minta ada penjelas.

## Ruang Lingkup

- **A. Simplifikasi nama**: `m_salary_components`/`MasterSalaryComponents` → `salary_components`/`SalaryComponents`; `salary_components`/`SalaryComponents` (assignment) lama → `salary_component_assignments`/`SalaryComponentAssignments`.
- **B. Rename `ProgramIncentive` → `Incentive`**: tabel, kolom, model, Resource, Policy.
- **C. Provenance `payroll_items`**: kolom baru `source_type` + enum, diisi konsisten di semua handler `PayrollService`.
- **D. Unifikasi doctor→payroll**: hapus 6 tabel `doctor_*`, ganti `fee_submissions`/`fee_items` generik, dokter ikut siklus `payrolls`/`payroll_items` biasa.
- **G. Rename baris katalog "Leave" → "Cuti"**: baris `CUTI` di `m_salary_components`/`SalaryComponentNameEnum` ternyata namanya kepeleset bahasa Inggris sejak awal, dirapikan.
- **H. Hapus baris katalog "Lembur" yatim**: tidak pernah dipakai (`PayrollService::handleOvertimeComponent()` generate baris lembur langsung, tanpa lookup ke katalog ini), dikonfirmasi 0 baris assignment yang mereferensikannya — aman dihapus.
- **I. Halaman admin baru untuk `job_evaluation_companies`**: tabel ini (skor JE total per company per bulan, dipakai `handleServiceFeeComponent()` buat itung rasio Jasa Pelayanan) sekarang **sama sekali tidak ada UI-nya** — cuma bisa diisi lewat tinker/seeder. Dibuatkan Resource Filament baru.
- Sekalian menutup 2 bug lama (root-cause, bukan tambal): `getMasterComponent()` tidak filter `company_id` (mati kalau dijalankan lewat queue worker), dan PDF slip dokter manggil kolom `employee_number` yang tidak ada (jadi tidak relevan lagi setelah D, karena slip dokter pakai `payroll.blade.php` biasa).

**Yang SENGAJA tidak diubah** (biar scope tetap terkendali, "jangan yang aneh-aneh"): `TrainerScoreRanges`, `TrainerScoreVariables`, `ShiftOvertimeCosts` (Overtime Rates), `ExtraHourAllowanceRules` — 4 Resource ini historisnya nempel di cluster yang sama dengan salary component, TAPI modelnya sama sekali beda (bukan `MasterSalaryComponents`/`SalaryComponents`), jadi tidak ikut rename apa pun di sini.

---

## A. Simplifikasi Nama Salary Component

| Peran | Nama sekarang | Nama baru |
|---|---|---|
| Katalog master (tabel) | `m_salary_components` | **`salary_components`** |
| Katalog master (model) | `App\Models\Payroll\MasterSalaryComponents` | **`App\Models\Payroll\SalaryComponents`** |
| Assignment/pivot (tabel) | `salary_components` | **`salary_component_assignments`** |
| Assignment/pivot (model) | `App\Models\Payroll\SalaryComponents` | **`App\Models\Payroll\SalaryComponentAssignments`** |

### A.1 Migration
1 migration baru, urutan **wajib** begini (hindari tabrakan nama di tengah jalan):
1. `RENAME TABLE salary_components TO salary_component_assignments` (assignment lama, duluan supaya nama `salary_components` kosong).
2. `RENAME TABLE m_salary_components TO salary_components` (katalog master, sekarang aman pakai nama itu).

MySQL/InnoDB otomatis menjaga FK constraint ikut nama tabel baru (tidak perlu drop+recreate FK manual). Nama kolom (`salary_component_id`, `salaryable_type/id`, dll) **tidak berubah** — cuma nama tabel & class.

### A.2 Model
- `app/Models/Payroll/MasterSalaryComponents.php` → rename file & class jadi `SalaryComponents.php`/`class SalaryComponents`, `protected $table = 'salary_components'`.
- `app/Models/Payroll/SalaryComponents.php` (assignment lama) → rename file & class jadi `SalaryComponentAssignments.php`/`class SalaryComponentAssignments`, `protected $table = 'salary_component_assignments'`. Relasi `salaryComponent(): BelongsTo` di dalamnya tetap sama nama method-nya, cuma target class-nya ganti ke `SalaryComponents::class` (yang baru).
- Method relasi `salaryComponents()`/`fixedSalaryComponents()`/`percentageSalaryComponents()`/`selectedSalaryComponent()` di `Companies`, `Pangkats`, `JobPositions` (`app/Models/Master/*.php`) — **nama method TIDAK berubah** (masih jelas dibaca "company punya salaryComponents"), cuma `use App\Models\Payroll\SalaryComponents;` diganti jadi `use App\Models\Payroll\SalaryComponentAssignments;` dan `morphMany(SalaryComponents::class, ...)` → `morphMany(SalaryComponentAssignments::class, ...)`.

### A.3 File yang mereferensikan `MasterSalaryComponents` (14 file, semua perlu update `use`/type-hint)
`app/Filament/Resources/Payroll/GeneralSalaryComponents/GeneralSalaryComponentResource.php`, `app/Policies/Payroll/MasterSalaryComponentsPolicy.php` (lihat §A.5), `app/Services/Payroll/PayrollService.php`, `app/Services/Payroll/DoctorPayrollService.php` (dihapus di bagian D, jadi tidak perlu diupdate), `app/Models/Payroll/SalaryComponents.php`, `app/Models/Payroll/MasterSalaryComponents.php` (direname), `app/Filament/Resources/Payroll/PangkatSalaryComponents/Tables/PangkatSalaryComponentsTable.php`, `.../Schemas/PangkatSalaryComponentForm.php`, `app/Filament/Resources/Payroll/JobPositionSalaryComponents/Tables/SalaryComponentsTable.php`, `.../Schemas/SalaryComponentsForm.php`, `app/Filament/Resources/Payroll/CompanySalaryComponents/Tables/CompanySalaryComponentsTable.php`, `.../Schemas/CompanySalaryComponentForm.php`, `app/Filament/Resources/Master/JobPositions/Schemas/JobPositionForm.php`, `app/Filament/Resources/ManPower/JobPositions/Schemas/JobPositionForm.php`.

### A.4 File yang mereferensikan `SalaryComponents` (assignment, 6 file)
`app/Filament/Resources/Payroll/GeneralSalaryComponents/GeneralSalaryComponentResource.php` (dua-duanya kepakai di file ini, hati-hati), `app/Models/Payroll/SalaryComponents.php` (direname), `app/Models/Payroll/MasterSalaryComponents.php`, `app/Models/Master/Pangkats.php`, `app/Models/Master/JobPositions.php`, `app/Models/Master/Companies.php`.

### A.5 Policy & Filament label
- `app/Policies/Payroll/MasterSalaryComponentsPolicy.php` — **hapus**, biarkan Shield generate ulang `SalaryComponentsPolicy` (nama baru otomatis mengikuti nama model) lewat `shield:generate` di langkah akhir (§F).
- Tidak ada perubahan struktur Resource (`GeneralSalaryComponents`, `CompanySalaryComponents`, `PangkatSalaryComponents`, `JobPositionSalaryComponents`) — biarkan nama folder/Resource-nya seperti sekarang (sudah cukup jelas, "Company/Pangkat/JobPosition Salary Component"), cuma isi filenya yang update referensi model.

---

## B. Rename `ProgramIncentive` → `Incentive`

| | Lama | Baru |
|---|---|---|
| Tabel | `program_incentives` | **`incentives`** |
| Kolom | `program_name` | **`name`** |
| Model | `App\Models\Incentive\ProgramIncentive` | **`App\Models\Incentive\Incentive`** |
| Resource folder | `app/Filament/Resources/Payroll/ProgramIncentives/` | **`app/Filament/Resources/Payroll/Incentives/`** |
| Resource class | `ProgramIncentiveResource` | **`IncentiveResource`** |
| Policy | `app/Policies/Incentive/ProgramIncentivePolicy.php` | dihapus, di-generate ulang Shield jadi `IncentivePolicy` |

### B.1 Migration
1 migration: `RENAME TABLE program_incentives TO incentives` + `RENAME COLUMN program_name TO name` (2 statement, aman digabung 1 migration karena beda operasi).

### B.2 File yang kena dampak (11 file, dari grep)
- `app/Models/Incentive/ProgramIncentive.php` → rename jadi `Incentive.php`, `protected $table = 'incentives'`, `fillable` ganti `program_name` → `name`.
- `app/Policies/Incentive/ProgramIncentivePolicy.php` → hapus (lihat §F).
- `app/Filament/Resources/Payroll/ProgramIncentives/` (folder) → rename ke `Incentives/`, isi:
  - `ProgramIncentiveResource.php` → `IncentiveResource.php` (class + namespace + `$model` + `getPages()` reference ke Page class baru)
  - `Pages/CreateProgramIncentive.php` → `Pages/CreateIncentive.php`
  - `Pages/EditProgramIncentive.php` → `Pages/EditIncentive.php`
  - `Pages/ListProgramIncentives.php` → `Pages/ListIncentives.php`
  - `Pages/ViewProgramIncentive.php` → `Pages/ViewIncentive.php`
  - `Schemas/ProgramIncentiveForm.php` → `Schemas/IncentiveForm.php` (field `program_name` → `name`, label "Nama Program" tetap boleh sama karena itu label form, cuma nama kolomnya yang berubah)
  - `Schemas/ProgramIncentiveInfolist.php` → `Schemas/IncentiveInfolist.php`
  - `Tables/ProgramIncentivesTable.php` → `Tables/IncentivesTable.php`
- `app/Services/Payroll/PayrollService.php` — `use App\Models\Incentive\ProgramIncentive;` → `use App\Models\Incentive\Incentive;`, method `handleProgramIncentiveComponent()` → `handleIncentiveComponent()`, query `ProgramIncentive::query()` → `Incentive::query()`, `->pluck('program_name')` → `->pluck('name')`, nama baris `payroll_items` yang tadinya `'Program Incentive'` → **`'Incentive'`** (sesuai permintaan, istilahnya dipangkas).

---

## C. Provenance `payroll_items` (`source_type`)

### C.1 Migration
Tambah kolom `source_type` (string, **nullable** — biar baris lama/manual yang belum ke-tag tidak error) di `payroll_items`.

### C.2 Enum baru
`app/Enum/Salary/PayrollItemSourceEnum.php` (namespace sama dengan `SalaryComponentNameEnum`/`CalculationTypeEnum` yang sudah ada, konsisten):
```php
enum PayrollItemSourceEnum: string
{
    case BASE_SALARY = 'base_salary';                 // gaji pokok (pangkat)
    case JOB_POSITION_ASSIGNMENT = 'job_position_assignment'; // komponen tetap/persentase jabatan
    case ATTENDANCE = 'attendance';                    // cuti/izin/lembur/telat/kelebihan jam kerja
    case SERVICE_FEE = 'service_fee';                  // jasa pelayanan (JE ratio)
    case INCENTIVE = 'incentive';                       // insentif
    case FEE_CLAIM = 'fee_claim';                       // klaim fee (dokter, dst — dari bagian D)
    case MANUAL = 'manual';                              // adjustment manual admin (relation manager)

    public function label(): string { /* ... label Indonesia per case ... */ }
}
```

### C.3 `PayrollService.php` — tag tiap `PayrollItem::create()`
| Method | `source_type` |
|---|---|
| `handleBaseSalary()` | `BASE_SALARY` |
| `handleJobPositionFixedComponents()` | `JOB_POSITION_ASSIGNMENT` |
| `handleJobPositionPercentageComponents()` | `JOB_POSITION_ASSIGNMENT` |
| `handleLeaveComponent()` / `handlePermissionComponent()` / `handleLateArrivalComponent()` (lewat `createPayrollItemFromMaster()`, tambah 1 parameter) | `ATTENDANCE` |
| `handleOvertimeComponent()` | `ATTENDANCE` |
| `handleExtraHourAllowanceComponent()` | `ATTENDANCE` |
| `handleIncentiveComponent()` (dulu `handleProgramIncentiveComponent`) | `INCENTIVE` |
| `handleServiceFeeComponent()` | `SERVICE_FEE` |
| `handleFeeItemsComponent()` (baru, dari bagian D) | `FEE_CLAIM` |
| "Add Adjustment" di `PayrollItemsRelationManager` (`mutateFormDataUsing`) | `MANUAL` |

Sekalian fix bug `getMasterComponent(SalaryComponentNameEnum $nameEnum)` → tambah param `string $companyId`, filter `where('company_id', $companyId)->orWhereNull('company_id')`, `orderByRaw('company_id IS NULL')` (company-specific menang). Update 4 pemanggil (`handleLeaveComponent`, `handlePermissionComponent`, `handleLateArrivalComponent`, `handleServiceFeeComponent`) supaya kirim `$period->company_id`.

### C.4 Tampilan
- `PayrollItemsRelationManager.php` (`app/Filament/Resources/Payroll/Payrolls/RelationManagers/`) — tambah `TextColumn::make('source_type')->badge()->formatStateUsing(fn($state) => PayrollItemSourceEnum::tryFrom($state)?->label() ?? '-')`.
- **Tidak** ditambahkan ke PDF slip (`pdf/payroll/payroll.blade.php`) — itu dokumen buat karyawan, taksonomi internal admin tidak perlu tampil di situ. Kalau ternyata mau ditambahkan juga, bilang belakangan.

---

## D. Unifikasi Doctor → Payroll

(Isi sama dengan `planning/2026-07-08-redesign-struktur-payroll.md`, disalin & disesuaikan di sini supaya 1 dokumen jadi satu-satunya rujukan eksekusi.)

**Dihapus total**: `m_doctor_services`, `doctor_fee_submissions`, `doctor_fee_items`, `doctor_payroll_periods`, `doctor_payrolls`, `doctor_payroll_items`, `master_doctor_service_categories` (orphan) + model-model di `app/Models/DoctorService/` + `app/Services/Payroll/DoctorPayrollService.php` + `app/Jobs/GenerateDoctorPayrollJob.php` + `app/Filament/Resources/Payroll/MasterDoctorServices/` + `app/Filament/Resources/Payroll/DoctorPayrollPeriods/` + `resources/views/pdf/payroll/doctor-payroll.blade.php` + `database/seeders/DoctorServiceCategorySeeder.php`.

**Baru**: `fee_submissions` (`id`, `company_id` wajib, `reference_number`, `subject_name`, `service_type`, `status`, `notes`, `date`, `created_by` nullable), `fee_items` (`id`, `submission_id` FK, `user_id` penerima, **`salary_component_id`** FK → `salary_components` **yang sudah di-rename di bagian A** — nullable, `is_manual` bool, `name` snapshot wajib, `amount`, `is_paid`, `notes`).

Model baru: `App\Models\Payroll\FeeSubmission`, `App\Models\Payroll\FeeItem`.

`PayrollService::handleFeeItemsComponent()` — pola sama `handleIncentiveComponent()` tapi **1 baris per item** (bukan digabung), tag `source_type = FEE_CLAIM`, plus 1 baris deduction pajak (`getMasterComponent(SalaryComponentNameEnum::DOCTOR_TAX, $period->company_id)`, versi yang sudah difix §C.3) kalau total fee > 0. Tandai `FeeItem.is_paid = true` setelah diproses.

Filament: rename+rebuild `DoctorFeeSubmissions/` → `FeeSubmissions/` (`FeeSubmissionResource`, `navigationGroup = 'Keuangan'`), label digeneralisasi ("No SIP"→"No Referensi", "Nama Pasien"→"Nama/Keterangan", "Dokter"→"Penerima"), dropdown master dari `SalaryComponents` (nama baru, type=earning) bukan `MasterDoctorService`. `PayrollsTable.php` — hapus dead import `App\Models\Payroll\DoctorPayroll`.

3 baris `m_doctor_services` lama (Operator, Anestesi, konsultasi) — kalau mau dipertahankan, ditambahkan manual ke `salary_components` (nama baru) lewat `GeneralSalaryComponents` setelah migration jalan, bukan migration data otomatis (cuma 3 baris).

---

## G. Rename Baris Katalog "Leave" → "Cuti"

Ditemukan saat inspeksi data (2026-07-12): baris komponen Cuti di `m_salary_components` namanya `"Leave"` (bahasa Inggris), beda sendiri dari baris lain yang semua bahasa Indonesia (Izin, Keterlambatan, dst). Dikonfirmasi ini memang komponen Cuti yang sama (`SalaryComponentNameEnum::CUTI = 'Leave'`), bukan baris nyasar — cuma penamaan yang kepeleset dari awal.

- `app/Enum/Salary/SalaryComponentNameEnum.php` — `case CUTI = 'Leave';` → `case CUTI = 'Cuti';`.
- Data-fix (bagian dari migration di §E langkah 3, dijalankan setelah `salary_components` sudah jadi nama tabel katalog yang baru): `UPDATE salary_components SET name = 'Cuti' WHERE name = 'Leave';`

## H. Hapus Baris Katalog "Lembur" Yatim

Baris `"Lembur"` di `m_salary_components` tidak pernah dipakai — `SalaryComponentNameEnum` sudah comment-out case-nya (`// case LEMBUR = 'Lembur'; // tidak dipakai, lembur dihitung langsung dari OvertimeRateRules`), dan `PayrollService::handleOvertimeComponent()` memang generate `PayrollItem` lembur langsung tanpa lookup ke katalog ini sama sekali. Dikonfirmasi (2026-07-12): **0 baris** `salary_components` (assignment) yang mereferensikan baris ini via `salary_component_id` — aman dihapus tanpa masalah FK.

- Data-fix (bagian dari migration §E langkah 3): `DELETE FROM salary_components WHERE name = 'Lembur';`

## I. ~~Halaman Admin Baru~~ — TERNYATA SUDAH ADA, dibatalkan

**Koreksi (2026-07-12, saat eksekusi)**: klaim awal "tidak ada Resource Filament yang mengelola `job_evaluation_companies`" **salah** — riset awal cuma grep nama class `JobEvaluationCompanies` sebagai import langsung, padahal sudah ada `app/Filament/Resources/Master/Companies/RelationManagers/JobEvaluationsRelationManager.php` yang cukup lengkap (cegah duplikat bulan per tahun, label bulan Indonesia), terdaftar di `CompaniesResource::getRelations()`, diakses lewat `/admin` → Companies → edit → tab "Nilai Job Evaluation Perusahaan". Tidak jadi bikin Resource baru — sudah ada, tidak perlu duplikasi UI.

Catatan yang masih relevan (bukan bug, cuma keterbatasan desain existing): tabel di RelationManager itu di-filter `where('year', date('Y'))` (tahun berjalan saja) — data tahun lalu (mis. Desember 2025) tidak kelihatan lewat UI itu kalau sekarang sudah lewat tahunnya. Di luar scope untuk diperbaiki sekarang.

---

## J. Eligibility Tipe Karyawan (bisa lebih dari 1) di `salary_component_assignments`

Menutup gap "tidak ada syarat status kepegawaian" (`analisis-payroll.md` §5.1/§6) — tapi bentuknya **many-to-many** (bisa pilih beberapa tipe sekaligus per assignment), bukan 1 kolom FK nullable seperti rekomendasi awal.

- Tabel pivot baru: `salary_component_assignment_employee_types` (`assignment_id` FK → `salary_component_assignments`, `employee_type_id` FK → `m_employee_types`).
- **Tidak ada baris pivot** untuk 1 assignment = berlaku semua tipe karyawan (default, 22 baris assignment yang sudah ada otomatis tetap berlaku semua tipe, tidak ada perubahan perilaku).
- **Ada baris pivot** = cuma berlaku untuk tipe yang disebut.
- Model `SalaryComponentAssignments` (nama baru dari §A) — tambah `belongsToMany(EmployeeTypes::class, 'salary_component_assignment_employee_types', 'assignment_id', 'employee_type_id')`.
- `PayrollService::handleJobPositionFixedComponents()`/`...Percentage...()` — tambah filter: skip komponen kalau `$component->employeeTypes->isNotEmpty()` DAN `$user->profile->employee_type_id` tidak ada di daftar itu.
- Filament: form `CompanySalaryComponentForm`/`PangkatSalaryComponentForm`/`SalaryComponentsForm` (JobPosition) — tambah `Select::make('employee_type_ids')->multiple()->relationship('employeeTypes', 'name')`, opsional (kosong = semua tipe).

**Catatan tambahan (temuan sampingan, bukan bagian rencana ini)**: `m_employee_types` sekarang ada duplikat mencurigakan ("Karyawan Tetap" 14 user vs "Tetap" 1 user, "Karyawan Kontrak" 7 user vs "Kontrak"/"Freelance"/"Outsorcing" 0 user) — kolom "Jumlah Karyawan" sudah ditambahkan di `/admin` (`EmployeeTypesTable.php`, 2026-07-12) buat bantu user bersihkan manual. Tidak dieksekusi otomatis di sini.

## K. Fix Query Gaji Pokok (`handleBaseSalary()`)

Bug ditemukan 2026-07-12: `$user->profile?->pangkat?->salaryComponents()->first()` ambil baris assignment PERTAMA apa saja milik pangkat itu, **tanpa filter nama "Gaji Pokok"** — aman sekarang cuma karena konvensi (pangkat memang selalu cuma di-assign 1 komponen), tapi rapuh kalau nanti ada pangkat yang di-assign komponen lain juga. Fix (bagian dari rewrite `PayrollService.php` di §C, bukan tabel baru — sudah didiskusikan opsinya, cara ini yang dipilih):

```php
$baseSalaryComponent = $user->profile?->pangkat?->salaryComponents()
    ->whereHas('salaryComponent', fn ($q) => $q->where('name', SalaryComponentNameEnum::GAJI_POKOK->value))
    ->first();
```

**Ditolak/ditunda** (dibahas, sengaja tidak dieksekusi): tabel history/audit trail untuk perubahan nilai assignment — modul belum produksi (tidak ada data nyata buat diaudit), dan `payroll_items` per periode sudah berfungsi sebagai snapshot histori yang lebih relevan. Kalau nanti benar-benar dibutuhkan, pakai package generik (`spatie/laravel-activitylog`) buat semua tabel penting sekaligus, bukan tabel history khusus 1 tabel.

**Dikonfirmasi tidak perlu perubahan**: `company_id` di `m_salary_components` (segera `salary_components`) — sudah ada (nullable, dari migration `2026_07_03_141000_...`), otomatis ikut ter-rename, tidak butuh migration tambahan.

---

## E. Urutan Eksekusi Migration (gabungan, urutan final)

1. `RENAME TABLE salary_components TO salary_component_assignments` (§A.1 langkah 1)
2. `RENAME TABLE m_salary_components TO salary_components` (§A.1 langkah 2)
3. Data-fix katalog: `UPDATE salary_components SET name = 'Cuti' WHERE name = 'Leave'` (§G) + `DELETE FROM salary_components WHERE name = 'Lembur'` (§H)
4. `RENAME TABLE program_incentives TO incentives` + `RENAME COLUMN program_name TO name` (§B.1)
5. `ALTER TABLE payroll_items ADD COLUMN source_type VARCHAR NULL` (§C.1)
6. `CREATE TABLE fee_submissions` + `CREATE TABLE fee_items` (§D, kolom `salary_component_id` FK ke `salary_components` — pastikan migration ini jalan **setelah** langkah 2)
7. `DROP TABLE` urutan aman FK: `doctor_payroll_items` → `doctor_payrolls` → `doctor_payroll_periods` → `doctor_fee_items` → `doctor_fee_submissions` → `m_doctor_services` → `master_doctor_service_categories` (§D)
8. `CREATE TABLE salary_component_assignment_employee_types` (§J, pastikan jalan **setelah** langkah 1)
9. §I dibatalkan (UI `job_evaluation_companies` ternyata sudah ada, lihat §I) — tidak ada migration/kode terkait.
10. Migration terakhir: `Artisan::call('shield:generate', ['--panel' => 'admin', '--all' => true, '--option' => 'permissions'])` — regenerasi katalog permission & Policy buat semua Resource yang berubah nama ATAU baru (`SalaryComponents`/`SalaryComponentAssignments`/`Incentive`/`FeeSubmission`), pola persis migration `2026_07_08_230229_sync_filament_shield_permission_catalog.php` yang sudah ada (idempotent, aman dijalankan ulang).

Boleh dipecah jadi beberapa file migration terpisah (lebih gampang di-debug kalau ada yang gagal) selama urutannya dijaga persis seperti di atas.

## F. Cleanup Policy

Hapus manual: `app/Policies/Payroll/MasterSalaryComponentsPolicy.php`, `app/Policies/Incentive/ProgramIncentivePolicy.php`. **Tidak perlu** tulis Policy baru manual — langkah 7 di §E (`shield:generate`) otomatis generate `SalaryComponentsPolicy`/`SalaryComponentAssignmentsPolicy`/`IncentivePolicy`/`FeeSubmissionPolicy` sesuai nama Resource baru (pola `merge=true` di `config/filament-shield.php` yang sudah ada).

## Verifikasi

- `php artisan migrate` jalan bersih di DB dev.
- Generate payroll manual untuk 1 company yang punya `FeeItem` belum lunas → cek `payroll_items` muncul baris earning (nama snapshot) + 1 baris deduction pajak, tiap baris punya `source_type` terisi benar, `FeeItem.is_paid` jadi true.
- Cek tabel `PayrollItemsRelationManager` di `/admin/payroll/payrolls/{id}` — kolom "Source" muncul dengan label yang benar per baris (Gaji Pokok, Komponen Jabatan, Kehadiran, Jasa Pelayanan, Incentive, Fee Klaim, Manual).
- Download slip PDF → render benar seperti biasa (tidak ada perubahan tampilan PDF).
- Cek `/admin/roles` → Resource baru (`SalaryComponents`/`SalaryComponentAssignments`/`Incentive`/`FeeSubmission`) muncul di `RoleForm.php` dengan grouping & permission yang benar (bukti `shield:generate` sukses).
- Cek `/admin` nav: tidak ada lagi menu jasa dokter terpisah, `FeeSubmissions` menggantikan posisi lama, `Incentives` menggantikan `ProgramIncentives`.
- Cek katalog `salary_components`: baris "Leave" sudah jadi "Cuti", baris "Lembur" sudah tidak ada lagi.
- Generate payroll untuk RSU Banjarbaru periode yang punya baris `job_evaluation_companies` (mis. April 2026) → cek baris "Jasa Pelayanan" tetap muncul seperti sebelumnya (regresi check terhadap rename `SalaryComponents`/`SalaryComponentAssignments`, bukan terkait perubahan baru karena §I dibatalkan).

## Referensi

- [`planning/2026-07-08-redesign-struktur-payroll.md`](2026-07-08-redesign-struktur-payroll.md) — rencana asli unifikasi doctor (sekarang tergantikan dokumen ini).
- [`knowledge/markdown/analisis-payroll-2-redesign-struktur.md`](../knowledge/markdown/analisis-payroll-2-redesign-struktur.md) §3.1 — asal usul rekomendasi rename `salary_components` → `salary_component_assignments`.
- [`knowledge/markdown/analisis-payroll.md`](../knowledge/markdown/analisis-payroll.md) — analisis pertama, gap eligibility & kategori company (di luar scope dokumen ini, tetap pending).
- [`knowledge/changes/2026-07-11-cleanup-shield-permission-dan-portal-dokumen.md`](../knowledge/changes/2026-07-11-cleanup-shield-permission-dan-portal-dokumen.md) — konteks kenapa bagian permission di rencana lama sudah tidak berlaku.
- File kode kunci: `app/Services/Payroll/PayrollService.php`, `app/Models/Payroll/{MasterSalaryComponents,SalaryComponents}.php`, `app/Models/Master/{Companies,Pangkats,JobPositions}.php`, `app/Models/Incentive/ProgramIncentive.php`, `app/Filament/Resources/Payroll/Payrolls/RelationManagers/PayrollItemsRelationManager.php`.

---

## Addendum: Redesign Filament UI (lanjutan sesi yang sama, 2026-07-12)

Bagian A–K di atas sudah dieksekusi penuh (migration jalan, kode diupdate, `shield:generate` sukses). Setelah itu, di sesi yang sama, ada rentetan iterasi desain UI/UX yang **mengganti beberapa keputusan §A.5 dan §J** — dicatat di sini biar dokumen di atas tidak menyesatkan kalau dibaca ulang nanti.

### 1. Kategori sistem (`category`) — pengganti `SalaryComponentNameEnum`

Ternyata `SalaryComponentNameEnum` (lookup berbasis `name` string) punya bug nyata: `case CUTI = 'Leave'` tetap begitu walau baris datanya sudah di-rename ke `'Cuti'` (§G) — lookup diam-diam gagal. User minta root-cause fix, bukan cuma perbaiki 1 case:

- Kolom baru `salary_components.category` (string, migration `2026_07_12_131251_add_category_to_salary_components_table.php`) — 8 nilai: `gaji_pokok`, `tunjangan`, `cuti`, `izin`, `keterlambatan`, `lembur`, `jasa_pelayanan`, `pajak_jasa_dokter`, `bpjs`. Enum baru `App\Enum\Salary\SalaryComponentCategoryEnum` menggantikan `SalaryComponentNameEnum` (dihapus total) — `PayrollService.php` sekarang lookup by `category`, bukan `name`.
- `SalaryComponentCategoryEnum::type(): SalaryComponentTypeEnum` — earning/deduction **diturunkan otomatis dari category**, bukan input terpisah (mencegah kombinasi kontradiktif kaya kategori CUTI tapi type-nya earning). Model `SalaryComponents::booted()` maksa `type` selalu sinkron ke `category` tiap `saving()`.
- `SalaryComponentCategoryEnum::generalCategories()` = kategori tanpa assignment sama sekali (Cuti/Izin/Keterlambatan/Lembur) — dipakai jadi guard tombol Hapus (lihat poin 4).

### 2. Resource Filament di-split & dipindah top-level

4 Resource lama (`GeneralSalaryComponents`/`CompanySalaryComponents`/`PangkatSalaryComponents`/`JobPositionSalaryComponents`, disebut di §A.5 "tidak ada perubahan struktur") **dikonsolidasi total** jadi 2 Resource baru, sama-sama pindah dari `Resources/Payroll/*` ke `Resources/*` top-level (bukan lagi nested — pola ini juga diterapkan ke `Incentives`):

- **`Resources/SalaryComponents/`** (`SalaryComponentResource`, model `SalaryComponents`) — CRUD katalog murni (nama, kategori, metode hitung, nilai default, company opsional). Create/Edit pakai **modal** (bukan halaman terpisah — alasan: "ini tabel master"). Kolom "Dipakai Di" (`withCount('assignments')`) + tombol Hapus **cuma muncul kalau `assignments_count === 0` DAN kategori bukan salah satu `generalCategories()`** (kategori umum kaya Cuti/Izin selalu 0 assignment by design, jadi butuh guard kedua ini biar tetap terlindungi).
- **`Resources/SalaryComponentAssignments/`** (`SalaryComponentAssignmentResource`, model **`SalaryComponents`** juga, bukan `SalaryComponentAssignments`) — label nav "Penempatan Komponen Gaji". List-nya tabel **ringkasan**: 1 baris = 1 komponen katalog, kolom Perusahaan/Komponen/3 badge jumlah (ke Company/Pangkat/Jabatan, via `withCount` conditional). Klik "Lihat" → halaman View dengan **RelationManager** (`AssignmentsRelationManager`, relasi `assignments`) yang isinya CRUD assignment individual sungguhan (model `SalaryComponentAssignments`) — form Create/Edit: "Untuk" (Company langsung/Pangkat/Jabatan) → "Pilih" (opsinya **di-scope ke `company_id` milik komponen itu sendiri**, bukan field terpisah yang harus dipilih ulang) → Nilai. Edit nampilin field yang sama tapi `disabled()`+`dehydrated(false)` buat identitas (cuma Nilai yang beneran bisa diubah).

Widget "Komponen Umum" (kategori tanpa assignment) sempat dibuat sebagai header widget lalu **dihapus lagi** atas permintaan user — kategori umum sekarang cukup diedit lewat CRUD katalog biasa di `SalaryComponentResource`, tidak ada tampilan khusus lagi. Sebagai gantinya ada section legend "Pendapatan vs Potongan" (badge per kategori, warna dari `SalaryComponentCategoryEnum::badgeColor()`) di bawah tabel List `SalaryComponentResource`.

### 3. Eligibility tipe karyawan — pindah dari assignment ke komponen (ganti §J)

§J di atas taruh pivot eligibility di level assignment (`salary_component_assignment_employee_types`, FK ke `salary_component_assignments`). User minta dipindah: **1 komponen cuma punya 1 daftar tipe karyawan eligible, berlaku sama ke semua assignment-nya** — bukan beda-beda per pangkat/jabatan yang dituju.

- Migration `2026_07_12_152732_move_employee_type_eligibility_to_salary_components.php` — bikin `salary_component_employee_types` (FK ke `salary_components`), **drop** `salary_component_assignment_employee_types` (tabel §J dihapus total, bukan cuma tidak dipakai).
- `SalaryComponents::employeeTypes(): BelongsToMany` (baru). `SalaryComponentAssignments::employeeTypes()` **dihapus**.
- `PayrollService::isEligibleForEmployeeType()` — baca `$assignment->salaryComponent->employeeTypes()`, bukan `$assignment->employeeTypes()`.
- Field "Tipe Karyawan Eligible" pindah ke form katalog (`SalaryComponentForm`, pakai `->relationship('employeeTypes', 'name')->multiple()` — auto-sync tanpa hook manual), dicopot dari form assignment individual.

### 4. Bug ditemukan & difix selama proses (bukan cuma refactor, ada regresi nyata yang kepancing)

- **Filament v4 `TableWidget`/`RelationManager` default-nya lazy-load** (`x-intersect`, baru render pas viewport masuk) — widget "Komponen Umum" (sebelum dihapus) dan `AssignmentsRelationManager` di halaman View dua-duanya kena, bikin heading/tombol/kolom aksi "hilang" padahal kodenya benar. Fix: `protected static bool $isLazy = false;` eksplisit di keduanya.
- **`Filament\Tables\Table` tidak punya method `withCount()`** (itu method Eloquent Builder) — harus lewat `->modifyQueryUsing(fn ($q) => $q->withCount(...))`.
- **`ListRecords` (Filament v4) tidak otomatis render header widget lewat `content()` override** kalau `content()` di-override manual tanpa manggil balik komponen widget aslinya — solusinya extend `parent::content($schema)` lalu append komponen tambahan, bukan replace total.
- **Guard tombol Hapus yang cuma cek `assignments_count === 0` ternyata tidak melindungi kategori umum** (Cuti/Izin/Keterlambatan/Lembur) — kategori itu memang didesain TIDAK PERNAH punya assignment, jadi count-nya selalu 0, tombol Hapus selalu muncul walau komponennya masih dipakai `PayrollService` lewat `category` lookup. Fix: tambah kondisi kedua, `! in_array(category, generalCategories())`.
- **`$get('field')` di dalam closure `->schema()` action-level (RelationManager `CreateAction`/`EditAction`) tidak resolve `$ownerRecord` lewat type-hint parameter** (`BindingResolutionException`) — harus pakai `$this->getOwnerRecord()` (arrow function bawa `$this` dari method `table()`), bukan andalin auto-injection Filament.
- **`afterStateUpdated()` cuma jalan kalau user beneran ganti state di browser** — kalau form action di-submit langsung dengan data yang sudah "jadi" (mis. test `callTableAction(..., data: [...])`, atau autofill programatik), hook itu tidak pernah terpanggil. Auto-fill `salaryable_id = company_id` waktu "Untuk" = Company langsung sempat gagal karena cuma mengandalkan `afterStateUpdated` — fix: tambah `mutateDataUsing()` (nama baru Filament v4, `mutateFormDataUsing()` sudah deprecated) sebagai jaring pengaman server-side.
- **Migration `dropForeign('nama_constraint_string')` tidak portable ke SQLite** (dipakai test DB, `RefreshDatabase`) — SQLite grammar cuma dukung drop-by-column-array. Migration `2026_07_12_134851_drop_company_id_from_incentives_table.php` (hapus `incentives.company_id`, sudah redundan karena company bisa diturunkan dari `user->company_id`) sempat bikin **semua** test gagal sampai diperbaiki jadi `dropForeign(['company_id'])`.

### 5. `Incentive` juga dipindah top-level + full Indonesia

Folder `Resources/Payroll/Incentives/` → `Resources/Incentives/` (URL `/admin/incentives`, bukan `/admin/payroll/incentives`). 3 halaman page terpisah (Create/Edit/View, route-nya memang sudah tidak aktif dari awal) dihapus — CRUD sepenuhnya modal (Create & Edit pakai modal biasa, View & Delete slide-over). Kolom tabel & form full Indonesia (Company→Perusahaan, Program Holder→Penerima, Program→Nama Program, dst), kolom status jadi badge warna (hijau "Sudah Diproses" / kuning "Belum Diproses", ganti dari icon boolean polos). Form Create/Edit nambah field "Perusahaan" di depan "Penerima" (cuma buat filter pilihan user, `dehydrated(false)` — bukan kolom asli tabel `incentives`), Edit pakai `fillForm` custom (`...$record->attributesToArray()` + `company_id` manual) biar field itu ke-isi otomatis pas dibuka. Nav Keuangan diurut ulang: Komponen Gaji → Penempatan Komponen Gaji → Periode Penggajian → Penggajian → Insentif → Pengajuan Fee → Gaji Trainer.

### 6. Global: tombol Create default jadi "Buat" polos

Override translation `lang/vendor/filament-actions/id/create.php` (`single.label` dari `'Buat :label'` jadi `'Buat'`) — berlaku ke **semua** Resource sekaligus (bukan per-Resource), karena `Filament\Actions\CreateAction` di seluruh app pakai key translation yang sama.

### 7. Test baru

- `tests/Feature/SalaryComponentCrudTest.php` — CRUD katalog, type auto-derive dari category, filter company, 3 skenario guard tombol Hapus.
- `tests/Feature/SalaryComponentAssignmentTest.php` — summary list + counts, filter company, halaman View + relation manager, scoping Pangkat/Jabatan ke company komponen (termasuk test negatif: assign ke pangkat company lain harus ditolak validasi), create/edit/delete assignment, auto-fill `salaryable_id` buat assignment langsung ke Company.

Referensi tambahan: `app/Models/SalaryComponent/SalaryComponents.php`, `app/Filament/Resources/SalaryComponents/`, `app/Filament/Resources/SalaryComponentAssignments/`, `app/Filament/Resources/Incentives/`, `app/Enum/Salary/SalaryComponentCategoryEnum.php`.
