# Import Payroll Manual dari Excel → struktur `Payroll` HRIS existing

> Dibuat 2026-08-31. Status: **ANALISIS SELESAI, BELUM CODING.** Lanjut besok.
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
| 6 | **Field identitas slip di-SNAPSHOT dari Excel** ke `payrolls` (kolom baru, mis. `import_meta` json), TIDAK ambil dari relasi HRIS. Alasan: data jabatan HRIS beda 33%, kelas beda 28% (lihat §5). Slip blade fallback: `$payroll->import_meta['jabatan'] ?? $payroll->user->activeJobPosition?->jobPosition?->name ?? '-'`. Field yang di-snapshot: jabatan, status karyawan, kelas, tgl masuk/masa kerja. |

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
| **V** | Tunjangan Dokter/Jabatan Khusus | `tunjangan_dokter` | **kustom.** Bukan `FEE_CLAIM` — kalau ke fee_claim ikut kebawa mesin `fee_items` + `PAJAK_JASA_DOKTER`. Sheet ini **tidak ada kolom PPh jasa dokter sama sekali** (sudah dicek seluruh kolom) → V = earning flat, tanpa potongan. |
| N | Tunjangan Masa Kerja | `tunjangan_masa_kerja` | |
| Y | Jasa Pelayanan | `jasa_pelayanan` | |
| **AA / AD / AE** (3 baris terpisah) | Jasa Unit — Tambahan Jasa / Jasa Unit — Jasa Supir Ambulance & Perawat / Jasa Unit — Jasa Paket Infus | `jasa_unit` | Konfirmasi user: ketiganya = Jasa Unit. Pola sama `JasaUnitCalculator` (tiap baris nama sendiri). |
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

### Diabaikan
- Hitungan: AI, AK, AN
- Subtotal (dihitung ulang importer): X, Z, AF, AR, AS
- AW (counter), AZ (catatan)
- Tidak ada di sheet: `TUNJANGAN_TRANSPORTASI`, `FEE_CLAIM`, `PAJAK_JASA_DOKTER`

### Cara buat tiap item
```php
PayrollItem::create([
  'payroll_id'      => $payroll->id,
  'name'            => <label>,
  'type'            => 'earning' | 'deduction',
  'quantity'        => 1,
  'amount'          => <nominal Excel>,   // 0 → baris TIDAK dibuat
  'source_type'     => <tag>,
  'edited_manually' => true,              // tanda: bukan hasil calculator
]);
```
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

## 5. Temuan kualitas data (bandingkan Excel vs export `data-karyawan-20260831-213244.xlsx` — export dari halaman Karyawan production)

Export HRIS kolom: A No, B NIP, C Nama, D Jabatan, E Pangkat, F Kelas, G Tipe Karyawan, H Tgl Bergabung. 485 baris.

**Match by NIP (+ zero-pad): 452–456 dari 471 baris non-Mitra.**

### 5.1 Nama / NIP — 3 SALAH ORANG (KRITIS, betulkan sebelum import)
| NIP | Payroll | HRIS |
|---|---|---|
| 01122025801 | Wafiq Nur Azizah | **Ilham** |
| 01122025802 | Ilham | **Muhammad Puja Oktapiannoor** |
| 01122025803 | Muhammad Puja Oktapiannoor | **Syaima Nazmi** |

3 NIP berurutan tergeser. Match by NIP + nama HRIS → gaji nyasar. Ada juga NIP `01122025804` (Syaima Nazmi di Excel) yang nyambung ke geseran ini.

6 lain cuma beda ejaan/gelar (Nor Laila SKM vs S.K.M, Rafli Rahman vs Rakhman, Ria Oktrianti vs Oktarianti, "Muhammad Rifki" vs "M. Rifki") — orang sama, aman.

