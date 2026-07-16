# Analisis Payroll #3 — Skema Komponen Gaji Nyata & Arsitektur "Registry + Strategy + Config" (2026-07-16)

Lanjutan dari [`analisis-payroll.md`](analisis-payroll.md) & [`analisis-payroll-2-redesign-struktur.md`](analisis-payroll-2-redesign-struktur.md). Dua dokumen itu menyelesaikan **struktur tabel** (unifikasi doctor, rename, provenance). Dokumen ini menjawab pertanyaan yang lebih dalam dan baru muncul setelah user menjabarkan aturan bisnis nyata: **"komponen gaji sesungguhnya jauh lebih rumit dari fixed/percentage — bentuk arsitektur yang benar seperti apa?"**

Status: **dokumen diskusi/desain, belum ada kode.** Ditulis untuk jadi basis keputusan sebelum implementasi besar.

---

## 1. TL;DR (buat yang tidak baca semua)

1. **Engine generik sekarang (`calculation_type ∈ {fixed, percentage, formula}`) terbukti tidak sanggup mengekspresikan SATU PUN dari 8+ aturan nyata** yang user jabarkan. `formula` malah 0 baris dipakai sejak awal (mati suri). Aturan nyata bukan "nilai tetap" atau "% dari basis" — melainkan **lookup ke matriks (pangkat×kelas)**, **lookup bracket (masa kerja)**, **rate × kuantitas dengan aturan bisnis** (telat dengan grace, lembur >2 jam), dan **rumus turunan** (izin seharian, BPJS % dari UMP/nilai deklarasi).
2. **Rekomendasi: JANGAN full-generik, JANGAN full-hardcode. Pakai 3 lapis — Registry + Strategy + Config.** Insting user ("akhirnya kita hardcode, tapi tetap ada komponen gaji") **benar**, tinggal dirapikan bentuknya:
   - **Registry** = `salary_components` tetap jadi daftar "komponen apa yang dipakai company ini" (tombol on/off + eligibility). Ini yang bikin "masing-masing company bisa ambil komponen apa aja".
   - **Strategy** = tiap `category` punya 1 kelas kalkulator PHP sendiri (logikanya di-hardcode di situ, bukan di 1 `match()` raksasa). Ganti aturan 1 company = edit 1 kalkulator, bukan bongkar `generatePayroll()` 800 baris.
   - **Config** = angka-angkanya (UMP, matriks tunjangan jabatan, transport per kelas, bracket masa kerja, threshold telat) ditaruh di **tabel config terstruktur**, biar finance bisa ubah nilai tanpa deploy. **Hardcode LOGIKANYA, table-drive ANGKANYA.**
3. **Fondasi data kurang banyak** untuk aturan baru: Kelas belum punya `level` numerik, Pangkat belum punya base salary / batas kelas / klasifikasi (pelaksana/spv/manager), belum ada matriks pangkat×kelas, belum ada UMP per company, belum ada bracket masa kerja. Semua ini harus dibangun dulu (detail §4, §6).
4. **Banyak PERTANYAAN TERBUKA yang user sendiri belum tahu/lupa** (rumus lembur holding, aturan "Izin Meninggalkan Jam Kerja", sumber "nilai deklarasi" BPJS spv/manager, detail insentif RSU). Ini **jangan ditebak** — dikumpulkan di §8 sebagai daftar yang harus dijawab sebelum kalkulator terkait dibangun.
5. **Sisi pengajuan (izin/cuti/lembur) harus diselaraskan** dengan payroll: tipe izin jadi 4 (bukan generik), tipe cuti melahirkan/keguguran/haid tidak muat di model `m_leave_types` sekarang (quota_days + reset_period tidak bisa ekspresikan "1.5 bulan seputar event"). Detail §7.

---

## 2. Aturan Bisnis Nyata (sumber: user, 2026-07-16)

Dicatat apa adanya. **Belum diverifikasi ke dokumen HR resmi** — beberapa angka user sebut "anggaplah" / "aku lupa", ditandai ⚠️.

### 2.1 PENDAPATAN

