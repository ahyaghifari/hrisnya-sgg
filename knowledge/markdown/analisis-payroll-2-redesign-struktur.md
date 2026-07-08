# Analisis Payroll #2 — Verifikasi Database Asli & Rencana Redesign Struktur (2026-07-08)

Lanjutan dari [`analisis-payroll.md`](analisis-payroll.md) (2026-07-05). Dokumen itu menjawab "apakah gap-nya nyata secara kode". Dokumen ini menjawab pertanyaan lanjutan: **"apakah modul ini benar-benar dipakai di database asli, dan kalau mau dirombak strukturnya, bentuknya seperti apa?"** — ditulis dari query langsung ke `hris_db` (bukan cuma baca kode), plus rancangan skema kalau dimulai dari nol.

## TL;DR

1. **Payroll (reguler maupun dokter) belum benar-benar dipakai operasional.** Terakhir digenerate 2026-05-07 (2 bulan mandek), dan **57 dari 76 payroll (75%) hasilnya kosong** (0 baris `payroll_items`) karena mayoritas user belum punya `salary_components` ter-assign.
2. **Semua data `doctor_fee_submissions`/`doctor_fee_items` (4 submission, 8 item) adalah data uji coba**, bukan produksi — `patient_name` selalu "Denis", `sep_number` "TEST"/"TEST2"/dst, "dokter" penerimanya "Staff IT"/"Admin". **Tidak ada data nyata yang perlu diselamatkan** kalau strukturnya dirombak.
3. Modul yang **justru aktif dipakai rutin** sampai sekarang (terakhir 2026-07-05, 3 hari sebelum dokumen ini ditulis): attendance request (cuti 42, izin 78, lembur 30 — total 150 baris).
4. `calculation_type = 'formula'` di `m_salary_components`: **0 baris** pernah dipakai — fitur yang ada di UI (dropdown) tapi tidak pernah diimplementasikan di `PayrollService` (`fixedSalaryComponents()`/`percentageSalaryComponents()` cuma handle `fixed`/`percentage`) DAN tidak pernah dipakai user. Dead end di dua sisi.
5. Karena momentnya murah (belum ada data nyata terikat ke skema lama), ini kesempatan pas untuk redesign sebelum modul ini benar-benar dipakai produksi.

## 1. Verifikasi Row Count Database Asli

Query `SELECT COUNT(*)` langsung ke `hris_db` (2026-07-08):

| Tabel | Jumlah baris | Catatan |
|---|---|---|
| `users` | 509 | |
| `payrolls` | 76 | 57 di antaranya (75%) 0 `payroll_items` |
| `payroll_items` | 51 | 34 earning, 17 deduction |
| `payroll_periods` | 4 | Jan, Mar, 2× Apr 2026 |
| `salary_components` (assignment) | 22 | 15 JobPosition, 5 Pangkat, 2 Company — kecil untuk 509 user |
| `m_salary_components` | 12, **semua `company_id = NULL`** | 0 baris `calculation_type=formula` |
| `m_doctor_services` | 3, **semua `company_id = NULL`** | Operator, Anestesi, konsultasi |
| `doctor_fee_submissions` / `doctor_fee_items` | 4 / 8 | **semua data uji, lihat §2** |
| `doctor_payroll_periods` / `doctor_payrolls` / `doctor_payroll_items` | 1 / 1 / 2 | cuma pernah di-generate 1 kali |
| `program_incentives` | 2 | |
| `trainers`, `trainer_score_variables` | 3, 3 | |

### 1.1 Payroll kosong per periode (detail)

```
Jan 2026 (company A): 19 payroll, 15 kosong (79%)
Mar 2026 (company A): 13 payroll, 10 kosong (77%)
Apr 2026 (company A): 23 payroll, 12 kosong (52%)
Apr 2026 (company B): 21 payroll, 20 kosong (95%)
```

Bukan 1 kejadian aneh — konsisten di 4 dari 4 periode yang pernah ada. Akar masalahnya: `salary_components` (tabel assignment) cuma 22 baris total, jauh lebih sedikit dari jumlah jabatan/pangkat yang seharusnya di-cover untuk 509 user — banyak jabatan/pangkat memang belum di-assign komponen apa pun, jadi `handleBaseSalary()`/`handleJobPositionFixedComponents()` pulang tangan kosong buat user-user itu.

