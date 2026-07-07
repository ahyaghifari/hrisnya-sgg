# Analisis Payroll — Komponen Gaji, Jasa Dokter, Trainer, Insentif (2026-07-05)

Dokumen ini menjawab kebingungan yang dirasakan soal payroll: apakah jasa dokter/pajak jasa dokter memang dibatasi cuma untuk company rumah sakit, dan apakah tunjangan jabatan/transportasi memang punya syarat status kepegawaian (tetap vs kontrak). Ditulis dari pembacaan kode langsung (migration, model, service) — bukan asumsi.

## TL;DR

**Dua kecurigaan yang disampaikan TERNYATA BENAR — keduanya adalah gap nyata, bukan fitur yang sudah ada tapi tersembunyi:**

1. **Tidak ada syarat status kepegawaian (tetap/kontrak) untuk tunjangan apa pun.** `PayrollService::generatePayroll()` cuma filter karyawan berdasar `company_id` + `is_active` — kolom `employee_type_id` **tidak pernah dibaca** di seluruh alur generate payroll. Semua karyawan aktif di company itu otomatis dapat SEMUA komponen gaji yang di-assign ke jabatan & pangkatnya, termasuk Tunjangan Jabatan dan Tunjangan Transportasi, tidak peduli dia karyawan tetap atau masih kontrak.
2. **Tidak ada pembeda "ini rumah sakit" vs "ini bukan rumah sakit" di mana pun** — baik di skema database (`m_companies` cuma punya `code`, `name`, `location`, `is_active`, tidak ada kolom tipe/kategori bisnis) maupun di kode. Fitur Jasa Dokter, Master Layanan Dokter, dan Pajak Jasa Dokter semuanya tersedia sebagai resource Filament global yang company mana pun bisa pakai — termasuk holding, "Lerina Inn" (hotel), atau "Syifa Agro Natura", tidak ada penghalang teknis sama sekali.

Detail & rekomendasi ada di §5 dan §6.

## 1. Peta Modul Payroll

| Sub-modul | Fungsi | Trigger |
|---|---|---|
| **Payroll reguler** | Gaji pokok + komponen tetap/persentase per jabatan/pangkat + lembur + jasa pelayanan | Manual — admin klik "Generate" per periode di `/admin/payroll/payroll-periods` |
| **Jasa Dokter** | Klaim jasa pelayanan dokter per pasien → payroll dokter per periode + pajak | Manual — input submission per pasien, lalu klik "Generate" per periode dokter |
| **Gaji Trainer** | Skor penilaian trainer → honor + masa berlaku sertifikasi, berbasis tabel skor | Otomatis dihitung ulang tiap ada assessment baru (bukan per-periode) |
| **Insentif Program** | Insentif manual per karyawan | **Full manual** — tidak ada logic otomatis sama sekali |

Semua generate payroll (reguler & dokter) dipicu lewat tombol di tabel Filament (`GeneratePayrollJob::dispatch()`/`GenerateDoctorPayrollJob::dispatch()`), **bukan cron/scheduled job** — jadi kalau ada period yang lupa di-generate, tidak ada yang otomatis mengingatkan atau menjalankan.

## 2. Skema Tabel

### 2.1 Payroll Reguler

**`m_salary_components`** — katalog master komponen gaji.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `company_id` | ulid, FK → `m_companies`, **nullable** | `NULL` = berlaku untuk semua company (shared). Ditambahkan belakangan (`2026_07_03_141000...`), data lama sengaja **tidak** di-backfill — komentar migration eksplisit bilang holding & RSU Banjarbaru memang berbagi baris yang sama |
| `name` | string | mis. "Tunjangan Jabatan", "Pajak Jasa Dokter" |
| `type` | enum(`earning`, `deduction`) | |
| `calculation_type` | enum(`fixed`, `percentage`, `formula`) | default `fixed` |
| `formula_key` | string, nullable | |
| `default_value` | decimal(15,2), nullable | |
| `is_active` | boolean | |

**`salary_components`** — tabel assignment (siapa dapat komponen apa, berapa besarannya). Polymorphic ke `JobPositions`/`Pangkats`/`Companies` lewat `salaryable_type`/`salaryable_id`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `salary_component_id` | ulid, FK → `m_salary_components` | cascade delete |
| `salaryable_type` / `salaryable_id` | polymorphic | isi salah satu: `JobPositions`, `Pangkats`, atau `Companies` |
| `amount` | decimal(15,2), nullable | |
| `percentage` | decimal(5,2), nullable | |
| `is_overrideable` | boolean, default true | |

