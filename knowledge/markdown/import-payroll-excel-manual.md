# Import Payroll Manual dari Excel → struktur `Payroll` HRIS existing

> Dibuat 2026-08-31, diperbarui 2026-09-01. Status: **ANALISIS SELESAI, IMPORTER BELUM DI-CODING.**
> Rekonsiliasi data HRIS ↔ Excel berjalan (kelas SELESAI, jabatan tinggal sedikit — lihat §5.6).
> Script ops kelas/jabatan sudah dibuat di `scripts/` (§6).
>
> Konteks: atasan minta gerak cepat. Payroll Agustus RSU Syifa Medika Banjarbaru **tidak**
> digenerate lewat calculator HRIS — semua angka sudah final di Excel
> (`knowledge/gaji-agustus-banjarbaru.xlsx`, 344 KB, angka disembunyikan/rahasia saat dibagikan
> tapi struktur & header terbaca). Yang dibutuhkan: **import angka jadi itu ke tabel
> `Payroll` + `PayrollItem` + `PayrollPeriod` yang sudah ada**, supaya slip PDF + email jalan
> lewat infra existing tanpa modul paralel.
>
> **Pola ini akan dipakai RS lain** (RSU Syifa Medika Barabai, dst) — dokumen ini jadi acuan
> kontrak sheet + mapping + langkah, bukan sekali pakai.

---

## 1. Keputusan arah (SUDAH dikonfirmasi user 2026-08-31)

| # | Keputusan |
|---|---|
| 1 | **Masuk ke `Payroll`/`PayrollItem`/`PayrollPeriod` existing** — BUKAN tabel `manual_payroll_slips` terpisah. Alasan: `MyPayrollSlipPage` (portal karyawan), `PayrollsPage`/`PayrollDetailPage` (admin), `SendPayrollSlipJob` + `PayrollSlipMail` + PDF terenkripsi `pdf.payroll.payroll` — semua jalan gratis. Kode baru = **importer + tombol trigger saja**. |
| 2 | **Dokter (19 baris `STATUS KARYAWAN = Mitra`) di-SKIP total** dari import v1. Tidak punya `User` HRIS → tidak bisa punya `Payroll`. **Tidak dikirim email juga** (keputusan user). Kalau nanti perlu rekap dokter → ditambah belakangan (opsi: `User` minimal, atau tabel `external_payroll_recipients` tipis). |
| 3 | **Slip = PDF attachment**, tampilan **sama persis** slip existing. |
| 4 | **Label baris slip = label HRIS** (`PayrollComponentEnum::label()`), bukan label Excel. |
| 5 | **Email target = `profile.email_pribadi` ?? `user.email`** (perilaku `SendPayrollSlipJob` sekarang). **Kolom AV (Alamat Email) Excel DIABAIKAN.** User update `email_pribadi` karyawan manual. Importer tidak menyentuh data email. |
| 6 | **Field identitas slip di-SNAPSHOT dari Excel** ke `payrolls` (kolom baru, mis. `import_meta` json), TIDAK ambil dari relasi HRIS. Alasan: data jabatan HRIS beda 33%, kelas beda 28% (lihat §5). Slip blade fallback: `$payroll->import_meta['jabatan'] ?? $payroll->user->activeJobPosition?->jobPosition?->name ?? '-'`. Field yang di-snapshot: jabatan, status karyawan, kelas, tgl masuk/masa kerja, unit, nama/NIP Excel. **`import_meta` = JSON plaintext (cast `array`), jadi TIDAK boleh menyimpan nominal.** Angka gaji tetap di kolom terenkripsi (`PayrollEncrypted`): `payrolls.gross_salary/total_deductions/net_salary`, `payroll_items.amount`, `payroll_manual_lines.amount`. `net_excel` Excel cuma dibandingkan saat pratinjau (warning), tidak dipersist. |

---

## 2. Struktur file Excel (`gaji-agustus-banjarbaru.xlsx`)