### 1.2 Perbandingan aktivitas antar modul (created_at terbaru)

| Modul | Aktivitas terakhir | Jumlah data |
|---|---|---|
| **Attendance request (cuti/izin/lembur)** | **2026-07-05** — paling baru di seluruh DB | 150 (42 cuti, 78 izin, 30 lembur) |
| User documents | 2026-05-12 | 10 |
| Job applications | 2026-05-08 | 16 |
| **Payroll** | 2026-05-07 — berhenti 2 bulan | 76, tapi 75% kosong |
| **Doctor fee** | 2026-01-12 — berhenti 6 bulan | 4, semua dummy |

**Kesimpulan**: attendance request adalah satu-satunya modul yang benar-benar dipakai rutin sampai sekarang. Payroll cuma pernah dicoba beberapa kali lalu ditinggal — kemungkinan besar karena data assignment-nya memang belum lengkap (data-entry gap), bukan karena workflow-nya sudah selesai dipakai.

## 2. Isi Asli `doctor_fee_submissions`/`doctor_fee_items` — Semuanya Data Uji

4 baris `doctor_fee_submissions`:

| sep_number | patient_name | company | tanggal |
|---|---|---|---|
| TEST | Denis | Holding | 2025-12-16 |
| TEST2 | Denis | Holding | 2025-12-16 |
| TEST 22 | Denis | Company lain | 2026-01-08 |
| TEST 1234 | Denis | Company lain | 2026-01-12 |

8 baris `doctor_fee_items` — "dokter" penerimanya **"Staff IT"** dan **"Admin"**, bukan nama dokter sungguhan. Temuan penting soal pola pemakaian: dari 8 item, cuma 4 yang link ke `m_doctor_services` via `doctor_service_id`. Sisanya (`"Visit Bersama"` 200rb, `"Operasi"` 100rb, `"Bius"` 500rb) **`doctor_service_id`-nya NULL** — nama-nama ini bahkan tidak ada di master (isinya cuma Operator/Anestesi/konsultasi). Ini bukan bug — form-nya (`DoctorFeeSubmissionForm.php`) memang punya toggle eksplisit `is_manual` untuk kasus input ad-hoc tanpa lewat katalog. Implikasinya buat redesign: **kolom link ke master komponen harus tetap nullable**, jangan dipaksa FK wajib.

## 3. Rancangan Skema — Reset dari Nol (tanpa terikat penamaan lama)

Kalau tidak terikat sama sekali ke skema `doctor_*` yang ada sekarang, ini bentuk idealnya — satu pipeline untuk semua jenis income (assignment tetap maupun klaim per kejadian), tidak dipisah per jenis bisnis:

```
m_companies
  id, code, name, location, category (nullable: hospital/hotel/agro/...), is_active
                                     ^ ditambah dari awal, nutup gap "tidak ada tipe company"
                                       (rekomendasi §6.1 analisis pertama)

m_employee_types
  id, name, company_id

m_salary_components                        ← katalog SATU-SATUNYA untuk semua jenis pendapatan/potongan
  id, company_id (nullable=shared), company_category (nullable),
  name, type (earning/deduction), calculation_type (fixed/percentage),
  default_value, is_active
                 ^ 3-tier scope (company spesifik → kategori company → global) dari awal.
                   calculation_type TIDAK punya opsi 'formula' — 0 baris pernah dipakai (§TL;DR #4), YAGNI.

salary_component_assignments                ← WHO gets WHAT, aturan tetap (rule-driven)
  id, salary_component_id (FK),
  assignable_type/id (poly: JobPositions | Pangkats | Companies),
  required_employee_type_id (FK, nullable = semua tipe)   ← nutup gap eligibility tetap/kontrak
  amount, percentage, is_overrideable

fee_claims                                   ← WHO gets WHAT, per kejadian (event-driven) — generik,
  id, company_id (wajib), reference_number,     bukan cuma dokter, bisa dipakai lini bisnis manapun
  subject_name, category (nullable, mis. "jasa_dokter"),
  status, notes, date, created_by

fee_claim_items
  id, claim_id (FK), user_id (penerima),
  salary_component_id (FK, nullable ← boleh ad-hoc tanpa master, lihat §2),
  name (snapshot, wajib), amount, is_paid, is_manual

payroll_periods
  id, company_id (wajib — bukan nullable, fungsinya memang selalu wajib diisi), start_date, end_date, processed_at

payrolls                                     ← 1 slip per user per periode, SATU tabel untuk semua orang
  id, user_id, payroll_period_id, gross_salary, total_deductions, net_salary, is_paid, paid_at

payroll_items                                ← rincian snapshot, dari SEMUA sumber
  id, payroll_id, source_type (enum: 'assignment'|'fee_claim'|'attendance'|'manual'),
  source_id (nullable, jejak asal), name, type, calculation_type, quantity, unit, unit_amount, amount, note
```