⚠️ **Tidak ada kolom apa pun di tabel ini yang menyebut syarat kepegawaian** (employee_type, status tetap/kontrak) — assignment cuma bicara "jabatan/pangkat/company ini dapat komponen X sebesar Y", titik.

**`payroll_periods`**: `id`, `company_id` (nullable, tapi fungsinya wajib — lihat §5), `start_date`, `end_date`, `processed_at`.

**`payrolls`**: `id`, `user_id` → `users`, `payroll_period_id` → `payroll_periods`, `gross_salary`/`total_deductions`/`net_salary`, `is_paid`.

**`payroll_items`**: baris rincian per payroll (snapshot, tidak terhubung balik ke `salary_components`) — `payroll_id`, `name`, `type`, `calculation_type`, `quantity`/`unit_amount`/`amount`.

**`extra_hour_rules`** / **`shift_overtime_costs`**: tarif lembur, keduanya **wajib** `company_id` (tidak ada opsi "berlaku semua company" seperti `m_salary_components`).

### 2.2 Jasa Dokter

**`m_doctor_services`** (model `MasterDoctorService`) — tarif master per jenis layanan dokter.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `company_id` | ulid, FK → `m_companies`, **nullable** | ditambahkan `2026_07_03_141000`, sama seperti `m_salary_components` — `NULL` = berlaku semua company |
| `name`, `calculation_type`, `amount`, `percentage`, `formula_code`, `notes`, `is_active` | | Cuma `amount` (fixed) yang benar-benar dipakai di form admin — `percentage`/`formula_code` ada di skema tapi disembunyikan dari form |

**`doctor_fee_submissions`** — klaim per pasien/SEP.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `company_id` | ulid, FK → `m_companies`, **wajib** | |
| `sep_number`, `patient_name`, `total_claim`, `service_type`, `status`, `notes`, `date` | | |
| `created_by` | ulid, FK → `users`, nullable | |

**`doctor_fee_items`** — baris per dokter dalam 1 submission: `submission_id`, `user_id` (dokternya), `doctor_service_id`, `amount`, `is_paid`.

**`doctor_payroll_periods`**: `company_id` (nullable), `start_date`/`end_date`, `processed_at`.

**`doctor_payrolls`** — **di sinilah pajak jasa dokter hidup**, di level payroll-run, bukan per layanan/company:

| Kolom | Tipe |
|---|---|
| `doctor_id` | FK → `users` |
| `doctor_payroll_period_id` | FK → `doctor_payroll_periods` |
| `gross_salary` | decimal(15,2) |
| `tax_percentage` | decimal(5,2) |
| `tax_amount` | decimal(15,2) |
| `net_salary` | decimal(15,2) |
| `is_paid` | boolean |

**`doctor_payroll_items`**: `doctor_payroll_id`, `doctor_fee_item_id`, `service_name`, `amount`.

⚠️ **Tidak ada kolom "tipe company" di `m_companies` maupun di tabel manapun di atas.** Skema `m_companies` cuma: `id`, `code`, `name`, `location`, `is_active`.

### 2.3 Gaji Trainer

`trainers` (internal/eksternal, punya kolom `company` **string bebas**, bukan FK — beda dari `company_id` FK yang dipakai tabel lain) → `trainer_score_variables` (kategori penilaian: honor/validity, per company nullable) → `trainer_score_categories` (skor per kategori) → `trainer_assessments`/`trainer_assessment_details` (hasil penilaian per meeting) → `trainer_score_ranges` (tabel rentang skor → besaran honor + masa berlaku) → `trainer_assesments_results` (hasil akhir per course: `honor_amount`, `validity_months`, `valid_until` — inilah yang ditampilkan sebagai "Gaji Trainer").

### 2.4 Insentif Program

**`program_incentives`**: `company_id` (wajib), `user_id` (wajib), `program_name`, `incentive_date`, `amount`, `is_processed`. Murni tabel input manual — **tidak ada Service class atau logic otomatis apa pun** yang mengisi/memproses tabel ini.

## 3. Relasi Model (ringkas)