| # | Komponen | Dasar perhitungan | Company | Catatan |
|---|---|---|---|---|
| 1 | **Gaji Pokok** | Basis per **PANGKAT**, naik **8% tiap naik KELAS**. Tiap pangkat punya **batas kelas**. | Semua (?) | Butuh: base per pangkat, urutan kelas, batas kelas per pangkat. |
| 2 | **Tunjangan Jabatan** | Matriks **PANGKAT × KELAS** — tiap kombinasi punya nominal sendiri. | Holding + RSU Banjarbaru | Butuh tabel matriks. |
| 3 | **Tunjangan Transportasi** | Per **KELAS** (beda kelas beda nominal). | **Holding saja** | Butuh tabel kelas→nominal. |
| 4 | **Tunjangan Masa Kerja** | Per **lama tahun kerja** (bracket), tiap bracket nominal sendiri. | Holding + RSU Banjarbaru | Butuh tabel bracket + `joining_date` (sudah ada). |
| 5 | **Insentif** | Holding: nominal **tetap** ke orang tertentu. RSU Banjarbaru: **banyak jenis, perhitungan & penerima masing-masing**. | Holding (simpel) vs RSU (kompleks) | RSU perlu sub-spec sendiri (§8). |
| 6 | **Lembur** | Holding punya **rumus sendiri** ⚠️(angka lupa). Syarat: **harus >2 jam**. | Holding (rumus khusus) | Rumus & referensi angka = PERTANYAAN TERBUKA. |

### 2.2 POTONGAN

| # | Komponen | Dasar perhitungan | Catatan |
|---|---|---|---|
| 1 | **BPJS Kesehatan** | **Pelaksana**: 1% × **UMP**. **Supervisor & Manager**: 1% × **nilai yang diajukan perusahaan** ⚠️(mis. 3jt). | Butuh: UMP per company, klasifikasi pangkat, sumber "nilai deklarasi". |
| 2 | **BPJS Ketenagakerjaan** | Sama pola BPJS Kesehatan, tapi **2%**. | Idem. |
| 3 | **Keterlambatan (>5 menit)** | Tiap telat >5 menit potong **25.000**. TAPI holding kasih **kompensasi 1× telat/bulan** — telat pertama gratis, **potong mulai telat ke-2**. | Butuh: threshold menit, nominal, **grace count** per company. |
| 4 | **Izin Terlambat Masuk / Pulang Cepat** | **Langsung** potong ⚠️(anggap 10.000), **tidak** pakai grace seperti keterlambatan. | Beda mekanik dari #3. |
| 5 | **Cuti** | **Tidak ada potongan.** | — |
| 6 | **Izin Seharian** | Rumus: `TOTAL_PENDAPATAN / (DAY(EOMONTH(tgl)) − TUNJANGAN_TRANSPORTASI) × JUMLAH_IZIN_SEHARIAN_BULAN_ITU`. | ⚠️ Rumus Excel ini **janggal secara dimensi** (mengurangi jumlah-hari dengan nilai-rupiah transport) — WAJIB diklarifikasi (§8). |
| 7 | **Koperasi** | Khusus member koperasi, **input manual** oleh bagian koperasi/keuangan. | Butuh input per user per periode. |
| 8 | **Bon** | Tidak pasti, **input manual** oleh finance. | Idem. |

### 2.3 Komponen khusus rumah sakit (disebut sambil lalu)
- **Jasa Pelayanan** (ambulans, pasang infus, dll) — cuma di rumah sakit. Sudah ada rintisan `handleServiceFeeComponent()` + `JobEvaluationCompanies` di kode.
- **Extra Shift** — user **belum pernah menyentuh modul shift** di sistem ini. Workstream terpisah.

---

## 3. Kenapa Engine Generik Sekarang Tidak Cukup (evidence)

Referensi state saat ini: [`app/Services/Payroll/PayrollService.php`](../../app/Services/Payroll/PayrollService.php), [`app/Enum/Salary/SalaryComponentCategoryEnum.php`](../../app/Enum/Salary/SalaryComponentCategoryEnum.php).