- **1 sheet** `Gaji Skema Baru (JE)`. Baris 1 = header. Baris 2 = serial periode (`46265` di kol B). Baris 3+ data.
- **Dikelompokkan per unit**: ada **51 baris "section header"** (E kosong, B berisi nama unit non-numerik: `IGD/LAB/GIGI`, `MANAJEMEN`, `KEUANGAN`, `APOTEK`, `LABORATORIUM`, `PERAWAT RANAP LT. 2 GEDUNG SHOFA`, dst). Importer: lacak `unit` dari section header terakhir.
- **Trailer**: baris ≥ baris pertama yang B-nya "JUMLAH" (~baris 540) = rekap/total/nama non-payroll → **skip semua**.
- **499 baris data**: 273 Karyawan Tetap + 192 Karyawan Kontrak + 15 Karyawan Orientasi + **19 Mitra** (dokter, skip).
- Kolom terpakai A..AY. `highest column` ALS tapi kosong. **Tidak ada kolom tersembunyi.**
- NIP (kol E): staff = numerik polos (`21092021389`). Dokter = prefix `DU-`/`DG-`/`DS-` (`DU-09032020106`). HRIS `users.nip` = **numerik polos**. Importer normalisasi: `preg_replace('/^D[A-Z]-?/i', '', trim($nip))`, match exact → zero-pad 11 digit.

### Header lengkap
| Kol | Header | Kol | Header |
|---|---|---|---|
| A | NO | S | Gaji Pokok |
| B | NAMA | T | Tunj. Jabatan Skema Baru |
| C | PERIODE GAJI (serial) | U | Insentif |
| D | TGL PEMBAYARAN GAJI (serial) | V | Tunj. Dokter/Jabatan Khusus |
| E | NIP | W | Tunj. Jabatan/ Beban Kerja |
| F | NIK | X | TOTAL GAJI SKEMA BARU *(subtotal)* |
| G | STATUS | Y | Jasa Pelayanan Baru |
| H | TANGGAL LAHIR | Z | Jumlah Gaji+Jasa Pelayanan *(subtotal)* |
| I | JABATAN | AA | tambahan jasa |
| J | Golongan Jabatan | AB | LEMBUR (Dihitung Stlh 2 Jam) |
| K | STATUS KARYAWAN | AC | EXTRA SHIFT |
| L | Tanggal Masuk (serial) | AD | Jasa Supir Ambulance & Perawat (Rujukan Pasien) |
| M | Bulan Kerja *(formula DATEDIF)* | AE | Jasa Paket Infus |
| N | Tunjangan Masa Kerja >24 bln | AF | Jumlah Terima Skema Baru *(subtotal)* |
| O | Nilai JE | AG | POTONGAN BPJS 1% |
| P | Kelas | AH | POTONGAN BPJSTK (JHT 2 %) |
| Q | Gaji Dasar | AI | JUMLAH TELAT > 5 MENIT/BULAN *(hitungan)* |
| R | % Kenaikan | AJ | POTONGAN TELAT >5 MENIT |
| | | AK | JUMLAH TELAT >5 MENIT ISTIRAHAT/TIDAK ABSEN *(hitungan)* |
| | | AL | POTONGAN TELAT >5 MENIT ISTIRAHAT/TIDAK ABSEN |
| | | AM | POTONGAN IZIN TERLAMBAT MASUK/PULANG CEPAT |
| | | AN | JMLH (CUTI, IZIN, SAKIT) *(hitungan)* |
| | | AO | POTONGAN IZIN dan CUTI /HARI |
| | | AP | POTONGAN KOPERASI |
| | | AQ | POTONGAN BON |
| | | AR | TOTAL POTONGAN *(subtotal)* |
| | | AS | TOTAL GAJI YANG DITERIMA SKEMA BARU *(subtotal)* |
| AT | NO REKENING BSI | AV | Alamat Email |
| AU | NAMA REKENING BSI | AX | Jumlah Hari Efektif Kontrak / Cuti Melahirkan |
| AW | *(counter, abaikan)* | AY | Jumlah Potongan |
| AZ | *(catatan bebas — abaikan, keputusan user)* | | |

---

## 3. Mapping kolom Excel → `PayrollItem`

`PayrollComponentEnum` = vocabulary tertutup. `PayrollItem.source_type` = string bebas → kolom
tanpa padanan enum tetap masuk dengan tag kustom.