| Model | Relasi kunci |
|---|---|
| `MasterSalaryComponents` | `hasMany(SalaryComponents)`, pakai trait `BelongsToCompanyOrShared` |
| `JobPositions` / `Pangkats` / `Companies` | masing-masing `morphMany(SalaryComponents::class, 'salaryable')` |
| `Payroll` | `belongsTo(User)`, `belongsTo(PayrollPeriod)`, `hasMany(PayrollItem)` |
| `MasterDoctorService` | pakai trait `BelongsToCompanyOrShared` (sama seperti komponen gaji) |
| `DoctorFeeSubmission` | `company()`, `items() hasMany DoctorFeeItem`, `createdBy()` |
| `DoctorPayroll` | `period()`, `doctor() (User)`, `items() hasMany DoctorPayrollItem` |
| `ProgramIncentive` | pakai trait `BelongsToCompany` (scope KETAT per company, beda dari `OrCompanyShared`) — `company()`, `user()` |
| `TrainerAssesmentsResults` | `course() (LmsCourses)`, `trainer() (Trainers)`, `company()` |
| `EmployeeTypes` (`app/Models/Master/EmployeeTypes.php`) | **cuma** `name` + `company_id` — **tidak ada relasi ke komponen gaji sama sekali** |

## 4. Bagaimana Payroll Dihasilkan (alur nyata di kode)

### 4.1 Payroll reguler — `PayrollService::generatePayroll()`

1. Ambil semua `User::where('company_id', $period->company_id)->where('is_active', true)->whereNull('deleted_at')`. **Cuma ini filternya** — tidak ada `employee_type_id` di mana pun dalam query ini.
2. `handleBaseSalary()` — ambil komponen gaji pokok dari `$user->profile?->pangkat?->salaryComponents()`, diterapkan tanpa syarat tambahan.
3. `handleJobPositionFixedComponents()` / `handleJobPositionPercentageComponents()` — loop **semua** komponen fixed/persentase yang nempel di jabatan user (`$user->activeJobPosition?->jobPosition?->fixedSalaryComponents`) — **di sinilah Tunjangan Jabatan & Tunjangan Transportasi diterapkan, tanpa cek status kepegawaian apa pun.**
4. `handleExtraHourAllowanceComponent()` / `handleOvertimeComponent()` — dihitung dari data presensi/shift aktual (`ExtraHourAllowanceRules`, `ShiftOvertimeCosts`) — ini SATU-SATUNYA bagian yang benar-benar kondisional, tapi kondisinya soal jam kerja/shift, bukan status kepegawaian.
5. `handleServiceFeeComponent()` (Jasa Pelayanan) — dihitung dari rasio skor JE per company.

### 4.2 Payroll Jasa Dokter — `DoctorPayrollService::generatePayroll()`

1. Ambil semua `doctor_fee_items` yang belum `is_paid`, tanggalnya masuk periode, dan (kalau `period->company_id` diisi) difilter company.
2. Group per dokter (`user_id`), jumlahkan jadi `gross_salary`.
3. Cari `MasterSalaryComponents` bernama **"Pajak Jasa Dokter"** — **tanpa filter company sama sekali** (baris 87-90 di service) — tarif pajak yang sama dipakai company mana pun yang generate payroll dokter.
4. `tax_amount = gross * tax_percentage / 100`, `net_salary = gross - tax_amount`.

### 4.3 Gaji Trainer — `TrainerAssessmentService::generateTrainerAssessmentResult()`

Rata-rata skor honor/validity dari seluruh `trainer_assessments` per course → dicocokkan ke `TrainerScoreRanges` (rentang skor → `honor_amount` + `validity_months`) → hasil disimpan di `trainer_assesments_results`. Ini berbasis tabel skor, bukan status kepegawaian — masuk akal karena trainer bukan karyawan tetap perusahaan.

### 4.4 Insentif Program

Tidak ada alur otomatis — admin isi manual lewat form Filament, tandai `is_processed` manual juga.

## 5. Temuan Kritis (Gap Analysis)