### 3.1 Beda paling penting vs sekadar "unifikasi minimal"

| | Unifikasi minimal (dari tabel lama, rename+gabung) | Reset dari nol |
|---|---|---|
| `payroll_items.source_*` | tidak ada — snapshot buta | **ada provenance** (assignment/klaim/lembur/manual), tanpa perlu tabel `*_items` terpisah per sumber |
| Eligibility kepegawaian | gap terpisah, belum digarap | **langsung include** (`required_employee_type_id`) |
| Kategori company | rekomendasi §6.1 lama, belum jalan | **langsung include** (`m_companies.category` + `m_salary_components.company_category`) |
| `calculation_type='formula'` | masih ada di enum, 0 dipakai | **dihapus** |
| Nama tabel assignment | `salary_components` (ambigu, bentrok nama dengan katalog `m_salary_components`) | `salary_component_assignments` — jelas beda peran |

## 4. Rencana Eksekusi yang Direkomendasikan (Skala Lebih Kecil dari Reset Total)

Reset total (§3) adalah bentuk ideal, tapi butuh effort lebih besar (5 fitur sekaligus: unifikasi doctor, provenance, kategori company, eligibility, rename tabel assignment). Rencana eksekusi konkret yang sudah disiapkan (lebih murah, fokus ke masalah yang sudah pasti — unifikasi doctor→payroll saja, tanpa 3 fitur tambahan) ada di **[`planning/2026-07-08-redesign-struktur-payroll.md`](../../planning/2026-07-08-redesign-struktur-payroll.md)**, termasuk:
- Skema baru `fee_submissions`/`fee_items` (generalisasi `doctor_fee_submissions`/`doctor_fee_items`, `salary_component_id` nullable sesuai temuan §2)
- Migration order, daftar model/service/Job/Filament Resource/PDF view yang kena dampak (hasil inventarisasi menyeluruh, ~15 file)
- Fix bug company-scope `getMasterComponent()` yang ditemukan di analisis pertama (root-cause, sekalian nutup di titik yang sama)
- Fix bug PDF `$payroll->doctor->employee_number` (kolom yang tidak ada di skema `users`)
- Keputusan yang sudah dikonfirmasi: fee dokter ditarik otomatis lewat 1 tombol "Generate Payroll" bulanan yang sama dengan payroll reguler, bukan tombol generate terpisah.

**Status: masih tahap planning, belum ada migration/kode yang dieksekusi.**

## 5. Referensi

- [`analisis-payroll.md`](analisis-payroll.md) — analisis pertama (2026-07-05), gap eligibility kepegawaian & kategori company dari sisi kode.
- [`../html/analisis-payroll.html`](../html/analisis-payroll.html) §2.5 dan §8 — versi HTML dengan diagram ERD dan detail bug queue/company-scope.
- [`../../planning/2026-07-08-redesign-struktur-payroll.md`](../../planning/2026-07-08-redesign-struktur-payroll.md) — rencana eksekusi konkret.
- File kode kunci: `app/Services/Payroll/PayrollService.php`, `DoctorPayrollService.php`, `app/Models/Concerns/BelongsToCompanyOrShared.php`, `app/Filament/Resources/Payroll/DoctorFeeSubmissions/Schemas/DoctorFeeSubmissionForm.php` (pola `is_manual` toggle).