### Pendapatan (`type = earning`)
| Excel | `PayrollItem.name` | `source_type` | Catatan |
|---|---|---|---|
| S | Gaji Pokok | `gaji_pokok` | |
| **T + W** (dijumlah, 1 baris) | Tunjangan Jabatan | `tunjangan_jabatan` | W = "Tunj. Jabatan/Beban Kerja" = tunjangan jabatan juga (konfirmasi user) |
| U | Insentif | `insentif` | |
| **V** | *(label per perusahaan, hardcode)* | `tunjangan_khusus` | **kustom.** `name` diambil dari `ManualPayrollExcelImporter::TUNJANGAN_KHUSUS_LABEL` by `companies.code` (`0001`/`0002` → "Tunjangan Dokter/Jabatan Khusus"), fallback "Tunjangan Khusus". Bukan `FEE_CLAIM` — fee_claim ikut memicu mesin `fee_items` + potong `PAJAK_JASA_DOKTER`; sheet ini tidak punya kolom PPh dokter → V = earning flat tanpa potongan. |
| N | Tunjangan Masa Kerja | `tunjangan_masa_kerja` | |
| Y | Jasa Pelayanan | `jasa_pelayanan` | |
| **AA / AD / AE** | 1 baris per jenis terisi | `jasa_unit` | Revisi 2026-09-01: 1 `payroll_item` per kolom yang terisi (>0), `name` = jenisnya (AA=Tambahan Jasa, AD=Ambulans & Rujukan Pasien, AE=Paket Infus). Di **slip** dikelompokkan: judul "Jasa Unit" lalu rincian menjorok (`– Tambahan Jasa … Rp`). Grouping ada di `pdf/payroll/payroll.blade.php` (berlaku juga buat slip hasil calculator yg `jasa_unit`-nya multi-baris). |
| AB | Lembur | `lembur` | |
| AC | Extra Shift | `extra_shift` | |

### Potongan (`type = deduction`)
| Excel | `PayrollItem.name` | `source_type` | Catatan |
|---|---|---|---|
| AG | BPJS Kesehatan | `bpjs_kesehatan` | |
| AH | BPJS Ketenagakerjaan | `bpjs_ketenagakerjaan` | |
| **AJ + AL** (dijumlah, 1 baris) | Keterlambatan | `keterlambatan` | HRIS 1 bucket telat, Excel 2 (telat biasa + telat istirahat/tidak absen). Gabung. |
| AM | Izin Meninggalkan Jam Kerja | `izin_terlambat_pulang_cepat` | |
| **AO** | Potongan Izin & Cuti | `izin_cuti_gabung` | **kustom.** HRIS pisah `IZIN_SEHARIAN` + `CUTI`, Excel 1 angka gabung, tak bisa dipecah tanpa detail. |
| AP | Koperasi | `koperasi` | |
| AQ | Bon | `bon` | |
| **AX** (kalau > 0) | Potongan Hari Kerja Efektif & Cuti Melahirkan | `potongan_khusus` | **kustom.** Cuma dibuat kalau AX terisi. **Juga** ditulis ke `payroll_manual_lines` (component `potongan_khusus`) — sumber kebenaran potongan manual per orang, senada Koperasi/Bon. Re-import menghapus baris `potongan_khusus` lama dulu. |

### Jumlah kejadian → `quantity` + `note`
Bukan diabaikan lagi (revisi 2026-09-01): disalin ke `quantity` + `note` baris terkait, tampil di slip "Nx/Bulan".
- **AI + AK** (jumlah telat + telat istirahat/tidak absen) → `quantity` baris `keterlambatan`, note `"Nx/Bulan"`.
- **AN** (JMLH CUTI/IZIN/SAKIT) → `quantity` baris `izin_cuti_gabung`, note `"Nx"`. `izin_cuti_gabung` ditambahkan ke `PayrollService::componentRows()` `$notedComponents` supaya note-nya ikut tampil.

### Diabaikan
- Subtotal (dihitung ulang importer): X, Z, AF, AR, AS
- AW (counter), AZ (catatan bebas)
- AY (Jumlah Potongan — subtotal, importer hitung sendiri)
- Tidak ada di sheet: `TUNJANGAN_TRANSPORTASI`, `FEE_CLAIM`, `PAJAK_JASA_DOKTER`