### 5.2 NIP tak ada di HRIS (28, dari perbandingan vs export; lokal DB ~44)
- **A. Kurang angka depan → zero-pad (4):** baris 145 Laila Rahmi Jamilah `1022019194`→`01022019194`, 147 Ria Maulida `1122017093`→`01122017093`, 159 Aisha Mauldia `2032026873`→`02032026873`, 160 Rifa Normaulida `7032026878`→`07032026878`.
- **B. NIP salah ketik di Excel, orang ADA di HRIS (17):** pola dominan digit bulan `0202…` seharusnya `0206…`. Contoh: 191 M. Dicky `02022023503`→`02062023503`; 272/288/289/342/352/409/469 sama pola; 351 Novie `…679`→`…680`; 395 Suci `01102024674`→`01092024674`; 418 Senna `10042023484`→`01042023484`; 450 M. Septian `02062025741`→`02052025741`; 517 M. Solihin `15082023537`→`22082023537`; 211 Firda Wahyuni `01102024672`→`01092024672`; 193 Nurani `08122025806`→`08122025804`; 483 M. Halimi `22012024594` (HRIS `220122024594`, 12 digit — typo di HRIS); 247 Syaima Nazmi (lihat §5.1).
- **C. Perlu verifikasi manual — NIP & konteks geser jauh (4):** 10 dr. Yosevine `DU-09032020106` vs nama-match HRIS `15032024616` (2020↔2024); 28 dr. Bagus Putra `02032022068` vs `01082022452`; 98 Firliana Jimmy `27012022415` vs `07022022421`; 207 Ghina Mardiyah `27012022416` vs `29012022418`.
- **D. Genuine TIDAK ADA di HRIS (3):** baris 242 Melinda Sari, S.Tr.Keb.,Bdn (Perawat IGD / Bidan Ponek, Kontrak); 368 Shella Aurrelia, S.Kep., Ns (Perawat Orientasi); 501 Hadriannor (Kesling / Cleaning Service, Orientasi). → buat `User` dulu atau skip periode ini.

### 5.3 Jabatan — 456 cocok
| Kategori | Jumlah | Aman? |
|---|---|---|
| Identik | 282 (62%) | ✅ |
| Cosmetic (typo/singkatan, peran sama) | 8 | ✅ |
| Skema gedung: Excel "Shofa/Marwah/Muzdalifah" ↔ HRIS "Gedung A/B/C" | 78 | ⚠️ sistematis, auto-map |
| HRIS "…Orientasi", Excel sudah posted | 18 | ⚠️ Excel lebih update |
| **Konflik nyata** | **~15** | ❌ cek HR |
| **HRIS kosong ("-")** | **40** | ❌ data HRIS belum diisi |

**Sinkron aman = 290 (64%).** → mendukung keputusan §1.6 (snapshot jabatan dari Excel).

Konflik nyata: Munif ↔ Tamara ("Staf Klaim Asuransi" & "Payroll Jasa Dokter BPJS" **ketuker**);
Wafiq/M.Puja (efek §5.1); Rudy Alpianoor (Kepala Ruangan IGD vs Perawat IGD); Dwi Rahmiati /
Heldawati / M. Hari Rahman (Perawat Ranap vs Perawat ICU); Arif Rahman Hakim (Orientasi vs
Poliklinik Reguler); Ahmad Sayuti (Cleaning Service vs Pekarya CSSD); Desy / Maudatun
(Pendaftaran Rawat Jalan vs Rawat Inap); dr. Fahrizal (Kepala Tim JKN vs PJ Casemix).

### 5.4 Kelas — 456 cocok
| Kategori | Jumlah |
|---|---|
| Identik | 302–303 (66%) |
| **Excel = HRIS + 1** (offset sistematis) | **120–131 (26–29%)** |
| Beda selain +1 (outlier) | 10 |
| Salah satu kosong | 24 |

**120+ offset +1**: XLS 3→HRIS 2, XLS 2→HRIS 1, XLS 5→HRIS 4. Terlalu rapi untuk kebetulan.
Satu sistem geser 1 tingkat. **Keputusan user 2026-08-31: Excel = kanon**, samakan HRIS ke Excel.
→ script `sync_kelas.php` sudah dibuat (lihat §6).

Outlier 10 (rekrut 2026, HRIS kasih kelas tinggi janggal — kemungkinan salah input akun):
Nurul Mawaddah XLS 2 / HRIS 9; M. Raehan Aulia 2/8; Putri Kharisma 2/7; Titia Nur Safitri 2/6;
Nurkhalisa 2/5; Rusiani / apt. Rizky Aulia 3/4; Ahmad Jailani 2/4; Isti Paiyah / Ahmad Sayuti 2/3.
→ script ikut menyamakan ke Excel (turun ke 2/3), TAPI cek dulu mana yang memang salah input HRIS.

### 5.5 Tipe karyawan
Praktis sama: "Karyawan Tetap" (Excel) ≡ "Tetap" (HRIS) — cuma format label. 1 konflik: **Haniah**
Excel "Orientasi" vs HRIS "Mitra".

---

## 6. Script sync kelas (SUDAH DIBUAT)

`sync_kelas.php` (sekarang di scratchpad — **pindahkan ke root repo HRIS untuk dipakai**).

