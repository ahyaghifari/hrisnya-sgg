# Planning: Redesign Struktur Payroll — Unifikasi Doctor Payroll ke Payroll Reguler — 2026-07-08

Status: **rencana disusun & didiskusikan, belum ada migration/kode dieksekusi.**

Konteks lengkap & verifikasi database ada di [`knowledge/markdown/analisis-payroll-2-redesign-struktur.md`](../knowledge/markdown/analisis-payroll-2-redesign-struktur.md) — ringkasannya di bawah supaya rencana ini bisa dieksekusi tanpa baca ulang seluruh riwayat diskusi.

## Latar Belakang

Holding ini multi-lini-bisnis (rumah sakit, hotel, agribisnis, dll), tapi ada 1 subsistem payroll paralel penuh khusus "jasa dokter" (`m_doctor_services`, `doctor_fee_submissions`, `doctor_fee_items`, `doctor_payroll_periods`, `doctor_payrolls`, `doctor_payroll_items`) yang strukturnya identik dengan payroll reguler (`m_salary_components` → `salary_components` → `payroll_periods` → `payrolls` → `payroll_items`), cuma beda sumber pemicu: assignment tetap ke jabatan (rule-driven) vs klaim per kejadian (event-driven).

Sudah diverifikasi ke database asli (`hris_db`, bukan asumsi):
- **Modul payroll (reguler maupun dokter) belum benar-benar dipakai operasional.** Payroll terakhir digenerate 2026-05-07 (2 bulan mandek), dan 57 dari 76 payroll (75%) hasilnya kosong (0 `payroll_items`) karena banyak user belum punya `salary_components` ter-assign.
- Semua data `doctor_fee_submissions`/`doctor_fee_items` (4 submission, 8 item) adalah data uji coba jelas ("Denis", "TEST", dokter fiktif "Staff IT"/"Admin") — **tidak ada data produksi nyata yang perlu diselamatkan**.
- Modul yang justru aktif dipakai rutin sampai sekarang (terakhir 2026-07-05): attendance request (cuti/izin/lembur).
- Ditemukan bug nyata yang ditutup sekalian dalam refactor ini (root-cause, bukan tambal): `getMasterComponent()` di `PayrollService` dan lookup pajak dokter di `DoctorPayrollService` sama-sama query `MasterSalaryComponents::where('name', ...)->first()` tanpa filter `company_id` eksplisit, mengandalkan global scope `BelongsToCompanyOrShared` yang **mati total** saat dijalankan lewat `queue:work` (`app()->runningInConsole()` = true di worker, dan `.env` set `QUEUE_CONNECTION=database`). Masih laten sekarang (semua komponen master `company_id = NULL`), tapi meledak diam-diam begitu ada company kedua dengan tarif sendiri.
- PDF slip dokter (`doctor-payroll.blade.php`) manggil `$payroll->doctor->employee_number` — kolom itu tidak ada di skema `users` (yang ada `nip`), selalu tampil "-".

Karena datanya cuma dummy dan modulnya belum benar-benar dipakai, ini momen paling murah untuk merapikan struktur sebelum ada data produksi nyata yang terikat ke skema lama.

**Keputusan yang sudah dikonfirmasi**: fee dokter ditarik otomatis lewat 1 tombol "Generate Payroll" bulanan yang sama dengan payroll reguler (bukan tombol generate terpisah di luar siklus bulanan) — dokter jadi `User` biasa yang ikut siklus payroll company-nya, sumber income-nya saja yang beda (klaim, bukan assignment jabatan).

Catatan: ada juga rancangan "reset total dari nol" (skema lebih ambisius: tambah kategori company, eligibility kepegawaian, provenance `payroll_items`, rename tabel assignment) — didokumentasikan di analisis #2 §3 sebagai referensi arah jangka panjang, TAPI **plan ini sengaja dibatasi ke unifikasi doctor→payroll saja** (scope lebih kecil, langsung actionable, tidak nunggu keputusan fitur-fitur tambahan itu).

## Tujuan

Hapus skema paralel `doctor_*`, gantikan dengan tabel klaim generik (`fee_submissions`/`fee_items`) yang menyatu ke pipeline `payroll_periods → payrolls → payroll_items` yang sudah ada — supaya dokter (dan lini bisnis lain yang nanti butuh income berbasis klaim, bukan cuma rumah sakit) cukup 1 pipeline payroll, bukan 2 sistem paralel yang harus disinkronkan manual.

## Skema Baru

**Dihapus total** (tabel + model + migration drop, folder `app/Models/DoctorService/` ikut hilang):
- `m_doctor_services`, `doctor_fee_submissions`, `doctor_fee_items`, `doctor_payroll_periods`, `doctor_payrolls`, `doctor_payroll_items`
- Bonus cleanup (orphan, ditemukan saat riset): tabel `master_doctor_service_categories` + `database/seeders/DoctorServiceCategorySeeder.php` — sudah dead code, merujuk ke model `DoctorServiceCategory` yang tidak ada, seeder-nya tidak terdaftar di `DatabaseSeeder.php`.