### Cara buat tiap item
```php
PayrollItem::create([
  'payroll_id'      => $payroll->id,
  'name'            => <label>,
  'type'            => 'earning' | 'deduction',
  'quantity'        => 1,                 // atau jumlah kejadian utk keterlambatan/izin_cuti_gabung
  'amount'          => <nominal Excel>,   // clamp ke 0 kalau minus
  'source_type'     => <tag>,
  'edited_manually' => true,              // tanda: bukan hasil calculator
]);
```
> **Komponen tetap EARNINGS/DEDUCTIONS SELALU dibuat walau Rp0** (revisi 2026-09-01) — supaya
> slip lengkap, semua baris konsisten muncul. Yang **hanya dibuat kalau terisi**: baris dinamis
> — Jasa Unit per jenis (AA/AD/AE) & Potongan Khusus (AX). Di slip nominal Rp0 tampil "Rp" polos
> (konvensi `pdf/payroll/payroll.blade.php`).
- `Payroll.gross_salary` = Σ earning, `total_deductions` = Σ deduction, `net_salary` = selisih.
- Bandingkan dgn kol AF/AR/AS Excel → selisih = warning (default: pakai computed, Excel pembanding — lihat §7).

---

## 4. Infra HRIS yang di-reuse (sudah dicek)

- **`PayrollService::download()` / `encryptedSlip()`** → `Pdf::loadView('pdf.payroll.payroll', ['payroll'=>..., 'rows'=>componentRows(...)])`. Blade 404 baris. `rows['earnings']`/`['deductions']` = `{label, amount, note}` dari `$payroll->items`.
- **Password PDF** (`slipPassword`) = 2 digit `profile.date_of_birth` + 3 digit akhir `nip`. Null kalau salah satu kosong → `SendPayrollSlipJob` return diam-diam. **Preview importer harus flag baris tanpa DOB.**
- **`SendPayrollSlipJob`**: queued, `RateLimited('payroll-slip-email')` (throttle SMTP Gmail), `tries=3`, update `slip_sent_at`/`slip_send_failed_at`/`slip_send_error`, hanya jalan kalau `payroll.is_paid`.
- **`PayrollSlipMail`**: subject "Slip Gaji Bulan {F Y}", attachment PDF, body `emails.payroll-slip`. Sender name/posisi dari `payrollPeriod.signer` ?? `generatedBy`, fallback "NOOR AFINI, SE / Accounting".
- **`Payroll` & `PayrollItem` tidak punya observer / `booted()` custom** (`app/Observers/` kosong) → insert langsung aman.
- Nominal (`gross_salary`/`total_deductions`/`net_salary`/`amount`/`unit_amount`) di-cast `PayrollEncrypted` → tulis float biasa.
- **PayrollPeriod RSU Syifa Medika Banjarbaru Agustus 2026 SUDAH ADA** (`2026-08-01..2026-08-31`, `payment_date` kosong, **0 payroll**) → importer reuse, isi `payment_date` dari kol D.
- Slip blade "INFO" block baca: `user->name`, `user->activeJobPosition->jobPosition->name`, `user->nip`, masa kerja dari `user->profile->joining_date`, `user->profile->employeeType->name`, `user->profile->kelas->name`. → semua yang di-snapshot (§1.6) perlu fallback di blade.

---

## 5. Temuan kualitas data (Excel gaji vs export "Karyawan" production)

Bandingkan `knowledge/gaji-agustus-banjarbaru.xlsx` (E NIP, B Nama, I Jabatan, K Status, P Kelas)
vs export halaman Karyawan production (B NIP, C Nama, D Jabatan, E Pangkat, F Kelas, G Tipe).

**Data HRIS terus diperbaiki HR selama sesi analisis (31 Agu – 1 Sep 2026).** Beberapa export
diambil berturut-turut; angka di bawah adalah **kondisi terakhir** (`data-karyawan-20260901-074322.xlsx`).
Match by NIP (exact → zero-pad 11) + fuzzy nama untuk NIP typo: **457 direct + 20 by-nama = 477
dari 480 baris non-Mitra**.

### 5.1 Nama / NIP

- **3 NIP SALAH ORANG — SUDAH DIBETULKAN HR:** `01122025801/802/803` (Wafiq ↔ Ilham ↔ M. Puja
  ↔ Syaima Nazmi tergeser). Per export 1 Sep sudah cocok. Ini yang paling kritis; pastikan tetap
  benar sebelum import.
- **8 nama beda tersisa — semua cosmetic** (gelar/ejaan: "Puga MARS" vs "M.A.R.S", "Nor Laila SKM"
  vs "S.K.M", "Ria Oktrianti" vs "Oktarianti", "Muhammad Rifki" vs "M. Rifki", dll). Orang sama, aman.