1. **Tidak ada eligibility check status kepegawaian untuk komponen gaji apa pun** (`app/Services/Payroll/PayrollService.php`). Grep menyeluruh `employee_type|EmployeeType|is_permanent|status_karyawan` di seluruh `app/` cuma nemu 22 hits, dan **tidak satu pun** di jalur generate payroll (`PayrollService`, `SalaryComponents`, `PayrollItem`). Satu-satunya hit yang berhubungan cuma kolom tabel admin (`ExtraHourAllowancesTable.php`), bukan logic. Ini bukan fitur tersembunyi — **memang belum pernah dibangun**.
2. **Tidak ada kolom tipe/kategori company di `m_companies`**, dan tidak ada tabel jasa-dokter yang membatasi berdasar kategori bisnis. Form admin (`DoctorFeeSubmissionForm`, `DoctorPayrollPeriodForm`) menampilkan **semua** company di dropdown `company_id` tanpa filter apa pun — satu-satunya pembatas yang ada saat ini murni soal *permission* (siapa yang punya akses ke resource ini), bukan soal *jenis company*.
3. **`MasterDoctorServiceResource` tidak punya halaman create/edit** — `getPages()` cuma daftarkan `index`, halaman create/edit di-comment. Jadi tarif master layanan dokter saat ini **tidak bisa dibuat/diubah lewat UI sama sekali** (kemungkinan besar cuma bisa lewat seeder/tinker manual).
4. **Tarif "Pajak Jasa Dokter" bersifat 1 tarif global** — dicari lewat nama komponen tanpa filter company, jadi kalau holding & RSU Banjarbaru butuh persentase pajak yang beda, sistem saat ini tidak bisa membedakannya.
5. **`Trainers.company`** adalah kolom string bebas (bukan FK ke `m_companies`) — beda sendiri dari pola `company_id` FK yang dipakai tabel lain, berisiko typo/tidak konsisten.
6. **Insentif Program 100% manual** — kalau ada rencana "otomatisasi payroll", ini bagian yang paling jauh dari otomatis; belum ada rule/trigger apa pun.

## 6. Rekomendasi

**Untuk syarat status kepegawaian (tetap/kontrak):**
Pilihan termudah yang tidak mengubah cara kerja skema secara besar: tambah 1 kolom nullable di tabel assignment `salary_components`, mis. `required_employee_type_id` (FK ke `m_employee_types`, nullable = "berlaku semua tipe"). Lalu di `PayrollService::handleJobPositionFixedComponents()`/`Percentage...()`, tambah 1 baris pengecekan: skip komponen kalau `required_employee_type_id` diisi dan tidak cocok dengan `$user->profile->employee_type_id`. Perubahan kecil, tidak perlu redesain skema.

**Untuk "jasa dokter cuma buat rumah sakit" — jangka pendek vs jangka panjang:**

Jangka pendek, ada 2 opsi cepat:
- **Opsi A (data-driven, 1 company)**: pakai kolom `company_id` yang **sudah ada** (nullable) di `m_salary_components`/`m_doctor_services` — isi dengan 1 company spesifik alih-alih `NULL`. Tidak butuh migration sama sekali, tapi kalau rumah sakitnya lebih dari satu, harus duplikasi baris per rumah sakit (lihat risiko drift di bawah).
- **Opsi B (permission-based, tanpa ubah skema)**: jangan berikan permission `view_pengajuan_jasa_dokter`/`create_pengajuan_jasa_dokter` ke role di company non-rumah-sakit lewat `/admin/roles` yang sudah ada. Konsisten dengan pola yang sudah dirapikan sesi sebelumnya (role per company + permission assignment) — bisa langsung diterapkan hari ini, nol risiko migrasi data.

### 6.1 Rekomendasi jangka panjang: scope 3 tingkat (company spesifik → kategori company → global)

`company_id` nullable yang sudah ada di `m_salary_components`/`m_doctor_services` cuma bisa membedakan **1 company spesifik** vs **global (semua company)** — tidak ada tingkat menengah "sekelompok company sejenis" (mis. "semua rumah sakit", tanpa peduli berapa banyak rumah sakit yang ada/akan ada). Kalau rumah sakit nambah jadi 3, 4, dst, satu-satunya cara sekarang adalah duplikasi baris komponen per rumah sakit — dan tiap kali tarifnya berubah, semua baris duplikat itu harus diingat untuk diupdate bareng. Ini pola drift yang sama yang berulang kali ditemukan di sesi analisis lain (permission, dst).

**Rekomendasi**: tambah 1 tingkat scope lagi, mengikuti pola `scope_type` yang **sudah ada** di Approval Engine (`same_department` / `specific_department` / `global` — lihat `manajemen-persetujuan.md`) — reuse pola yang sudah dikenal di codebase ini, bukan konsep baru:

1. **Company spesifik** (`company_id` diisi) — paling spesifik, menang duluan kalau ada.
2. **Kategori company** (`company_category`, kolom baru nullable) — berlaku untuk semua company dengan kategori yang sama. Rumah sakit baru otomatis ke-cover tanpa perlu duplikasi baris apa pun.
3. **Global** (keduanya `NULL`) — berlaku semua company, seperti sekarang.

Urutan pencarian tarif: cek company spesifik dulu → kalau tidak ada, cek kategori → kalau tidak ada juga, pakai yang global.