**Baru** (menggantikan `doctor_fee_submissions`/`doctor_fee_items`, generik):
- `fee_submissions`: `id`, `company_id` (wajib, FK), `reference_number` (dulu `sep_number`), `subject_name` (dulu `patient_name`), `service_type`, `status`, `notes`, `date`, `created_by` (FK users, nullable)
- `fee_items`: `id`, `submission_id` (FK), `user_id` (penerima), `salary_component_id` (FK → `m_salary_components`, **nullable**), `is_manual` (bool, dari toggle form yang sudah ada), `name` (snapshot, wajib), `amount`, `is_paid`, `notes`

Kolom `salary_component_id` sengaja nullable dan `name` tetap snapshot text — data asli `doctor_fee_items` menunjukkan banyak baris (`"Visit Bersama"`, `"Operasi"`, `"Bius"`) sama sekali tidak link ke master service, murni input manual (form-nya sudah punya toggle `is_manual` eksplisit untuk ini). Kalau dipaksa FK wajib, alur kerja ad-hoc ini patah.

**Tidak berubah**: `m_salary_components`, `salary_components`, `payroll_periods`, `payrolls`, `payroll_items`. 3 baris `m_doctor_services` yang ada (Operator, Anestesi, konsultasi) kalau mau dipertahankan, tambahkan manual lewat resource `GeneralSalaryComponents`/`CompanySalaryComponents` yang sudah ada (tidak perlu migration data khusus, cuma 3 baris dummy).

## Langkah Eksekusi

### 1. Migration
- 1 migration baru: create `fee_submissions` + `fee_items` (schema di atas).
- 1 migration baru: drop (urutan aman FK) `doctor_payroll_items` → `doctor_payrolls` → `doctor_payroll_periods` → `doctor_fee_items` → `doctor_fee_submissions` → `m_doctor_services` → `master_doctor_service_categories`.
- Migration lama `2026_07_03_141000_add_nullable_company_id_to_master_doctor_services_and_salary_components.php` **tidak diubah** (migration historis) — begitu `m_doctor_services` didrop migration baru, bagian itu otomatis tidak relevan lagi.

### 2. Model
- Baru: `App\Models\Payroll\FeeSubmission` (`hasMany(FeeItem)`, `belongsTo(Companies::class, 'company_id')`), `App\Models\Payroll\FeeItem` (`belongsTo(FeeSubmission::class, 'submission_id')`, `belongsTo(User::class, 'user_id')` sebagai penerima, `belongsTo(MasterSalaryComponents::class, 'salary_component_id')` nullable).
- Hapus: `MasterDoctorService`, `DoctorFeeSubmission`, `DoctorFeeItem`, `DoctorPayrollPeriod`, `DoctorPayroll`, `DoctorPayrollItem`, folder `app/Models/DoctorService/`.

### 3. Service — `app/Services/Payroll/PayrollService.php`
- Fix root-cause dulu: `getMasterComponent(SalaryComponentNameEnum $nameEnum, string $companyId)` — tambah param `$companyId`, filter eksplisit `where('company_id', $companyId)->orWhereNull('company_id')`, `orderByRaw('company_id IS NULL')` supaya company-specific menang. Update 3 pemanggil existing (`handleLeaveComponent`, `handlePermissionComponent`, `handleLateArrivalComponent`) supaya kirim `$period->company_id`.
- Tambah `handleFeeItemsComponent($user, $payroll, $period, &$grossSalary, &$totalDeductions)`, dipanggil di `generatePayroll()` sejajar handler lain:
  1. Ambil `FeeItem::where('user_id', $user->id)->where('is_paid', false)->whereHas('submission', fn($q) => $q->where('company_id', $period->company_id)->whereBetween('date', [$period->start_date, $period->end_date]))->get()`.
  2. Tiap item → `PayrollItem::create()` (type=earning, `name` = snapshot `$item->name`, `amount` = `$item->amount`) — pola sama persis `handleProgramIncentiveComponent()`.
  3. Kalau total fee > 0: hitung pajak pakai `getMasterComponent(SalaryComponentNameEnum::DOCTOR_TAX, $period->company_id)` (versi yang sudah difix) → 1 `PayrollItem` lagi (type=deduction, name "Pajak Jasa Dokter") — bukan kolom terpisah kayak `doctor_payrolls` lama.
  4. Tandai `FeeItem` terkait `is_paid = true`.
- Hapus `app/Services/Payroll/DoctorPayrollService.php` seutuhnya.