### 5.2 NIP Excel tak ketemu di HRIS — 23 (turun dari 28)

- **A. Kurang angka depan → zero-pad, sudah ke-match (4):** Laila Rahmi `1022019194`→`01022019194`,
  Ria Maulida `1122017093`→`01122017093`, Aisha Mauldia `2032026873`→`02032026873`,
  Rifa Normaulida `7032026878`→`07032026878`.
- **B. NIP salah KETIK di Excel — orang & data ADA di HRIS (20 baris, belum dibetulkan di Excel):**
  pola dominan digit bulan `0202…` seharusnya `0206…`.
  | Excel NIP | → HRIS NIP | Nama |
  |---|---|---|
  | 02022023502 | 02062023502 | Rachmawati Eka Putri Kesuma |
  | 02022023503 | 02062023503 | M. Dicky Setiawan |
  | 02022023504 | 02062023504 | Robiatul Adawiyah |
  | 02022023505 | 02062023505 | Aprilia Rizky |
  | 02022023506 | 02062023506 | Firda Rosa Meliyani |
  | 02022023507 | 02062023507 | Ummi Nurul Risqi Wahyuny |
  | 02022023508 | 02062023508 | Neka Oktari Norcahya |
  | 02022023509 | 02062023509 | Fransiska Agustin |
  | 02022023498 | 02062023498 | Husin |
  | 21102024679 | 21102024680 | Novie Vediena |
  | 01102024672 | 01092024672 | Firda Wahyuni |
  | 01102024674 | 01092024674 | Suci Rahayu |
  | 10042023484 | 01042023484 | Senna Virgandisi |
  | 02062025741 | 02052025741 | Muhammad Septian Hanafi |
  | 15082023537 | 22082023537 | Muhammad Solihin |
  | 08122025806 | 08122025804 | Nurani Indah Fitriana |
  | 27012022415 | 07022022421 | Firliana Jimmy Sari |
  | 27012022416 | 29012022418 | Ghina Mardiyah |
  | 02032022068 | 01082022452 | dr. Bagus Putra Kurniawan |
  | DU-09032020106 | 15032024616 | dr. Yosevine *(NIP HRIS beda tahun — verifikasi apa ganti NIP)* |

  Setelah NIP dibetulkan di Excel, kelas mereka **sudah cocok semua** (475 OK), jabatan cuma
  dr. Yosevine yang masih beda (lihat §5.3).
- **C. Genuine TIDAK ADA di HRIS (3) — user konfirmasi belum ditambahkan:** Melinda Sari,
  S.Tr.Keb.,Bdn `13022024603` (Bidan Ponek, Kontrak); Shella Aurrelia, S.Kep., Ns `27082026056`
  (Orientasi); Hadriannor `15082025055` (Cleaning Service, Orientasi). → buat `User` dulu atau
  skip periode ini.

### 5.3 Jabatan — kondisi 1 Sep 2026 (export 074322)

| Kategori | Jumlah | Catatan |
|---|---|---|
| Identik | 430 | naik dari 282 (31 Agu) — HR rapiin banyak |
| Cosmetic (nama pendek vs panjang, typo, peran sama) | ~25 | mis. HRIS "LT. 4 Gedung Marwah" vs Excel "LT. 4 Faviliun Firdaus Gedung Marwah"; "Ranap" vs "Ruangan Unit Perawatan"; "Staf Promkes" vs "Promkes" |
| **Konflik nyata** | **6** | ❌ lihat bawah |
| **HRIS "Perawat Orientasi", Excel sudah posted** | **17** | ⚠️ rekrut 2026, HR belum promote |

**Sudah kelar antar-export:** skema gedung (Excel "Shofa/Marwah/Muzdalifah" — HR migrasi HRIS
pakai nama gedung, bukan "Gedung A/B/C" lagi; **LT. 4 Faviliun Firdaus = Gedung Marwah**,
dikonfirmasi user); Perawat ICU vs ranap (3 orang: Dwi Rahmiati / Heldawati / M. Hari Rahman —
sudah dibetulkan HRIS ke ranap); Munif ↔ Tamara "Staf Klaim Asuransi"/"Payroll Jasa Dokter BPJS"
ketuker (fixed); Arif Rahman Hakim.