```bash
php sync_kelas.php "knowledge/gaji-agustus-banjarbaru.xlsx"            # DRY RUN
php sync_kelas.php "knowledge/gaji-agustus-banjarbaru.xlsx" --apply    # tulis
#   --company=<ULID>   override (default: cari "RSU Syifa Medika Banjarbaru")
#   env HRIS_ROOT=<path repo>  kalau file tidak di root
```
- Match NIP exact → zero-pad 11.
- Update `user_profiles.kelas_id` ke `m_kelas` (company itu) dgn `level` = angka kol P.
- `--apply` dibungkus 1 transaksi, cetak tiap perubahan, cuma sentuh baris yang level-nya beda.
- **Tidak menyentuh `pangkat`.**
- Dry run lokal: 131 akan diubah, 303 sudah sama, 19 Mitra skip, 2 kol P kosong (dr. Yosevine,
  dr. Fahrizal), 44 NIP tak ketemu (prod ~28), 0 level tanpa m_kelas.

**PERINGATAN:** script menganggap Excel benar. Kalau kelas Excel yang basi (HRIS sudah revisi),
ini malah mundur. Jalankan dry-run di PROD dulu, review, baru `--apply`.

---

## 7. Keputusan yang masih perlu (default sementara di kurung)

1. **NIP Excel tak ketemu `User`** → *(default: skip + laporkan di preview, import lanjut untuk yang cocok)*. Alternatif: blokir sampai bersih.
2. **Re-import saat period sudah ada `Payroll` (belum `is_paid`)** → *(default: hapus `Payroll` + `PayrollItem` company+period itu yang `is_paid=false`, buat ulang; yang `is_paid=true` → blokir, minta hapus manual)*.
3. **Selisih net computed vs kol AS Excel** → *(default: warning saja, simpan nilai computed dari Σ item)*. Alternatif: pakai AS apa adanya.
4. **`payment_date`** period → dari kol D Excel. Kalau period sudah punya `payment_date` beda → *(default: jangan timpa, warning)*.
5. **Kelas outlier §5.4** (10 rekrut 2026) — samakan ke Excel via script, atau perbaiki HRIS satu-satu dulu?
6. **Import per bulan**: 1 file 1 sheet 1 period. Multi-sheet / multi-period tidak didukung v1.
7. **Dokter (Mitra)** — skip v1. Kapan & bagaimana masuk = keputusan terpisah.

---

## 8. Rencana implementasi (Fase — belum dikerjakan)

### Fase 0 — Bersih-bersih data (USER, sebelum import)
1. Betulkan 3 NIP salah orang (`01122025801/802/803`) + Syaima Nazmi `804`.
2. Zero-pad 4 NIP kategori 5.2.A di Excel (atau importer auto zero-pad).
3. Betulkan 17 NIP salah ketik kategori 5.2.B (pola `0202`↔`0206`) di Excel.
4. Verifikasi 4 NIP kategori 5.2.C.
5. Putuskan 3 karyawan kategori 5.2.D: buat `User` atau skip.
6. Jalankan `sync_kelas.php --apply` di prod (setelah dry-run OK).
7. Isi `email_pribadi` karyawan yang kosong (min. 2: Zeihan Alisia Fajrina, Ummu Habibah — di prod bisa lebih).
8. Haniah: tetapkan Orientasi atau Mitra.

### Fase 1 — Importer
`app/Services/Payroll/ManualPayrollExcelImporter.php`:
- `preview(string $path, string $companyId): array` — parse (PhpSpreadsheet, `setReadDataOnly` + `IReadFilter` kol A..AY row ≤ "JUMLAH"), lacak `unit`, skip section-header + trailer + `Mitra`. Per baris: normalisasi NIP → match `User`; susun earnings/deductions; hitung gross/deduction/net; snapshot jabatan/status/kelas/tgl-masuk. Return `rows` (matched/unmatched), `warnings` (NIP tak ketemu, tanpa `email_pribadi`, tanpa `date_of_birth`, net ≠ Excel, jabatan/kelas beda HRIS), `summary`.
- `import(string $path, PayrollPeriod $period, User $actor): array` — transaksi: (re)buat `Payroll` + `PayrollItem`. `is_paid=false`, `generated_by=actor`, `generated_at=now()`. Isi `period->payment_date` dari kol D kalau kosong. Isi `payroll->import_meta`.
- Migration: `payrolls` + kolom `import_meta` json nullable (atau kolom terpisah `job_title_snapshot` dst — pilih json biar reusable RS lain).

### Fase 2 — Halaman trigger (Livewire, portal Keuangan)
`app/Livewire/Keuangan/ManualPayrollImportPage.php` + blade, route `/keuangan/import-payroll-manual`.
- Upload `.xlsx` → preview (tabel + ringkasan + `warnings`) → tombol **Import** (disabled kalau ada blocker).
- Gate: reuse `CustomPermissionEnum::GENERATE_PAYROLL`.
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