### 4. Job
- Hapus `app/Jobs/GenerateDoctorPayrollJob.php` — fee ikut `GeneratePayrollJob`/`RegeneratePayrollJob` yang sudah ada.

### 5. Filament
- Hapus folder `app/Filament/Resources/Payroll/MasterDoctorServices/` seutuhnya — tidak perlu resource pengganti, admin pakai `GeneralSalaryComponents`/`CompanySalaryComponents` yang sudah ada di `MasterSalaryComponentCluster`.
- Hapus folder `app/Filament/Resources/Payroll/DoctorPayrollPeriods/` seutuhnya (termasuk `RelationManagers/PayrollsRelationManager.php`) — hasil payroll dokter otomatis muncul di resource `Payrolls` yang sudah ada.
- Rename+rebuild `app/Filament/Resources/Payroll/DoctorFeeSubmissions/` → `FeeSubmissions/` (`FeeSubmissionResource`, nav group `'Keuangan'` sama seperti sebelumnya), bentuk form sama persis `DoctorFeeSubmissionForm.php` (termasuk toggle `is_manual` + dropdown master vs manual), label digeneralisasi: "No SIP"→"No Referensi", "Nama Pasien"→"Nama/Keterangan", "Dokter"→"Penerima", opsi master dari `MasterSalaryComponents` (type=earning) bukan `MasterDoctorService`.
- `app/Filament/Resources/Payroll/Payrolls/Tables/PayrollsTable.php` — hapus import `App\Models\Payroll\DoctorPayroll` yang sudah tidak dipakai (dead import, ketemu saat riset).

### 6. View
- Hapus `resources/views/pdf/payroll/doctor-payroll.blade.php` — slip dokter otomatis pakai `pdf/payroll/payroll.blade.php` yang sudah ada (layout identik, sudah diverifikasi), sekalian menutup bug `employee_number`.

### 7. Permission (Spatie) — perlu hati-hati, ini data live
- `database/seeders/PermissionsSeeder.php` & `RoleDefaultPermissionsSeeder.php`: ganti definisi permission `view/create/edit/delete_doctor_payroll` dan blok "Doctor Fee Submission" jadi `view/create/edit/delete_fee_submission`.
- Karena permission ini sudah ter-seed di DB asli, tambahkan 1 langkah data-fix (migration atau perintah artisan sekali-jalan) yang **rename in-place** baris di tabel `permissions` (`UPDATE permissions SET name = 'view_fee_submission' WHERE name = 'view_doctor_payroll'`, dst) — supaya `role_has_permissions` yang sudah ada otomatis ikut (row-nya sama, cuma nama berubah), bukan hapus+buat baru yang bisa putus assignment role.

### 8. Cleanup tambahan (orphan, ditemukan saat riset, aman dihapus)
- `database/seeders/DoctorServiceCategorySeeder.php` (sudah broken, tidak terdaftar).
- Migration `2025_12_04_004828_create_master_doctor_service_categories_table.php` didrop lewat migration baru di langkah 1.

## Verifikasi

- `php artisan migrate` jalan bersih di DB dev (sqlite test config biarkan tetap `:memory:` — **jangan** arahkan ke DB asli untuk test, lihat Known Gaps di `README.md`).
- Generate payroll manual untuk 1 company yang punya `FeeItem` belum lunas → cek `payroll_items` muncul baris earning (nama sesuai snapshot) + 1 baris deduction pajak, `FeeItem.is_paid` jadi true.
- Download slip PDF hasil generate itu → pastikan `payroll.blade.php` render benar untuk user yang income-nya campuran (gaji tetap + fee), kolom PENDAPATAN/POTONGAN terisi dari kedua sumber.
- Cek `/admin` nav: cluster `MasterSalaryComponent` tidak lagi ada submenu "Jasa Dokter" terpisah, grup `Keuangan` punya `FeeSubmissions` menggantikan `DoctorFeeSubmissions`, tidak ada lagi menu `DoctorPayrollPeriods`.
- Cek role yang sebelumnya punya permission `view_doctor_payroll` dkk masih bisa akses `FeeSubmissions` resource setelah rename permission (spot-check 1 role admin RSU Banjarbaru).

## Referensi

- [`knowledge/markdown/analisis-payroll-2-redesign-struktur.md`](../knowledge/markdown/analisis-payroll-2-redesign-struktur.md) — analisis & verifikasi database lengkap (row count, isi data uji, rancangan "reset dari nol" sebagai referensi jangka panjang).
- [`knowledge/markdown/analisis-payroll.md`](../knowledge/markdown/analisis-payroll.md) — analisis pertama (2026-07-05).
- [`knowledge/html/analisis-payroll.html`](../knowledge/html/analisis-payroll.html) — versi HTML dengan diagram ERD.