**Konflik nyata sisa (6):**
| NIP | Nama | Excel | HRIS | Tindakan |
|---|---|---|---|---|
| 09032020106 | dr. Yosevine | Kepala Instalasi IGD & koordinator dr umum | Kepala Ruangan IGD | putusan HR |
| 01092022457 | dr. Fahrizal Setiawan | Kepala Tim JKN | PJ tim Casemix | putusan HR |
| 15112017076 | Rudy Alpianoor | Kepala Ruangan IGD | Perawat IGD | putusan HR |
| — | Dara Faradilla Kholipah | Case Manager Poliklinik Eksekutif | (kosong) | isi HRIS |
| 1022019194 | Laila Rahmi Jamilah | Laborat**o**tium (typo Excel) | Laboratorium | cosmetic |
| 05102016006 | Ummu Habibah | Kasi Perawat | Kasi Keperawatan | cosmetic |

**HRIS "Perawat Orientasi", Excel sudah kasih posting (17)** — rekrut 2026, HR belum ubah status:
`02022026828` Siti Della Harizka · `06052026031` Verawati · `29102025790` Refika Sahara Dewi ·
`02022026834` M. Ferdy Baihaqi · `02022026832` Rahmat Rizki Ramdhani · `11032026884` Isti Paiyah ·
`12032026887` Titia Nur Safitri · `01042026995` M. Raehan Aulia · `02022026833` M. Redyansyah ·
`02042026006` Nurul Mawaddah · `02022026831` Wafa Kaila · `11032026885` Ahmad Jailani ·
`02022026830` Ahmad Erwin Wahyudi · `11032026886` Nurkhalisa Putri Azzahra · `02022026829` Arief Rahman ·
`01042026994` Putri Kharisma Ningtias · `15052026032` Rifka Adelina.

### 5.4 Kelas — SELESAI ✅

| Kategori | 31 Agu | 1 Sep (074322) |
|---|---|---|
| Identik | 302 | **475** |
| Excel = HRIS + 1 (offset sistematis) | ~120 | **0** |
| Outlier | 10 | **0** |
| Salah satu kosong | 24 | 2 |

Offset +1 sistematis (XLS N = HRIS N−1) **sudah dibetulkan** — via `scripts/sync_kelas.php`
dijalankan di prod atau koreksi HR. Termasuk 20 baris NIP-typo (§5.2.B). Sisa 2 baris `Kelas`
kosong. **Kelas tidak perlu tindakan lagi.**

### 5.5 Tipe karyawan
Praktis sama: "Karyawan Tetap" (Excel) ≡ "Tetap" (HRIS) — format label. Konflik lama Haniah
(Excel "Orientasi" vs HRIS "Mitra") — cek apakah sudah dibetulkan di export terbaru.

### 5.6 Ringkasan status rekonsiliasi (per 2026-09-01)

| Aspek | Status | Sisa pekerjaan |
|---|---|---|
| **Kelas** | ✅ selesai | 2 baris kelas kosong |
| **Nama / NIP salah orang** | ✅ selesai | pastikan tetap benar |
| **Jabatan — gedung/ICU/swap** | ✅ selesai | — |
| **Jabatan — konflik nyata** | 🟡 6 | 3 putusan HR (Yosevine/Fahrizal/Rudy), 1 isi HRIS (Dara), 2 cosmetic |
| **Jabatan — HRIS masih "Orientasi"** | 🟡 17 | HR promote rekrut 2026 (opsional — slip pakai snapshot Excel) |
| **NIP typo di Excel** | 🔴 20 | betulkan NIP di file Excel (`0202…`→`0206…` dll), atau importer fuzzy-match by nama |
| **Karyawan belum ada di HRIS** | 🔴 3 | buat `User` (Melinda Sari, Shella Aurrelia, Hadriannor) atau skip periode ini |

Karena slip **snapshot jabatan/kelas/status dari Excel** ke `payrolls.import_meta` (§1.6), sisa
konflik jabatan **tidak memblokir import** — cuma soal kerapian master HRIS.

---

## 6. Script ops (SUDAH DIBUAT, di `scripts/`)

