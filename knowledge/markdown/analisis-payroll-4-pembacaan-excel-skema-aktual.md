# Analisis Payroll #4 — Pembacaan File Excel "Contoh Payroll (Skema Aktual)" (2026-07-16)

Companion dari [`analisis-payroll-3-skema-komponen-real.md`](analisis-payroll-3-skema-komponen-real.md). Dokumen #3 menyusun arsitektur usulan + daftar pertanyaan terbuka (§8). Dokumen ini = **pembacaan langsung file Excel dummy** yang user berikan (`knowledge/Salinan Contoh Payroll (Skema Aktual).xlsx`), dibaca sel-per-sel **termasuk rumus mentahnya** lewat PhpSpreadsheet (`scripts/dump_xlsx.php`). Beberapa pertanyaan terbuka #3 **terjawab** di sini, beberapa muncul baru.

Status: **temuan empiris dari data dummy.** Angka = dummy (finance isi nilai riil belakangan), tapi **STRUKTUR & RUMUS = nyata** dan jadi acuan skema.

---

## 1. Ringkasan: File Ini Berisi Apa

8 sheet, **semuanya sisi PENDAPATAN + tabel referensi** — TIDAK ada sisi POTONGAN sama sekali (no BPJS, no telat, no izin, no koperasi/bon), dan **tidak ada slip payroll utuh** yang menjumlahkan semua. Jadi file ini = "kumpulan tabel lookup komponen pendapatan", bukan contoh 1 slip gaji jadi.

| Sheet | Isi | Relevansi skema |
|---|---|---|
| `Sheet9` | Pemetaan **Jabatan → Pangkat → Kelas** (start & batas kelas per jabatan) | Fondasi: job position → pangkat + kelas |
| `Gapok` | Base **Gaji Pokok per Pangkat**, per company (Holding vs RS) | Komponen #1 |
| `Tunjangan Jabatan (RS Syifa Med)` | Matriks **Pangkat × Kelas → nominal** (RS Banjarbaru) | Komponen #2 |
| `Tunjangan Jabatan (Holding)` | Matriks **Pangkat × Kelas → nominal** (Holding) | Komponen #2 |
| `Tunjangan Tranpostasi` | **Kelas → nominal** (Holding) | Komponen #3 |
| `Tunjangan Masa Kerja` | **Tahun kerja (bracket) → nominal**, per company | Komponen #4 |
| `Jasa Pelayanan` | Rumus rasio JE (rumah sakit) | Konfirmasi `handleServiceFeeComponent()` |
| `Copy of R- Overtime` | **Rumus Lembur** + 2 contoh baris | Komponen #6 (yang tadi "lupa") |

---

## 2. Fondasi: Kelas itu Numerik 1–15, Pangkat Punya Start & Batas Kelas

Dari `Sheet9` + header matriks tunjangan jabatan, terkonfirmasi struktur pangkat×kelas yang di dokumen #3 masih "belum ada di data":

- **Kelas = level numerik 1 sampai 15** (bukan cuma nama). Ini yang harus ditambahkan ke `m_kelas` (`level` int).
- **6 Pangkat berjenjang** dengan **kelas awal (start)** dan **batas (max)** masing-masing:

| Pangkat | Start Kelas | Max Kelas | Contoh jabatan (dari Sheet9) |
|---|---|---|---|
| Pelaksana | 1 | 5 | Cleaning Service, Laundry, Scanner RM |
| Staff | 1 (RS) / 2 (Holding) | 5 | Perawat Poli, Kasir, Satpam, Radiografer |
| Koordinator | 2 (RS) / 3 (Holding) | 7 | Kepala Ruangan, Koordinator Depo Farmasi |
| Supervisor | 5 | 9 | Kasi Keperawatan, Kepala IPSRS, Kepala Keuangan |
| Manager | 7 | 13 | Kabid, Kabag, Manager SBU |
| Direktur | 9 | 15 | Direktur |

**Catatan penting**: jenjang di `Sheet9` ini pakai istilah **Direksi/Manager/Supervisor/Koordinator/Staff/Pelaksana** sebagai grup pangkat, dan tiap **jabatan** (job position) dipetakan ke grup + kelas tertentu (mis. "Kabid. Pelayanan Medik (batas: Terapis Wicara): Kelas 7"). Artinya di sistem, mapping-nya: **Job Position → Pangkat (grup) + Kelas awal**. Ini menyambung ke `user_profiles.pangkat_id` + `kelas_id` yang sudah ada — tinggal datanya diisi konsisten dengan Sheet9.

---