**Perubahan yang dibutuhkan:**
1. Kolom baru `type` (enum: `hospital`, `hotel`, `other`, dst — nullable dulu, diisi manual per company yang sudah ada) di **`m_companies`** — sekaligus menutup temuan §5.2 (tidak ada kolom tipe company sama sekali).
2. Kolom baru `company_category` (nullable, enum sama) di **`m_salary_components`** dan **`m_doctor_services`** — dua tabel yang sudah sama-sama punya `company_id` nullable, pattern-nya identik jadi dirapikan bareng.
3. Ubah query pencarian komponen di `PayrollService`/`DoctorPayrollService` supaya cek 3 tingkat itu berurutan (saat ini cuma cek `company_id` langsung ke global, tinggal disisipkan 1 langkah kategori di antaranya).

**Kenapa kategori, bukan tabel pivot many-to-many (company ↔ daftar komponen eksplisit)**: pivot lebih fleksibel secara teori (bisa pilih company mana saja secara bebas), tapi tetap butuh pilih manual satu-satu dan tidak otomatis ke-cover kalau ada company baru sejenis. Untuk kasus "jasa dokter = rumah sakit" yang sifatnya soal JENIS BISNIS (bukan pengecualian acak per company), kategori lebih pas dan lebih sedikit kerja manual jangka panjang. Kalau nanti ternyata butuh pengecualian di luar kategori (mis. 1 rumah sakit tertentu TIDAK boleh dapat komponen itu), tingkat 1 (`company_id` spesifik, atau kolom `excluded` terpisah) tetap bisa jadi override di atas kategori.

Efeknya kecil di kode: 2 kolom nullable + 1 langkah tambahan di urutan pencarian, tidak perlu redesain skema besar-besaran.

Rekomendasi saya: **mulai dari Opsi B** (langsung bisa diterapkan hari ini lewat UI yang sudah ada, nol risiko migrasi data), Opsi A baru dipertimbangkan kalau ternyata butuh validasi yang lebih ketat/otomatis (mis. mencegah admin holding sendiri salah pilih company saat entry data).

**Soal "otomatisasi"**: bagian yang SUDAH otomatis begitu tombol "Generate" diklik: base salary, komponen tetap/persentase per jabatan, lembur, jasa pelayanan, pajak jasa dokter. Yang **belum** otomatis: triggernya sendiri (masih klik manual per periode, tidak ada jadwal/cron), input klaim jasa dokter (manual per pasien, wajar karena ini data kejadian nyata), dan insentif program (100% manual, belum ada rule pemicu). Kalau mau "lebih otomatis", area paling berdampak adalah menjadwalkan generate payroll reguler otomatis tiap awal bulan (lewat `$schedule` di `bootstrap/app.php`, sudah ada contoh pola serupa untuk reset saldo cuti) — bukan mengubah logic perhitungannya, cuma triggernya.

## 7. Referensi File Kode

- `app/Services/Payroll/PayrollService.php` — `generatePayroll()`, `handleBaseSalary()`, `handleJobPositionFixedComponents()`, `handleJobPositionPercentageComponents()`, `handleExtraHourAllowanceComponent()`, `handleOvertimeComponent()`, `handleServiceFeeComponent()`
- `app/Services/Payroll/DoctorPayrollService.php` — `generatePayroll()`
- `app/Services/LMS/TrainerAssessmentService.php` — `generateTrainerAssessmentResult()`
- `app/Models/Payroll/MasterSalaryComponents.php`, `SalaryComponents.php`
- `app/Models/DoctorService/MasterDoctorService.php`, `DoctorFeeSubmission.php`, `DoctorPayroll.php`
- `app/Models/Master/Companies.php`, `EmployeeTypes.php`
- `app/Models/Concerns/BelongsToCompanyOrShared.php`, `BelongsToCompany.php`
- `app/Enum/Salary/SalaryComponentNameEnum.php` — daftar nama komponen & routing ke resource admin mana
- `app/Filament/Clusters/MasterSalaryComponent/` — resource CRUD komponen gaji (Company/JobPosition/Pangkat/General)
- `app/Filament/Resources/Payroll/DoctorFeeSubmissions/`, `DoctorPayrollPeriods/`, `MasterDoctorServices/`
- `app/Filament/Resources/Payroll/TrainerScoreVariables/`, `TrainerScoreRanges/`, `TrainerSalaries/`
- `app/Filament/Resources/Payroll/ProgramIncentives/`
- Migration kunci: `2026_07_03_141000_add_nullable_company_id_to_master_doctor_services_and_salary_components.php` (komentar migration ini eksplisit menjelaskan keputusan "shared vs company-specific"), `2026_01_11_100257_...` (nambah kolom pajak di `doctor_payrolls`)