### `scripts/sync_kelas.php` — samakan `user_profiles.kelas_id` ke kolom Kelas Excel
```bash
php scripts/sync_kelas.php "knowledge/gaji-agustus-banjarbaru.xlsx"           # DRY RUN
php scripts/sync_kelas.php "knowledge/gaji-agustus-banjarbaru.xlsx" --apply   # tulis
#   --company=<ULID>   default: cari "RSU Syifa Medika Banjarbaru"
#   env HRIS_ROOT=<path repo>  kalau file tak di root repo & tak di scripts/
```
- Match NIP exact → zero-pad 11. Update `user_profiles.kelas_id` ke `m_kelas` (company itu)
  `level` = angka kol P. `--apply` dibungkus 1 transaksi, cuma sentuh baris yang level-nya beda.
  Tidak menyentuh `pangkat`.
- **Sudah tidak perlu dijalankan lagi** — kelas sudah selesai (§5.4). Simpan buat RS berikutnya.

### `scripts/set_jabatan.php` — set jabatan (generic) untuk daftar NIP
```bash
php scripts/set_jabatan.php "Petugas Pantry" 13052025731                # DRY RUN
php scripts/set_jabatan.php "Cleaning Service" 111 222 333 --apply
php scripts/set_jabatan.php "Petugas Pantry" --file=nips.txt --apply
#   --company=<ULID>  --position=<ULID>  (kalau nama tak unik / belum ada)
```
- Pola `EditKaryawan`: update `user_profiles.job_position_id` + swap `user_job_positions`
  (hapus jabatan lain, baris tujuan `status = 'active'`). Match NIP exact → zero-pad 11.
- Resolusi job position: nama persis (case-insensitive) dulu, lalu `LIKE`, error kalau ambigu.
- Sudah dipakai: 40 NIP → "Cleaning Service", 1 NIP (`13052025731` Muhammad Anshori) → "Petugas Pantry".

### `scripts/set_jabatan_cleaning_service.php` — varian hardcode 40 NIP cleaning
Redundan dengan `set_jabatan.php` (bisa `set_jabatan.php "Cleaning Service" --file=...`) — dibiarkan
karena daftar 40 NIP sudah di-review.

**PERINGATAN semua script:** jalankan DRY RUN di PROD dulu, review output, baru `--apply`.

---

## 7. Keputusan yang masih perlu (default sementara di kurung)

1. **NIP Excel tak ketemu `User`** → *(default: skip + laporkan di preview, import lanjut untuk yang cocok)*. Alternatif: blokir sampai bersih.
2. **Re-import saat period sudah ada `Payroll` (belum `is_paid`)** → *(default: hapus `Payroll` + `PayrollItem` company+period itu yang `is_paid=false`, buat ulang; yang `is_paid=true` → blokir, minta hapus manual)*.
3. **Selisih net computed vs kol AS Excel** → *(default: warning saja, simpan nilai computed dari Σ item)*. Alternatif: pakai AS apa adanya.
4. **`payment_date`** period → dari kol D Excel. Kalau period sudah punya `payment_date` beda → *(default: jangan timpa, warning)*.
5. **Import per bulan**: 1 file 1 sheet 1 period. Multi-sheet / multi-period tidak didukung v1.
6. **Dokter (Mitra)** — skip v1. Kapan & bagaimana masuk = keputusan terpisah.
7. **17 rekrut 2026 masih "Perawat Orientasi" di HRIS** (§5.3) — biarkan (slip pakai snapshot Excel) atau HR promote dulu?

---

## 8. Rencana implementasi (Fase — belum dikerjakan)

### Fase 0 — Bersih-bersih data (USER, sebelum import) — status per 2026-09-01
1. ✅ 3 NIP salah orang (`01122025801/802/803`) — sudah dibetulkan HR.
2. ✅ Kelas offset +1 (~120 baris) + outlier — sudah selesai (§5.4).
3. ✅ Skema gedung / Perawat ICU / swap jabatan — sudah dibereskan HR.
4. 🔴 **Betulkan 20 NIP salah ketik di Excel** (§5.2.B, tabel) — pola `0202…`→`0206…` dll. Atau importer fuzzy-match by nama.
5. 🔴 **3 karyawan belum ada di HRIS** (Melinda Sari, Shella Aurrelia, Hadriannor) — buat `User` atau skip periode ini.
6. 🟡 4 konflik jabatan butuh putusan HR (dr. Yosevine, dr. Fahrizal, Rudy Alpianoor, Dara Faradilla) — tidak memblokir (snapshot Excel).
7. 🔴 **Isi `email_pribadi`** karyawan yang kosong (cek di prod — Zeihan Alisia Fajrina, Ummu Habibah, + kemungkinan lebih).
8. 🟡 Haniah — tetapkan Orientasi atau Mitra (cek export terbaru apakah sudah beres).