## 3. Komponen per Sheet (struktur & rumus)

### 3.1 Gaji Pokok (`Gapok`)
Base **per Pangkat**, beda per company. Note eksplisit di sel C1: **"Ada kenaikan 8% tiap promosi kelas"**.

| Pangkat | Holding | RS Syifa Medika |
|---|---|---|
| Pelaksana | 200.000 | 100.000 |
| Staff | 210.000 | 110.000 |
| Koordinator | 220.000 | 120.000 |
| Supervisor | 230.000 | 130.000 |
| Manager | 240.000 | 140.000 |
| Direktur | 250.000 | 150.000 |

⚠️ **File ini TIDAK menunjukkan rumus penerapan 8%-nya** (cuma base + catatan). Jadi rumus pastinya masih perlu konfirmasi: `gapok = base_pangkat × 1,08^(kelas_sekarang − kelas_awal_pangkat)`? Atau eksponennya dari kelas 1? (§5 Q-baru). Angka base 200rb dst jelas dummy (tidak realistis buat gaji pokok), tapi strukturnya = base-per-pangkat-per-company.

### 3.2 Tunjangan Jabatan — Matriks Pangkat × Kelas (`Tunjangan Jabatan (RS)` & `(Holding)`)
**Bentuknya matriks 2D**: baris = Kelas (1–15), kolom = Pangkat (Pelaksana/Staff/Koordinator/Supervisor/Manager/Direktur), sel = nominal tunjangan. **1 matriks per company** (RS & Holding beda nominal). Tiap kolom pangkat cuma terisi antara start–max kelasnya; di atas max, nilai **flat** (di Excel pakai absolute ref ke sel max).

Contoh nilai matriks **Holding** (sebagian):
- Staff kelas 2→13: 12rb, 13rb, 20rb, 27rb, 34rb, 41rb, 48rb, 55rb (naik ~7rb/kelas)
- Supervisor kelas 6 (base) = 135rb, naik 26rb/kelas sampai max
- Direktur kelas 9 (base) = 745rb, naik 54rb/kelas

→ Untuk sistem: **1 tabel `position_allowances` (company_id, pangkat_id, kelas_id → amount)**. Di Excel nilainya dibangun inkremental (base + step), tapi di DB cukup simpan **nilai final per sel** (finance isi angka jadinya, tidak perlu simpan rumus increment-nya). Header sheet nyebut "kenaikan tahunan sesuai performa perusahaan" — artinya matriks ini di-review/ubah tiap tahun → **wajib table-driven, editable finance**, sesuai prinsip #3 §5.1.

### 3.3 Tunjangan Transportasi (`Tunjangan Tranpostasi`)
Per **Kelas** saja (bukan pangkat), **Holding only**:

| Kelas | 1 | 2–3 | 4 | 5 | 6 | ... | 15 |
|---|---|---|---|---|---|---|---|
| Nominal | 0 | 20.000 | 45.000 | 70.000 | 95.000 | +25rb/kelas | 320.000 |

→ Tabel `transport_allowances` (company_id, kelas_id → amount). Kelas 1 = 0 (kosong).

### 3.4 Tunjangan Masa Kerja (`Tunjangan Masa Kerja`)
Bracket **per tahun kerja (1–30)**, beda per company. Tahun 1 = 0, mulai tahun 2:

| | Tahun 2 | increment/tahun | Tahun 30 |
|---|---|---|---|
| Holding | 50.000 | +7.500 | 260.000 |
| RS Syifa Medika | 20.000 | +5.000 | 160.000 |

→ Tabel `tenure_allowances` (company_id, years → amount), atau bracket (min–max tahun → amount). Dihitung dari `user_profiles.joining_date` (sudah ada). Linear, jadi bisa juga formula (base + step×(tahun−2)) — tapi karena mungkin tidak selalu linear di data riil, aman simpan per-tahun sebagai tabel.

### 3.5 Jasa Pelayanan (`Jasa Pelayanan`) — KONFIRMASI implementasi existing
Rumus sel: `Tunjangan = (Nilai_JE_posisi / Total_JE_perusahaan) × Total_Jaspel`.
Contoh: (375 / 119.485) × 10.028.500 = **31.474**.

