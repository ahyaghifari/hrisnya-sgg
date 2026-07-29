# Jasa Pelayanan: Rincian Omset per Kategori + Total JE Live, Ganti Data Manual Lama (2026-07-30)

Sesi ini mengisi gap yang ditemukan saat audit payroll: `JasaPelayananCalculator` (rasio JE × pool Jaspel, lihat `analisis-payroll-4-pembacaan-excel-skema-aktual.md` §3.5) logikanya sudah dikonfirmasi benar sejak lama, tapi **2 input yang jadi pembagi/pool-nya sama sekali tidak punya UI** — cuma bisa diisi lewat `tinker`. Sesi ini membangun UI-nya sekaligus mengganti bentuk datanya berdasarkan koreksi rumus dari user.

## 1. Koreksi rumus: "Total Pool Jaspel" = Omset Rumah Sakit, bukan angka arbitrer

User mengoreksi rumus jadi `(OMSET RS / Total JE) × JE_karyawan` — secara matematis identik dengan `(JE_karyawan / Total JE) × OMSET` yang sudah ada di kode, cuma istilahnya diluruskan: yang disebut "Total Pool Jaspel" di kode/dokumen lama itu **omset rumah sakit** bulan itu (dari laporan keuangan), bukan angka pool yang ditentukan finance sendiri.

## 2. Keputusan desain (hasil diskusi, bukan tebakan)

- **Omset dipecah per kategori** (Rawat Inap, Rawat Jalan, dst) — bukan 1 angka polos. Kategori dikelola per company di halaman **Mapping Komponen Gaji** (tile baru "Jasa Pelayanan", CRUD nama kategori saja — `OmsetCategory`/`omset_categories`).
- **Omset menempel ke Periode Gaji** (bukan "tanggal bebas" yang dipilih manual saat generate) — konsisten dengan `service_fee_pools` lama yang juga per-periode, dan menghindari bikin konsep periode kedua yang paralel (pernah jadi sumber bug di sistem ini, lihat Q12 `analisis-payroll-5` soal periode izin 20→21 vs periode payroll kalender). Cascade delete kalau periode dihapus.
- **`company_id` TIDAK disimpan** di tabel rincian omset — sudah didapat lewat `payroll_period_id -> payroll_periods.company_id`, disengaja tidak diduplikasi (beda dari `service_fee_pools` lama yang kebetulan simpan dua-duanya).
- **Total JE Perusahaan dihitung LIVE** — `SUM(job_evaluation_score)` semua jabatan **status APPROVED** company itu, bukan input manual terpisah. `job_evaluation_companies` (tabel lama) jadi tidak dipakai lagi.
- **Tidak ada hard block** di Generate Payroll kalau omset belum diisi — konsisten dengan filosofi Koperasi/Bon/Insentif-import di sistem ini ("isi kapan saja, efeknya baru masuk pas Generate/Regenerate berikutnya"; guard block serupa untuk Insentif-import malah sengaja dihapus di Fase 4). `JasaPelayananCalculator` sudah aman skip diam-diam kalau omset/JE kosong. Cukup **indikator peringatan** di halaman Periode Gaji.

## 3. Tabel baru

```
omset_categories (sudah ada sebelum sesi ini)
  id, company_id, name, timestamps

omset_details (baru)
  id, payroll_period_id (FK cascade), omset_category_id (FK cascade), amount, timestamps
  unique(payroll_period_id, omset_category_id)
```

`service_fee_pools` dan `job_evaluation_companies` (tabel lama) **dibiarkan ada** (tidak di-drop) tapi sudah tidak dibaca oleh calculator manapun — bisa dibersihkan belakangan kalau sudah yakin tidak ada data historis yang perlu diselamatkan.

## 4. Kode yang berubah

- **`JasaPelayananCalculator`** (`app/Services/Payroll/Calculators/JasaPelayananCalculator.php`) — Total JE dari `JobPositions::where('company_id', ...)->where('status', 'approved')->sum('job_evaluation_score')`, Total Omset dari `OmsetDetail::where('payroll_period_id', ...)->sum('amount')`. Rumus rasio & pembulatan tidak berubah.
- **`MappingKomponenGajiPage`** — tile "Jasa Pelayanan" baru, cuma kelola daftar `OmsetCategory` (nama saja) per company.
- **Halaman baru `OmsetJaspelPage`** (`/keuangan/omset-jaspel`) — pilih company (cuma yang komponen Jasa Pelayanan aktif) + Periode Gaji, 2 bagian: **Rincian Omset** (kategori × nominal per periode, total live) dan **Job Evaluation per Jabatan** (tabel No/Nama Jabatan/JE Score seperti Excel, edit `job_positions.job_evaluation_score` langsung, Total JE live — tidak terikat periode, berlaku sampai diubah lagi).
- **`PayrollPeriodsPage`** — badge peringatan "Omset belum diisi" di baris periode (company Jasa Pelayanan aktif + `SUM(omset_details.amount)` periode itu masih 0), plus tombol pintasan ke halaman Omset & JE. Tidak memblokir tombol Generate/Regenerate.
- Menu nav (`nav.blade.php`) + `config/portal-pages.php` — entry baru "Omset & JE Jasa Pelayanan" di dropdown Penggajian.

## 5. Test baru

`tests/Feature/Payroll/JasaPelayananCalculatorTest.php` — 2 kasus (hitung rasio JE×Omset dari data `omset_details`+`job_positions` langsung, dan skip diam-diam kalau omset periode itu masih kosong).

## 6. Yang belum dikerjakan

- Data riil (kategori omset + rincian per periode + JE per jabatan) belum diisi untuk company hospital manapun — semua tabel baru masih infrastruktur kosong, sama seperti kondisi `position_allowances` dkk waktu pertama dibangun (Fase 3).
- `service_fee_pools`/`job_evaluation_companies` belum di-drop — dibiarkan dulu sampai yakin tidak ada data historis yang masih relevan.
- Verifikasi manual di browser (klik langsung end-to-end: isi kategori → isi omset → isi JE → generate payroll → cek slip) belum dilakukan — baru diverifikasi lewat test terprogram.

## 7. Referensi
- `analisis-payroll-4-pembacaan-excel-skema-aktual.md` §3.5 — konfirmasi rumus asli.
- `analisis-payroll-5-klasifikasi-enum-hardcode-config.md` — Q12, alasan kenapa "periode kedua paralel" dihindari.
- `payroll-fase-4-koperasi-insentif-manual-line-tracking-slip-gaji.md` §1 — preseden "regenerate aman tanpa hard block" yang jadi alasan tidak bikin gate di sini.