### Fase 1 — Importer
`app/Services/Payroll/ManualPayrollExcelImporter.php`:
- `preview(string $path, string $companyId): array` — parse (PhpSpreadsheet, `setReadDataOnly` + `IReadFilter` kol A..AY row ≤ "JUMLAH"), lacak `unit`, skip section-header + trailer + `Mitra`. Per baris: normalisasi NIP → match `User`; susun earnings/deductions; hitung gross/deduction/net; snapshot jabatan/status/kelas/tgl-masuk. Return `rows` (matched/unmatched), `warnings` (NIP tak ketemu, tanpa `email_pribadi`, tanpa `date_of_birth`, net ≠ Excel, jabatan/kelas beda HRIS), `summary`.
- `import(string $path, PayrollPeriod $period, User $actor): array` — transaksi: (re)buat `Payroll` + `PayrollItem`. `is_paid=false`, `generated_by=actor`, `generated_at=now()`. Isi `period->payment_date` dari kol D kalau kosong. Isi `payroll->import_meta`.
- Migration: `payrolls` + kolom `import_meta` json nullable (atau kolom terpisah `job_title_snapshot` dst — pilih json biar reusable RS lain).

### Fase 2 — Halaman trigger (Livewire, portal Keuangan)
`app/Livewire/Keuangan/ManualPayrollImportPage.php` + blade, route `/keuangan/import-payroll-manual`.
- Upload `.xlsx` → preview (tabel + ringkasan + `warnings`) → tombol **Import** (disabled kalau ada blocker).
- Gate: `CustomPermissionEnum::IMPORT_PAYROLL_MANUAL` (permission khusus, dipisah dari
  `GENERATE_PAYROLL` — petugas input Excel tidak otomatis bisa Generate/Regenerate). Aksi
  Tandai Dibayar / Kirim Slip di halaman tetap `Update:Payroll`. Ada di grup `SISTEM_KEUANGAN`
  (`RoleForm::portalGroups`), muncul di tree `/hak-akses` sebagai "Import Payroll Manual (dari Excel)".
- `config/portal-pages.php` — entry route baru.

### Fase 3 — Kirim slip
Tidak ada kode baru. Setelah import → `PayrollDetailPage` period itu → alur existing "Tandai Dibayar" (`is_paid=true`) → "Kirim Slip" (`SendPayrollSlipJob`). Kalau belum granular per-period, tambah aksi tipis di Fase 2.
Update slip blade `pdf.payroll.payroll` — fallback ke `import_meta` untuk jabatan/status/kelas/masa kerja.

### Fase 4 — Test
`tests/Feature/Payroll/ManualPayrollExcelImportTest.php`: fixture xlsx kecil (section header + Mitra + trailer + 3-4 data); preview skip benar, NIP zero-pad, warning; import buat `Payroll`+`PayrollItem`, gross/net benar, T+W & AJ+AL kejumlah, Rp0 tak dibuat, `edited_manually=true`, `import_meta` terisi; re-import sesuai §7.2.

---

## 9. Yang SENGAJA tidak dibangun (v1)

- Tidak ada tabel baru (kecuali kolom `import_meta` di `payrolls`), blade slip baru, Mailable/Job baru, halaman slip baru.
- Tidak menjalankan calculator apa pun — murni transkripsi angka Excel.
- Tidak ada prorate / PPh / BPJS dihitung ulang — final di Excel.
- Tidak menyentuh data email/kontak karyawan (user manual).
- Tidak multi-sheet / multi-period per file.
- Dokter/Mitra tidak diimport.

---

## 10. Reusability untuk RS lain

- Kontrak sheet: header kolom S..AS + A/B/E/I/K/L/P/AT/AU/AV **harus sama persis** (posisi & nama). Section-header (E kosong, B unit) & trailer ("JUMLAH") pola sama.
- Mapping §3 berlaku umum. Kalau RS lain punya komponen beda (mis. ada `TUNJANGAN_TRANSPORTASI`, atau ada kolom PPh dokter) → extend tabel mapping, jangan hardcode 1 RS.
- `sync_kelas.php` generic — `--company` + resolusi `m_kelas` per company sudah company-scoped.
- Snapshot identitas (`import_meta`) json → RS lain bisa punya field ekstra tanpa migration.