→ **Persis** logika `handleServiceFeeComponent()` yang sudah ada di [`PayrollService.php`](../../app/Services/Payroll/PayrollService.php) (rasio JE × pool jaspel). Cuma di rumah sakit. Tidak perlu ubah logika, cuma pastikan komponen ini cuma di-assign ke company kategori hospital (butuh kategori company — #3 §8 Q10).

### 3.6 Lembur (`Copy of R- Overtime`) — RUMUS YANG TADI "LUPA", sekarang terbaca lengkap

Rumus mentah kolom Rp (per baris lembur):
```
= IF(Status="TL", 3.500.000, IF(Status="TM", 3.000.000, 0))
  / (DAY(EOMONTH(tanggal, 0)) * 7)
  * ( (MAX(2, MIN(jam_lembur, 4)) * 2) - 0.5 )
```

Diterjemahkan:
1. **Base rate** dari kolom `Status`: **TL → 3.500.000**, **TM → 3.000.000**, selain itu 0. ⚠️ arti "TL"/"TM" belum jelas (§5 Q-baru).
2. **`DAY(EOMONTH(tanggal,0)) × 7`** = (jumlah hari dalam bulan) × 7 → pembagi. (7 = asumsi jam kerja/hari.)
3. **`jam_lembur`** = `ROUNDDOWN(jam selesai − mulai, 0)` (dibulatkan ke bawah, jam bulat).
4. **Multiplier** = `(clamp(jam, 2, 4) × 2) − 0,5`:
   - jam ≤ 2 → (2×2)−0,5 = **3,5**
   - jam = 3 → (3×2)−0,5 = **5,5**
   - jam ≥ 4 → (4×2)−0,5 = **7,5** (mentok, jam >4 tidak nambah)

Contoh nyata dari file:
- Baris TL, 3 jam, bulan 30 hari: 3.500.000/(30×7) × 5,5 = **91.667**
- Baris TM, 3 jam, bulan Feb-ish (28 hari): 3.000.000/(28×7) × 5,5 = **78.571**

⚠️ **Diskrepansi dengan yang user bilang**: user bilang "harus >2 jam baru dapat lembur", tapi rumusnya pakai `MAX(2, ...)` = **minimum dihitung 2 jam** (bahkan <2 jam tetap dibayar setara 2 jam, multiplier 3,5). Kemungkinan aturan ">2 jam" ditegakkan di **tahap input** (cuma lembur >2 jam yang diinput), bukan di rumus. Perlu konfirmasi mana yang benar (§5 Q-baru).

**Penting**: ini rumus **khusus Holding** (user bilang "lembur di holding punya perhitungannya masing-masing"). Company lain mungkin pakai `shift_overtime_costs`/`extra_hour_rules` yang sudah ada. Jadi `LemburCalculator` harus bisa **beda strategi per company**.

---

## 4. Update Status Pertanyaan Terbuka #3 §8

| # | Pertanyaan | Status setelah baca Excel | Status setelah jawaban user (§7) |
|---|---|---|---|
| Q1 | Rumus lembur holding | ✅ **TERJAWAB** — rumus lengkap di §3.6. Sisa: arti TL/TM & aturan >2jam. | Arti TL/TM masih nunggu (user akan tanya lagi). Aturan >2jam masih ambigu. |
| Q2 | Izin Meninggalkan Jam Kerja | ❌ Masih terbuka (tidak ada di file). | ❌ Masih terbuka — user konfirmasi ADA rumus sendiri, tapi lupa detailnya. |
| Q3 | BPJS "nilai deklarasi" spv/manager | ❌ Masih terbuka (no BPJS sheet). | ✅ **TERJAWAB** — 1 angka tetap per company (bukan per user/pangkat/kelas). Sisa ambiguitas: pangkat mana saja yang masuk kelompok "declared value" vs "UMP" (lihat §7.1). |
| Q4 | Klasifikasi pangkat pelaksana/spv/manager | ✅ **TERJAWAB sebagian** — 6 pangkat berjenjang jelas di §2. | Masih perlu pemetaan eksplisit 6 pangkat → 2 kelompok BPJS (§7.1). |
| Q5 | UMP per company | ❌ Masih terbuka (tidak ada di file). | ✅ **TERJAWAB** — UMP beda per company (bukan 1 angka nasional), dan ada role tertentu yang boleh mengubahnya (bukan hardcode/seed sekali doang). |
| Q6 | Rumus Izin Seharian | ❌ Masih terbuka (no potongan sheet). | ❌ Masih terbuka — user sendiri tidak yakin rumusnya, perlu verifikasi ulang ke sumber asli (bukan tebakan). |
| Q7 | Insentif RSU | ❌ Masih terbuka (tidak ada di file). | ✅ **TERJAWAB, tapi arahnya berubah** — bukan dibangun sebagai kalkulator otomatis, lihat §7.2 (keputusan: fitur IMPORT). |
| Q8 | Base gapok & batas kelas per pangkat | ✅ **TERJAWAB** — base + start/max kelas. Sisa: rumus eksponen 8%. | ✅ **TERJAWAB PENUH** — rumus additive, BUKAN compound (§7.3, koreksi penting dari asumsi awal §3.1/§6). |
| Q9 | Cuti melahirkan/keguguran/haid | ❌ Masih terbuka (file cuma pendapatan). | ✅ **TERJAWAB sebagian** — dibayar penuh (100%, tidak ada potongan), konsisten pola umum cuti. Sisa: syarat dokumen, apakah cuti haid hangus kalau tidak diambil. |
| Q10 | Kategori company (hospital) | ➖ Tidak di file. | ➖ Masih belum ditanyakan eksplisit. |

## 5. Pertanyaan BARU yang muncul dari Excel

1. **Arti "TL" & "TM"** di kolom Status lembur (base 3,5jt vs 3jt) — kategori apa? User akan tanya lagi ke sumbernya, **masih terbuka**.
2. **Aturan ">2 jam" lembur** — ditegakkan di input (cuma >2jam diinput) atau rumus benar pakai floor 2 jam? Rumus saat ini bahkan <2jam dibayar setara 2 jam. **Masih terbuka.**
3. ~~Rumus penerapan 8% gaji pokok~~ — **TERJAWAB, lihat §7.3.**
4. **Angka 3,5jt/3jt di lembur** — apakah "gaji referensi bulanan" tetap, atau ambil dari gaji aktual karyawan? **Masih terbuka**, belum ditanyakan ke user.
5. **Pembagi "× 7"** di lembur = jam kerja/hari — selalu 7, atau beda per shift/company? **Masih terbuka.**

---

## 6. Implikasi ke Skema (nambah/ubah dari #3)

Semua ini **memperkuat arsitektur Registry + Strategy + Config** di #3, dan mengisi bentuk config tabelnya jadi konkret:

- **`m_kelas.level`** (int 1–15) — WAJIB, jadi kunci semua matriks/urutan.
- **`m_pangkats`**: tambah `start_kelas_level`, `max_kelas_level`, dan base gapok per company (bisa tabel `pangkat_salaries` company_id×pangkat_id→base, karena base beda per company).
- **`position_allowances`** (company_id, pangkat_id, kelas_id → amount) — matriks tunjangan jabatan (§3.2). Simpan nilai FINAL, bukan rumus increment.
- **`transport_allowances`** (company_id, kelas_id → amount) — §3.3.
- **`tenure_allowances`** (company_id, year → amount, atau bracket) — §3.4.
- **`LemburCalculator`** strategi per-company: Holding pakai rumus §3.6 (base per status TL/TM, /(hari×7), multiplier clamp 2–4). Company lain pakai `shift_overtime_costs`/`extra_hour_rules`. Konstanta TL/TM & 7 → config, jangan hardcode buta.
- **Jasa Pelayanan** — logika existing sudah benar (§3.5), tinggal gating ke company hospital.
- Sisi POTONGAN (BPJS, telat, izin seharian, koperasi, bon) **tidak ada di file ini** → tetap nunggu jawaban #3 §8 Q2/Q3/Q5/Q6 sebelum dibangun.

---

## 7. Addendum: Jawaban User (2026-07-16, lanjutan sesi yang sama)

Jawaban langsung dari user, per prioritas yang diajukan di §4/§5. Dicatat apa adanya + implikasi ke skema.

### 7.1 BPJS Kesehatan/Ketenagakerjaan — "1 angka tetap per company"

**Jawaban**: untuk kelompok Supervisor & Manager, basis potongannya BUKAN UMP, melainkan **1 nilai tetap yang ditentukan tiap company** (bukan per user, bukan per pangkat/kelas individual — cuma 1 angka, company-wide).

**Implikasi skema**: butuh 1 kolom/tabel config per company, mis. `companies.bpjs_declared_base` (decimal, nullable). `BpjsKesehatanCalculator`/`BpjsKetenagakerjaanCalculator` pilih basis:
- Kalau pangkat user masuk kelompok "pelaksana" → basis = `companies.ump`.
- Kalau masuk kelompok "spv/manager" → basis = `companies.bpjs_declared_base`.
- Rate: Kesehatan 1%, Ketenagakerjaan 2% — dari basis yang dipilih.

**⚠️ Masih ambigu (BELUM ditanyakan eksplisit ke user)**: aturan asli cuma sebut 2 ujung ("Pelaksana" vs "Supervisor dan Manager"), padahal ada 6 tingkat pangkat (Pelaksana/Staff/Koordinator/Supervisor/Manager/Direktur — §2). **Staff, Koordinator, dan Direktur masuk kelompok mana?** Asumsi kerja sementara (JANGAN dieksekusi tanpa konfirmasi): Pelaksana+Staff+Koordinator = basis UMP (jajaran non-manajerial), Supervisor+Manager+Direktur = basis declared-value (jajaran manajerial) — pola ini konsisten dengan pembagian "grup" di `Sheet9` (yang juga megelompokkan Direksi/Manager/Supervisor sebagai 1 blok vs Koordinator/Staff sebagai blok lain). **Tetap perlu dikonfirmasi eksplisit ke user/HR sebelum dibangun** — jangan anggap ini jawaban final.

### 7.2 UMP — beda per company, dan bisa diubah (bukan hardcode)

**Jawaban**: "UMP sebenarnya bisa semua company, dan ada yang memang bisa mengubahnya" — dibaca sebagai: (a) UMP **beda-beda per company** (bukan 1 angka nasional — masuk akal, RSU Banjarbaru/Barabai/Holding Surabaya di kota/provinsi berbeda dengan UMP berbeda), dan (b) **ada mekanisme/role tertentu yang boleh mengubah nilainya** — bukan konstanta seed sekali lalu dilupakan, harus ada UI edit dengan permission gate yang jelas (kemungkinan besar peran Finance/HC).

**Implikasi skema**: `companies.ump` (decimal, editable lewat Resource/form Companies atau halaman config khusus payroll), digate permission yang sesuai (kemungkinan reuse pola `sistem_keuangan` atau permission Shield `Update:Companies` kalau memang bagian dari edit company biasa — perlu diputuskan siapa pemegang perannya saat eksekusi, bukan sekarang).

### 7.3 Rumus 8% Gaji Pokok — KOREKSI PENTING: additive, bukan compound

**Jawaban user**: "Kalo dia naik KELAS artinya dia bakal naik gaji pokoknya 8%, jadi kalo naik kedua kalinya maka dari basenya naik jadi 16%."

**Ini mengoreksi asumsi di §3.1/§6 dokumen ini dan di `analisis-payroll-3-skema-komponen-real.md` §6** yang menulis rumus sebagai `base × 1,08^langkah_kelas` (compound/eksponensial). **Yang benar, berdasar jawaban user, adalah ADDITIVE (linear)**:

```
gaji_pokok = base_pangkat × (1 + 0.08 × jumlah_kenaikan_kelas)
```

di mana `jumlah_kenaikan_kelas` = selisih level kelas user sekarang dari **kelas awal (start_kelas) pangkatnya** (bukan dari kelas 1 global — start_kelas beda per pangkat, lihat tabel §2). Contoh: base Rp1.000.000, naik kelas 2× → `1.000.000 × (1 + 0.08×2)` = `1.000.000 × 1,16` = **Rp1.160.000** (BUKAN `1.000.000 × 1,08²` = Rp1.166.400 — bedanya kecil di 2 langkah tapi makin melebar di langkah banyak, jadi harus dipastikan benar dari awal, bukan asumsi).

**Update ke Fase 0 (`analisis-payroll-3` §9)**: `GajiPokokCalculator` implementasinya jadi:
```php
$stepsUp = $user->kelas->level - $pangkat->start_kelas_level; // >= 0
$gajiPokok = $basePangkat * (1 + 0.08 * $stepsUp);
```
Rate 8% sendiri sebaiknya tetap config (`pangkat_salaries.class_increment_rate` atau semacamnya), bukan angka mati di kalkulator, in case company lain punya rate beda (belum dikonfirmasi apakah 8% ini Holding-only atau berlaku semua company — file `Gapok` cuma bilang "ada kenaikan 8%" tanpa spesifik per company, tapi base gapok sendiri beda Holding vs RS, jadi rate-nya JUGA sebaiknya dianggap bisa beda per company sampai dikonfirmasi sama).

### 7.4 Izin Seharian — masih terbuka, user sendiri tidak yakin

**Jawaban**: "Mungkin itu yang sebenarnya rumusnya aku juga kurang tau sih hehe." — **tetap OPEN**, jangan dibangun dari tebakan rumus di §3 dokumen sebelumnya. Perlu diverifikasi ke sumber asli (file Excel HR yang sesungguhnya, atau tanya langsung ke finance) sebelum `IzinSeharianCalculator` dibangun.

### 7.5 Izin Meninggalkan Jam Kerja — masih terbuka, dikonfirmasi ADA aturan khusus

**Jawaban**: "karena aku lupa, dan seingatku memang ada perhitungannya sendiri" — user CONFIRM ada rumus/aturan khusus (bukan cuma potongan flat generik), tapi lupa detailnya. **Tetap OPEN**, user akan tanya ulang. Jangan disamakan dengan "Izin Terlambat Masuk/Pulang Cepat" (potongan flat 10rb) tanpa konfirmasi — kemungkinan besar beda mekanik.

### 7.6 Insentif RSU — keputusan arsitektur berubah: FITUR IMPORT, bukan kalkulator otomatis

**Jawaban user (penting, mengubah rencana)**:
- **Holding**: cuma ~2 penerima insentif saat ini, nilainya **diinput langsung** (manual) — ini **sudah persis** cara kerja `Incentive` model yang ada sekarang (`incentives` table, input manual per user per periode). **Tidak perlu perubahan** untuk kasus Holding.
- **RSU Banjarbaru**: jumlah penerima banyak, dan **cara hitungnya dinamis/sering berubah** (bukan formula stabil yang layak di-hardcode di kalkulator). User secara eksplisit maunya **fitur IMPORT** (kemungkinan dari file Excel yang sudah dihitung finance/HR di luar sistem), bukan sistem menghitung sendiri.

**Implikasi skema — ini keputusan arsitektur, bukan cuma detail teknis**: `InsentifCalculator` di rencana §5/§9 dokumen #3 **cukup untuk kasus Holding saja** (baca `incentives` yang sudah diinput manual, seperti sekarang). Untuk RSU, **tidak dibangun kalkulator rumus** — dibangun **fitur import** (Excel/CSV) yang menulis langsung ke tabel `incentives` (kolom yang sudah ada: `user_id`, `name`, `amount`, `incentive_date`, dst — lihat model `Incentive`). Ini **konsisten dengan prinsip "Registry+Strategy+Config"**: kalau logikanya genuinely tidak stabil/terlalu dinamis untuk di-encode di kode maupun di tabel config, JANGAN dipaksa — biarkan tetap dihitung manual di luar sistem (Excel yang sudah biasa dipakai finance), sistem cuma jadi **saluran masuk data** (import), bukan mesin hitung. Ini juga secara langsung memvalidasi filosofi "hardcode LOGIKA, table-drive ANGKA" — untuk kasus RSU, bahkan tidak ada "LOGIKA" yang stabil untuk di-hardcode, jadi solusinya turun 1 tingkat lagi: cuma jadi data-entry (import), bukan kalkulator sama sekali.

Detail fitur import (nanti, bukan sekarang): kemungkinan besar reuse pattern Maatwebsite Excel yang sudah dipakai di project ini (README menyebut `Maatwebsite Excel` sebagai dependency existing, dipakai `EmployeeImportController`/`AttendanceImportService` — pola import sudah ada, tinggal dicontek untuk Insentif).

### 7.7 Cuti Melahirkan/Keguguran/Haid — dibayar penuh

**Jawaban**: "Cuti melahirkan/keguguran dan haid tetap dibayar penuh." — konfirmasi tidak ada potongan gaji untuk 3 tipe cuti ini (konsisten dengan aturan umum "Cuti tidak ada potongan" di §2.2 dokumen #3). **Sisa yang belum ditanyakan**: syarat dokumen pendukung (surat dokter/keterangan), dan apakah cuti haid yang tidak diambil di bulan itu **hangus** atau tidak — ini menentukan apakah perlu tracking saldo untuk cuti haid atau cukup "boleh ambil maks 2 hari/bulan, tidak ada carry-over".

### 7.8 TL/TM Lembur — masih terbuka

**Jawaban**: "aduh TL TM itu aku belum ngerti nih aku akan tanyakan lagi." — **tetap OPEN**, user akan cari tahu.

---

## 7B. Addendum Kedua (2026-07-17)

### 7B.1 BPJS — resolusi ambiguitas §7.1: bukan grup pangkat tetap, tapi toggle + override per pangkat

**Jawaban**: pertanyaan di §7.1 ("Staff/Koordinator/Direktur masuk kelompok mana?") ternyata **salah kerangka** — bukan soal 2 kelompok pangkat tetap (pelaksana vs spv/manager) yang berlaku universal semua company. Kondisi riilnya: **beda per company** (mis. di rumah sakit ada kemungkinan basisnya beda lagi), jadi harus jadi **pengaturan per company**, bukan aturan hardcode 2 kelompok.

**Desain baru (menggantikan §7.1)**:
- 1 toggle per company: "apakah ada perbedaan basis BPJS antar pangkat?" (setting company-scoped, bagian dari `requiredSettings()` BPJS).
- Kalau **tidak dicentang** → semua pangkat pakai basis `UMP` (company-wide, sesuai §7.2).
- Kalau **dicentang** → basis per pangkat diambil dari kolom `m_pangkats.bpjs_basis` (kolom ini **sudah ada** dari Fase 0, `2026_07_16_150100_add_payroll_classification_to_m_pangkats_table.php`, sudah dipakai `AbstractBpjsCalculator` sekarang — isinya string `'ump'` | `'declared_value'` per pangkat, BUKAN angka override; `'declared_value'` artinya "pakai `settings.bpjs_declared_value`", 1 angka yang tetap company-wide seperti §7.1 asal, cuma sekarang jadi opsional per pangkat bukan wajib semua). Kolom ini saat ini semua `NULL` — diisi lewat form edit Pangkat (Resource yang sudah ada, tinggal ditambah 1 field). Pangkat yang `bpjs_basis`-nya `NULL` atau `'ump'` tetap fallback ke UMP walau toggle aktif.
- Rate tetap seperti semula: Kesehatan 1%, Ketenagakerjaan 2% dari basis yang dipilih.

**Implikasi kode**: `AbstractBpjsCalculator` baca toggle via `Setting::resolve('bpjs_basis_berbeda', $companyId)` dulu — kalau tidak aktif, basis LANGSUNG UMP (tidak lagi baca `bpjs_basis` pangkat sama sekali, tidak lagi skip diam-diam kalau kolom itu NULL). Kalau aktif, baru baca `bpjs_basis` per pangkat, fallback UMP kalau NULL. Detail lengkap & kode di `planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md` §2.

### 7B.2 Izin Terlambat Masuk + Izin Pulang Cepat — digabung jadi 1 komponen gaji

**Jawaban**: "itu bisa ngga kita bikin menjadi 1 komponen saja, karena itu sebenarnya 1 grup dan biaya potongannya sama" — dikonfirmasi digabung, dengan cakupan: **sisi absensi (pengajuan izin/laporan) tetap 2 tipe terpisah** (`PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK` & `IZIN_PULANG_CEPAT` tidak berubah), **cuma sisi payroll yang digabung** jadi 1 `PayrollComponentEnum` case.

**Implikasi kode**: `PayrollComponentEnum` — hapus 1 dari 2 case (`IZIN_TERLAMBAT_MASUK`/`IZIN_PULANG_CEPAT`), sisakan 1 (mis. rename jadi `IZIN_TERLAMBAT_PULANG_CEPAT`). Gabung `IzinTerlambatMasukCalculator` + `IzinPulangCepatCalculator` (yang sekarang sudah ada, keduanya extends `AbstractIzinFlatCalculator`) jadi 1 Calculator yang menjumlah kejadian dari **kedua** kategori absensi (`IZIN_TERLAMBAT_MASUK` + `IZIN_PULANG_CEPAT`) dikali 1 rate flat yang sama. `company_payroll_components` & `requiredSettings()` juga ikut disederhanakan (1 toggle bukan 2).

### 7B.3 Izin Seharian — rumus dikonfirmasi

**Jawaban**: rumus asli dari Excel adalah `=T8/DAY(EOMONTH($B$2;0)-Q8)*AE8`, dikonfirmasi user bagian `-Q8` di dalam `DAY(EOMONTH(...))` adalah bukan bagian dari perhitungan hari (kemungkinan salah tulis ulang manual formula dari Excel, Q8/Tunjangan Transportasi tidak berperan di pembagi ini). Rumus final yang dipakai:

```
rate_harian = T8 / DAY(EOMONTH($B$2, 0))
potongan    = rate_harian * AE8
```

Di mana:
- `T8` = Total Pendapatan seluruh komponen pendapatan (setara `$context->grossSalary` sebelum potongan izin ini).
- `DAY(EOMONTH($B$2,0))` = jumlah hari dalam bulan periode payroll berjalan (`$period`).
- `AE8` = jumlah kejadian Izin Seharian di periode itu.

**Status berubah dari §7.4/§8**: `IzinSeharianCalculator` **sudah bisa dibangun** (rumus tidak lagi tertunda) — catatan implementasi: perlu urutan Calculator yang tepat, karena `T8` (total pendapatan) harus sudah terkumpul penuh di `PayrollContext` sebelum `IzinSeharianCalculator` jalan (jalankan di akhir urutan komponen EARNING, sebelum komponen DEDUCTION lain yang tidak bergantung total pendapatan).

### 7B.4 Izin Meninggalkan Jam Kerja — dikonfirmasi TIDAK masuk payroll sama sekali

**Jawaban**: "Izin meninggalkan jam kerja tidak ada di komponen gaji di slip gaji juga tidak diperhitungkan" — bukan lagi "masih terbuka, tertunda", tapi **final: memang tidak ada dampak payroll**. Ini murni kategori absensi (tetap ada di `PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA` untuk pengajuan/laporan izin), tapi **tidak butuh entry di `PayrollComponentEnum`/Calculator sama sekali** — bukan "belum tersedia", tapi "memang tidak pernah akan ada" untuk komponen ini.

**Implikasi kode**: hapus `IZIN_MENINGGALKAN_JAM_KERJA` dari `PayrollComponentEnum::notYetAvailable()` (dan dari enum itu sendiri kalau sempat ditambahkan) — cukup biarkan hidup di `PermissionTypeCategoryEnum` (sisi absensi) saja.

---

## 8. Ringkasan Status Setelah Addendum §7

**Cukup jelas untuk mulai dibangun (Fase 0 + sebagian Fase 2 di `analisis-payroll-3` §9)**:
- Fondasi kelas/pangkat (level, start/max kelas per pangkat) — dari §2.
- Gaji Pokok — rumus additive terkonfirmasi (§7.3), base per pangkat/company dari `Gapok` (§3.1).
- Tunjangan Jabatan (matriks), Transportasi (per kelas), Masa Kerja (bracket) — dari §3.2-3.4, tidak ada perubahan.
- Jasa Pelayanan — logika existing sudah benar (§3.5).
- UMP per company, editable — struktur jelas (§7.2), tinggal tentukan siapa pemegang izin edit saat eksekusi.
- Insentif Holding — tidak perlu perubahan dari yang sudah ada.
- Cuti melahirkan/keguguran/haid — dibayar penuh, tidak perlu logika potongan (tapi tetap perlu extend `m_leave_types` buat basis event/bulanan, lihat `analisis-payroll-3` §7.2).

**Berubah arahnya (bukan kalkulator, tapi fitur import)**:
- Insentif RSU — dibangun sebagai fitur IMPORT (§7.6), bukan `IncentiveCalculator` dinamis. Ini mengurangi kompleksitas Fase 3 di `analisis-payroll-3` §9.

**Diselesaikan lewat Addendum Kedua §7B (2026-07-17) — sekarang cukup jelas untuk dibangun**:
- BPJS — bukan lagi 2 kelompok pangkat hardcode, jadi toggle per company + override per pangkat lewat `m_pangkats.bpjs_basis` (§7B.1, menggantikan §7.1).
- Izin Terlambat Masuk + Izin Pulang Cepat — digabung jadi 1 `PayrollComponentEnum` case & 1 Calculator, sisi absensi tetap 2 tipe terpisah (§7B.2).
- Rumus Izin Seharian — terkonfirmasi `T8 / DAY(EOMONTH($B$2,0)) * AE8`, `IzinSeharianCalculator` bisa dibangun (§7B.3, menggantikan §7.4).
- Izin Meninggalkan Jam Kerja — final TIDAK ada dampak payroll sama sekali, tidak perlu Calculator/enum case (§7B.4, menggantikan §7.5).

**Masih 100% terbuka (JANGAN ditebak, jangan dibangun dulu)**:
- Arti TL/TM lembur (§7.8).
- Aturan >2 jam lembur — floor di rumus vs gate di input (§5.2).
- Basis angka 3,5jt/3jt lembur — tetap atau dari gaji aktual (§5.4).
- Pembagi ×7 di lembur — universal atau per shift/company (§5.5).
- Rate 8% gaji pokok — Holding-only atau berlaku semua company (§7.3).
- Kategori company (hospital/non) — belum ditanya (Q10).

---

## 9. Referensi
- File sumber: `knowledge/Salinan Contoh Payroll (Skema Aktual).xlsx` (data dummy, aman).
- Script pembaca: `scripts/dump_xlsx.php` (PhpSpreadsheet, dump sel + rumus mentah — reusable buat file Excel lain).
- Arsitektur & pertanyaan terbuka: [`analisis-payroll-3-skema-komponen-real.md`](analisis-payroll-3-skema-komponen-real.md).
- Implementasi existing yang terkonfirmasi: `handleServiceFeeComponent()` di [`PayrollService.php`](../../app/Services/Payroll/PayrollService.php).