1. **`calculation_type = formula` = 0 baris dipakai, tidak pernah diimplementasi** (temuan analisis #2 §TL;DR). Mimpi "admin ngetik rumus Excel di textfield" itu jebakan: parsing tidak aman, tidak ada yang maintain, dan tetap tidak bisa ekspresikan lookup matriks/bracket. Dead-end terbukti di dua sisi (kode & data).
2. **`fixed` / `percentage` cuma muat 2 dari ~14 bentuk aturan nyata.** Sisanya:
   - Matriks 2 dimensi (pangkat×kelas) → tidak ada bentuknya.
   - Bracket (masa kerja, rentang tahun → nominal) → tidak ada.
   - `base × (1.08 ^ langkah_kelas)` → bukan fixed, bukan % flat.
   - Rate × kuantitas **dengan aturan bisnis** (telat: potong mulai kejadian ke-2; lembur: cuma dihitung kalau >2 jam) → butuh logika, bukan rumus statik.
   - Cross-reference (BPJS = % dari UMP **atau** dari nilai deklarasi tergantung klasifikasi pangkat; izin seharian = f(total pendapatan, hari dalam bulan, transport)) → butuh akses ke hasil komponen lain + config eksternal.
3. **`PayrollService::generatePayroll()` sudah jadi ~800 baris dengan 12 handler inline.** Tiap handler beda sumber data & beda bentuk. Menambah 6+ aturan baru ke bentuk ini = 1 file makin tidak terkelola, tidak bisa dites per-komponen, dan tiap company yang beda aturan bikin `if (company == ...)` bersarang. Ini altitude yang salah.
4. **Lookup masih campur `name` vs `category`.** Sebagian sudah `category` (hasil fix 2026-07-12), tapi `handleServiceFeeComponent()` masih `selectedSalaryComponent($component->name)` (match string) — sisa inkonsistensi.

**Kesimpulan bagian ini:** bukan soal "kurang 1-2 fitur di engine generik", tapi **abstraksinya memang di altitude yang salah**. Aturan gaji ini genuinely bespoke per kategori (dan sebagian per company). Memaksakan 1 engine data-driven universal = terus menambal sesuatu yang fondasinya tidak cocok.

---

## 4. Verifikasi Fondasi Data Saat Ini (apa yang ADA vs KURANG)

### 4.1 Yang SUDAH ada & bisa dipakai
| Aset | Lokasi | Kegunaan buat aturan baru |
|---|---|---|
| `user_profiles.joining_date` | [`UserProfiles.php:53`](../../app/Models/User/UserProfiles.php) | Hitung masa kerja (tunjangan #4). **Ada.** |
| `user_profiles.pangkat_id/kelas_id/golongan_id/employee_type_id` | `UserProfiles.php:48-51` | Kunci semua lookup pangkat/kelas. **Ada.** |
| `attendance_policies` (company_id, name, type, value) | [`create_attendance_policies_table`](../../database/migrations/2026_03_15_134939_create_attendance_policies_table.php) | Key-value config generik per company → **reuse** buat threshold telat (5mnt), potongan telat (25rb), grace (1), potongan izin (10rb). **Ada, tinggal dipakai.** |
| `extra_hour_rules` (company, allowanceable morph, amount_per_hour) | [migration](../../database/migrations/2026_03_15_135856_create_extra_hour_allowance_rules_table.php) | Tunjangan kelebihan jam kerja. Ada. |
| `shift_overtime_costs` (shift, overtime_type, max_hours, overtime_rate) | migration `2026_03_03_111058` | Basis lembur per shift. Ada (tapi lembur holding mungkin pakai rumus lain — §8). |
| `salary_components` + `salary_component_assignments` | `app/Models/SalaryComponent/`, `app/Models/Payroll/` | Registry (Layer 1). **Dipertahankan, ganti peran.** |
| `m_holidays` (cuti bersama) | dibuat 2026-07-16 | Sudah dipakai di saldo cuti; nanti dipakai proпорsi hari kerja. |

### 4.2 Yang KURANG (harus dibangun)
| Kebutuhan | Kondisi sekarang | Untuk aturan |
|---|---|---|
| **Kelas punya urutan/level numerik** | `m_kelas` cuma `name`+`company_id` ([`Kelas.php`](../../app/Models/Master/Kelas.php)) | Gaji pokok +8%/kelas, batas kelas |
| **Pangkat: base salary + batas kelas + kelas increment %** | `m_pangkats` cuma `name`+`company_id` ([`Pangkats.php`](../../app/Models/Master/Pangkats.php)) | Gaji pokok |
| **Klasifikasi pangkat (pelaksana / supervisor / manager)** | Tidak ada | BPJS |
| **Matriks Tunjangan Jabatan (company, pangkat, kelas → nominal)** | Tidak ada | Tunjangan jabatan |
| **Transport per kelas (company, kelas → nominal)** | Tidak ada | Tunjangan transportasi |
| **Bracket masa kerja (company, min_year, max_year → nominal)** | Tidak ada | Tunjangan masa kerja |
| **UMP per company + sumber "nilai deklarasi" BPJS** | Tidak ada | BPJS |
| **Input manual per user per periode (Koperasi, Bon)** | Tidak ada | Potongan manual |
| **Kategori company (hospital vs non)** | `m_companies` tidak ada kolomnya (dikonfirmasi lagi 2026-07-16) | Filter komponen khusus RS (jasa pelayanan) |

---

## 5. Arsitektur Usulan: Registry + Strategy + Config (3 Lapis)

Ini inti rekomendasi. Menjawab langsung insting user ("hardcode tapi tetap ada komponen gaji").

```
┌─ LAYER 1: REGISTRY ───────────────────────────────────────────────┐
│ salary_components (katalog) — "komponen X (kategori C) dipakai      │
│ company Y". Tombol ON/OFF + eligibility tipe karyawan.             │
│ 1 baris = 1 komponen yang muncul di slip company itu.             │
│ category → menunjuk ke 1 Strategy (Layer 2).                       │
└───────────────────────────────────────────────────────────────────┘
              │ payroll ambil daftar komponen aktif company
              ▼
┌─ LAYER 2: STRATEGY (hardcoded, 1 kelas per kategori) ─────────────┐
│ interface ComponentCalculator {                                    │
│     calculate(User $u, PayrollContext $ctx): PayrollItem[]         │
│ }                                                                  │
│ GajiPokokCalculator, TunjanganJabatanCalculator, BpjsCalculator,  │
│ KeterlambatanCalculator, IzinSeharianCalculator, LemburCalculator,│
│ ... — logika bisnis di-HARDCODE di sini, terisolasi per komponen. │
│ Registry: category => calculator class (bukan match() raksasa).   │
└───────────────────────────────────────────────────────────────────┘
              │ tiap calculator baca angkanya dari...
              ▼
┌─ LAYER 3: CONFIG (table-driven, editable finance tanpa deploy) ───┐
│ pangkat_base_salaries, position_allowances (matriks pangkat×kelas),│
│ transport_allowances, tenure_allowances, company UMP,             │
│ attendance_policies (sudah ada), payroll_manual_lines (koperasi/  │
│ bon), m_kelas.level, pangkat.classification ...                   │
└───────────────────────────────────────────────────────────────────┘
```

### 5.1 Prinsip yang jadi pegangan
1. **Hardcode LOGIKA, table-drive ANGKA.** Rumus "base × 1.08^langkah_kelas" hidup di kode (jarang berubah, dan kalau berubah memang butuh developer). UMP, nominal transport, matriks jabatan → di tabel (berubah tiap tahun, finance harus bisa ubah sendiri).
2. **1 kategori = 1 kalkulator = 1 tanggung jawab.** Bisa dites sendiri (`GajiPokokCalculatorTest`), bisa dibaca tanpa scroll 800 baris, dan variasi per company jadi cabang kecil DI DALAM kalkulator itu (atau lewat config), bukan `if` bersarang di service raksasa.
3. **`PayrollContext`** = object pembawa data periode + company + hasil-antara (mis. total pendapatan, total transport) supaya kalkulator yang butuh cross-reference (izin seharian, BPJS) tidak query ulang. Urutan eksekusi kalkulator ditentukan eksplisit (pendapatan dulu, lalu potongan yang bergantung total pendapatan).
4. **`source_type` di `payroll_items` (sudah ada) diisi tiap kalkulator** → provenance tetap kepakai, tiap baris slip tahu asalnya dari kalkulator mana.
5. **Registry Layer 1 tetap "swalayan komponen".** Company tinggal centang komponen mana yang aktif. Company yang tidak punya "Tunjangan Transportasi" ya tidak usah di-assign — kalkulatornya tidak pernah dipanggil untuk company itu.
6. **YAGNI per kalkulator.** Aturan yang benar-benar 1 company & tidak akan berubah (mis. rumus lembur holding kalau ternyata unik) boleh hardcode konstanta DI kalkulatornya dengan komentar `// ponytail: konstanta lembur holding, pindah ke config kalau company lain butuh beda`. Tidak semua kalkulator butuh tabel config di hari pertama.

### 5.2 Kenapa BUKAN full-hardcode (konstanta di kode untuk semua)
Karena UMP, matriks tunjangan jabatan, transport per kelas, bracket masa kerja **berubah rutin** (UMP tiap tahun, struktur tunjangan tiap ada revisi HR). Kalau semua konstanta di kode, tiap perubahan angka = ganti kode + lint + deploy + risiko. Finance harus bisa ubah nominal sendiri lewat UI. Jadi angka WAJIB di tabel.

### 5.3 Kenapa BUKAN full-generik (1 engine data-driven universal)
Sudah dibuktikan di §3 — abstraksinya di altitude salah, `formula` mati, dan 14 bentuk aturan tidak bisa diseragamkan tanpa akhirnya membangun bahasa pemrograman mini di dalam tabel (kompleksitas pindah, bukan hilang).

---

## 6. Pemetaan Tiap Komponen → Strategy + Config

Bentuk konkret (nama tabel/kolom = usulan, bisa disesuaikan saat eksekusi).

### Pendapatan
| Komponen | Calculator | Config yang dibaca |
|---|---|---|
| Gaji Pokok | `GajiPokokCalculator` | `m_kelas.level` (baru), `pangkat_salaries` (pangkat_id → base_amount, max_kelas_level, increment_pct default 8). Rumus: `base × (1+increment)^(kelas.level − 1)`, dibatasi `max_kelas_level`. |
| Tunjangan Jabatan | `TunjanganJabatanCalculator` | `position_allowances` (company_id, pangkat_id, kelas_id → amount). Lookup langsung. |
| Tunjangan Transportasi | `TunjanganTransportasiCalculator` | `transport_allowances` (company_id, kelas_id → amount). Cuma di-assign ke holding lewat Registry. |
| Tunjangan Masa Kerja | `TunjanganMasaKerjaCalculator` | `tenure_allowances` (company_id, min_years, max_years → amount) + `joining_date`. |
| Insentif (holding) | `InsentifCalculator` | `incentives` (sudah ada) — nominal tetap per orang per periode. |
| Insentif (RSU) | *sub-spec terpisah* | Butuh spesifikasi sendiri (§8) — jangan dibangun sebelum aturannya jelas. |
| Lembur | `LemburCalculator` | ⚠️ rumus & angka = PERTANYAAN TERBUKA. Syarat >2 jam sudah jelas. `shift_overtime_costs`/`extra_hour_rules` mungkin sumbernya, atau rumus holding khusus. |

### Potongan
| Komponen | Calculator | Config yang dibaca |
|---|---|---|
| BPJS Kesehatan | `BpjsKesehatanCalculator` | `companies.ump` (baru), `pangkat.classification` (baru: pelaksana/spv/manager), sumber "nilai deklarasi" spv/manager ⚠️. Rate 1%. |
| BPJS Ketenagakerjaan | `BpjsKetenagakerjaanCalculator` | Idem, rate 2%. (Bisa 1 kalkulator param rate.) |
| Keterlambatan | `KeterlambatanCalculator` | `attendance_policies` (threshold 5mnt, nominal 25rb, grace 1) + `attendance_records` (jumlah telat >5mnt bulan itu). Potong mulai kejadian ke-(grace+1). |
| Izin Terlambat/Pulang Cepat | `IzinPotonganCalculator` | `attendance_policies` (nominal per kejadian, mis. 10rb) + attendance_requests type izin subtipe itu. Tanpa grace. |
| Izin Seharian | `IzinSeharianCalculator` | ⚠️ rumus janggal — klarifikasi dulu. Butuh `PayrollContext.totalPendapatan`, hari dalam bulan (`EOMONTH`), transport, jumlah izin seharian. Jalan SETELAH kalkulator pendapatan. |
| Cuti | *(tidak ada potongan)* | Tidak perlu kalkulator potongan. Cuti mengurangi kuota saldo (sisi pengajuan), bukan gaji. |
| Koperasi | `ManualDeductionCalculator` | `payroll_manual_lines` (baru: user_id, period_id, category, amount, note) diinput finance/koperasi. |
| Bon | `ManualDeductionCalculator` | Idem (kategori beda). |

**Catatan urutan:** kalkulator pendapatan jalan lebih dulu → `PayrollContext` kumpulkan `totalPendapatan` & `totalTransport` → baru kalkulator yang bergantung (Izin Seharian, mungkin BPJS kalau basisnya gaji) jalan.

---

## 7. Sisi Pengajuan (Izin / Cuti / Lembur) yang Harus Diselaraskan

Payroll di atas mengonsumsi data pengajuan, jadi bentuk pengajuan harus cocok.

### 7.1 IZIN — jadi 4 subtipe eksplisit
Sekarang `attendance_req_permission_details` generik (1 `permission_type_id`). Perlu dipetakan ke 4 subtipe yang tiap-tiap punya efek payroll beda:
1. **Izin Terlambat Masuk** → potongan flat (§2.2 #4).
2. **Izin Pulang Cepat** → potongan flat.
3. **Izin Seharian** → rumus proporsi (§2.2 #6).
4. **Izin Meninggalkan Jam Kerja** → ⚠️ **user LUPA aturannya** — PERTANYAAN TERBUKA (§8). Jangan ditebak.

Kemungkinan cukup pakai `m_permission_types` yang sudah ada (kalau ada) + tag efek payroll per tipe. Perlu cek struktur `m_permission_types` saat eksekusi.

### 7.2 CUTI — tipe baru yang tidak muat di model sekarang
`m_leave_types` sekarang = `quota_days` + `reset_period` (yearly/monthly/none). Cukup untuk **Cuti Tahunan** (12 hari, sudah jalan + saldo + cuti bersama). TIDAK cukup untuk:
- **Cuti Melahirkan** — 1.5 bulan sebelum + 1.5 bulan sesudah melahirkan. Bukan "kuota hari tahunan", tapi entitlement **seputar tanggal event** (perkiraan lahir).
- **Cuti Keguguran** — 1.5 bulan setelah keguguran. Event-based.
- **Cuti Haid** — hari ke-1 & ke-2 haid, tiap bulan. Bukan kuota tahunan, tapi **per-siklus bulanan, maks 2 hari**.

→ Model `m_leave_types` perlu diperluas (mis. kolom `basis` = `annual_balance` | `event_based` | `monthly_recurring`, plus parameter durasi). **Bug reversal saldo** (saldo cuti tidak dikembalikan saat reject/cancel — temuan analisis attendance 2026-07-16) makin penting ditutup di sini karena tipe cuti nambah.

### 7.3 LEMBUR
Diajukan → disetujui → dihitung payroll (syarat >2 jam). Sudah ada alur pengajuan overtime. Perlu pastikan `total_hours` yang disetujui yang dipakai kalkulator, dan syarat >2 jam ditegakkan (di pengajuan atau di kalkulator — putuskan di mana).

---

## 8. PERTANYAAN TERBUKA (WAJIB dijawab sebelum kalkulator terkait dibangun)

Ini yang user sendiri belum tahu/lupa. **Jangan ditebak — tiap tebakan salah = gaji orang salah hitung.**

1. **Rumus Lembur holding** — angka/multiplier persisnya apa, merujuk ke tarif mana? (User: "aku lupa deh angka-angkanya ini merujuk kemana"). Apakah pakai `shift_overtime_costs`/`extra_hour_rules` yang sudah ada, atau rumus lain?
2. **Izin Meninggalkan Jam Kerja** — aturan & efek payroll-nya apa? (User: "aku lupa sekali untuk menanyakan hal ini").
3. **BPJS "nilai yang diajukan perusahaan" (spv/manager)** — sumbernya di mana? Per company (1 angka)? Per user? Per pangkat? Input manual per periode? Ini yang membedakan dari basis UMP.
4. **Klasifikasi pangkat pelaksana/spv/manager** — turunkan dari data yang ada (nama pangkat? job position level?) atau kolom baru eksplisit di pangkat?
5. **UMP per company** — 1 angka per company, berubah tahunan. Di mana disimpan & siapa update? (Bisa `companies.ump` atau `attendance_policies`/config.)
6. **Rumus Izin Seharian** — rumus Excel yang diberikan janggal dimensinya (`jumlah_hari − nilai_transport`). Maksud sebenarnya apa? Kemungkinan: `(total_pendapatan − transport) / jumlah_hari_kerja_bulan × jumlah_izin`? WAJIB diklarifikasi angka & maksudnya.
7. **Insentif RSU Banjarbaru** — "banyak sekali, perhitungan & penerima masing-masing". Ini modul sendiri — perlu daftar jenis insentif + rumus + kriteria penerima per jenis. Sub-spec terpisah, jangan dicampur ke rilis pertama.
8. **Batas kelas per pangkat & base gaji pokok** — angka nyata untuk holding & RSU Banjarbaru (buat seed awal + validasi kalkulator).
9. **Cuti melahirkan/keguguran/haid** — apakah dibayar penuh? Ada batas per tahun? Butuh dokumen pendukung? (Buat modeling `m_leave_types` baru.)
10. **Kategori company (hospital vs non)** — dipakai untuk gating komponen khusus RS (jasa pelayanan, extra shift). Perlu kolom kategori di `m_companies` (rekomendasi lama analisis #1 §6.1, belum jalan).

---

## 9. Rekomendasi Eksekusi Bertahap (jangan boil the ocean)

Urutan yang meminimalkan risiko & tidak menunggu SEMUA pertanyaan terbuka terjawab:

**Fase 0 — Fondasi data (tanpa ubah logika payroll dulu)**
- `m_kelas.level` (int, urutan), `pangkat_salaries` (base + max kelas + increment), klasifikasi pangkat, `m_companies.category` + UMP.
- Belum sentuh `PayrollService`. Cuma nyiapkan tanah.

**Fase 1 — Rangka Strategy (refactor tanpa ubah hasil)**
- Bikin interface `ComponentCalculator` + `PayrollContext` + registry `category → calculator`.
- Pindahkan handler yang SUDAH jalan & jelas (Gaji Pokok, Izin flat, Keterlambatan) ke kalkulator masing-masing. Hasil angka harus IDENTIK dengan sekarang (regression test pakai data existing).
- `PayrollService::generatePayroll()` menyusut jadi orchestrator: ambil komponen aktif company → panggil kalkulatornya berurutan.

**Fase 2 — Komponen yang aturannya SUDAH jelas**
- Gaji Pokok +8%/kelas, Tunjangan Jabatan (matriks), Transportasi (per kelas), Masa Kerja (bracket), BPJS (begitu sumber nilai deklarasi & UMP jelas), Keterlambatan dengan grace, Manual (Koperasi/Bon).
- Tiap komponen: 1 tabel config + 1 kalkulator + 1 test.

**Fase 3 — Komponen menunggu jawaban (§8)**
- Lembur holding, Izin Seharian (rumus), Izin Meninggalkan Jam Kerja, Insentif RSU. Bangun begitu pertanyaannya terjawab, tidak sebelum.

**Fase 4 — Sisi pengajuan cuti baru**
- Perluas `m_leave_types` (event-based/monthly), tutup bug reversal saldo, sinkronkan tipe izin 4 subtipe.

**Paralel/terpisah:** modul Shift & Jasa Pelayanan (user belum sentuh shift — workstream sendiri).

---

## 10. Referensi File Kode

- [`app/Services/Payroll/PayrollService.php`](../../app/Services/Payroll/PayrollService.php) — engine sekarang (akan di-refactor jadi orchestrator).
- [`app/Enum/Salary/SalaryComponentCategoryEnum.php`](../../app/Enum/Salary/SalaryComponentCategoryEnum.php) — kategori (akan diperluas jadi ~14 kategori nyata).
- [`app/Models/Master/{Pangkats,Kelas,Golongans}.php`](../../app/Models/Master/) — fondasi yang perlu ditambah kolom.
- [`app/Models/User/UserProfiles.php`](../../app/Models/User/UserProfiles.php) — `joining_date`, pangkat/kelas/golongan.
- [`database/migrations/2026_03_15_134939_create_attendance_policies_table.php`](../../database/migrations/2026_03_15_134939_create_attendance_policies_table.php) — config key-value per company (reuse buat telat/izin).
- [`app/Models/Attendance/AttendanceRecords.php`](../../app/Models/Attendance/AttendanceRecords.php) — sumber telat & jam kerja.
- Analisis pendahulu: [`analisis-payroll.md`](analisis-payroll.md), [`analisis-payroll-2-redesign-struktur.md`](analisis-payroll-2-redesign-struktur.md).
- Temuan attendance (bug reversal saldo, 2 sumber attendance terpisah) — hasil riset 2026-07-16 di percakapan sesi ini (belum ada dokumen terpisah).
