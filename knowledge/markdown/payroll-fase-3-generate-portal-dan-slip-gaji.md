# Payroll Fase 3 — Generate Payroll End-to-End, Portal Payroll, dan Slip Gaji (2026-07-17)

Lanjutan dari Fase 0/1/2 (lihat `planning/2026-07-16-payroll-fase-0-*.md`, `planning/2026-07-17-payroll-fase-1-*.md`, `planning/2026-07-17-payroll-fase-2-*.md`). Fase 0-2 membangun arsitektur (Registry+Strategy+Config) dan mengeksekusi migrasi; sesi ini (Fase 3) yang pertama kali benar-benar **menjalankan Generate Payroll sampai tuntas** dan membangun **portal untuk melihat hasilnya** — sebelumnya cuma bisa diakses lewat `/admin` (Filament).

Dokumen ini ringkasan tingkat tinggi. Detail keputusan tiap fitur ada di planning doc masing-masing (link di §7).

## 1. Fitur baru yang dibangun

### 1.1 Insentif RSU — metode Import
- Setting `insentif_metode` (`master`/`import`) di Komponen Gaji — Holding tetap pakai tabel Insentif manual ("master"), RSU nanti bisa pindah ke "import" (upload Excel per-NIP, ditempel ke payroll yang SUDAH digenerate).
- Halaman baru `App\Livewire\Keuangan\ImportIncentivesPage` + `App\Imports\Payroll\IncentiveImport` — idempotent per NIP (re-upload = replace, bukan dobel).
- Guard: `PayrollService::periodHasImportedIncentives()` — regenerate payroll diblokir kalau periode itu sudah punya insentif hasil import (supaya tidak hilang diam-diam).
- **Rute & nav dicabut sementara** atas permintaan user ("dihapus dulu") — halamannya masih ada di kode, tapi cuma bisa diakses lewat tombol "Import" di halaman Payroll (bukan menu utama).

### 1.2 Halaman Mapping Komponen Gaji
`App\Livewire\Keuangan\MappingKomponenGajiPage` — 1 pintu buat isi 5 tabel config yang sebelumnya kosong tanpa UI (Gaji Pokok, Tunjangan Jabatan, Tunjangan Transportasi, Tunjangan Masa Kerja, BPJS) + reuse UI Filament yang sudah ada (Kelebihan Jam Kerja, Lembur) — pilih company → pilih komponen aktif → form spesifik sesuai struktur data komponen itu. Validasi input (angka negatif, bracket tumpang tindih Tunjangan Masa Kerja).

### 1.3 Lembur — metode kedua "per_pangkat"
Rencana awal rumus khusus Holding (TL/TM, base 3,5jt/3jt, multiplier clamp, pembagi ×7 dari Excel) **dibatalkan user** — diganti struktur sesederhana Tunjangan Transportasi: `pangkat_lembur_rates` (1 rate/jam per pangkat). Setting `lembur_metode` (`shift` default / `per_pangkat`). Setting lama `lembur_minimum_jam` yang ternyata dead code (dideklarasikan tapi tidak pernah dibaca) sekarang **benar-benar dipakai** sebagai gate jam minimum.

### 1.4 Batas akumulasi Cuti Tahunan per bulan
Setting `cuti_tahunan_max_per_bulan` (module `attendance`, company-scoped) — dicek di `AttendaceReqService::storeLeaveRequest()` sebelum request dibuat. Kolom baru `m_leave_types.category` + `LeaveTypeCategoryEnum::TAHUNAN` buat identifikasi eksplisit (bukan cocok nama teks). Holding di-override 5 hari/bulan, default global 0 (tidak dibatasi).

### 1.5 Halaman Periode Gaji
`App\Livewire\Keuangan\PayrollPeriodsPage` — versi portal dari `/admin/payroll-periods`, reuse Table/Form/Infolist Filament yang sama. Kolom "Status Generate" diubah dari icon boolean jadi teks ("Belum digenerate" / tanggal generate). Filter perusahaan (bukan search text). Tombol Regenerate cuma muncul kalau sudah pernah digenerate; tombol Generate cuma muncul kalau belum.

### 1.6 Halaman Payroll (hasil generate) + Detail + Slip Gaji
- `App\Livewire\Keuangan\PayrollsPage` — **custom (bukan reuse Filament)**, atas permintaan eksplisit user. Filter perusahaan + periode + cari nama/NIP. Tombol header "Import" (1 dropdown, isinya kondisional sesuai config: Insentif kalau metode "import", Koperasi/Bon kalau komponen itu aktif) — tiap item dropdown diwarnai aksen sendiri.
- `App\Livewire\Keuangan\PayrollDetailPage` — ringkasan + 2 kolom Pendapatan/Potongan, menampilkan **seluruh komponen gaji yang terdaftar aktif di company itu** (bukan cuma yang kebetulan menghasilkan angka — yang kosong tetap tampil Rp 0). Anotasi kecil di Lembur ("Dihitung > N jam") dan BPJS Kesehatan/Ketenagakerjaan (rate %). Tombol Tandai Sudah Dibayar, Preview Slip Gaji (buka PDF inline tab baru), Download Slip Gaji (paksa unduh).
- `PayrollService::componentRows()` — logic bareng yang dipakai baik oleh halaman detail portal MAUPUN PDF slip gaji, supaya keduanya selalu menampilkan daftar komponen yang identik (root-cause fix, bukan dua tempat kode nyalin logic yang sama).
- PDF Slip Gaji (`resources/views/pdf/payroll/payroll.blade.php`) — nama penerima di tanda tangan sekarang dinamis (bukan hardcode "Abdurahman Hakim"), stempel "RAHASIA" tidak dirotasi, baris "Total Gaji Diterima" + **Terbilang** (pakai `NumberFormatter::SPELLOUT` bawaan PHP ext-intl locale `id`, bukan nulis ulang logic angka-ke-kata) dalam bentuk tabel 2 baris × 3 kolom.

## 2. Bug produksi yang ditemukan & diperbaiki

1. **`App\Models\Payroll\PayrollPeriod` tanpa `$casts` tanggal** — `end_date`/`start_date` cuma string, bikin `IzinSeharianCalculator` crash (`->daysInMonth` di string) setiap kali ada kejadian Izin Seharian valid. Ditemukan lewat test baru, bukan cuma dari baca kode.
2. **Notifikasi Filament tidak pernah muncul di portal** — layout portal (`components/layout.blade.php`) tidak pernah merender `@livewire('notifications')`. Semua `Notification::make()->send()` di halaman portal (Komponen Gaji, Insentif, dst) diam-diam tidak tampil sejak awal dibangun. Fix 1 baris di layout bersama, otomatis benerin semua halaman sekaligus.
3. **Filament Table class yang dipakai standalone di Livewire polos butuh `->query()` eksplisit** — kelas Table Filament yang didesain jalan di dalam Resource (auto-infer model) error `"Table must have a [query()]"` kalau di-reuse di luar Resource. Ini nyata-nyata bikin `IncentivesPage` (dibangun sesi sebelumnya) **rusak di kondisi user sungguhan** (ketahuan dari log server, bukan dari test). Diperbaiki di 4 tempat: `PayrollPeriodsPage`, `IncentivesPage`, dan 2 section `MappingKomponenGajiPage`.
4. **Slip PDF menampilkan komponen yang di-toggle OFF** — `PayrollComponentEnum::alwaysOn()` (Fee Claim, Pajak Jasa Dokter) sebelumnya selalu disertakan di daftar komponen slip terlepas dari toggle `company_payroll_components`, jadi company yang tidak butuh (mis. Holding, bukan rumah sakit) tetap lihat baris "Pajak Jasa Dokter Rp 0". Diperbaiki: cuma tampil kalau memang toggle aktif ATAU beneran menghasilkan uang (data safety).

## 3. Perilaku Filament yang perlu diingat ke depan

Kalau mau reuse Table/Form/Infolist class Filament di Livewire component polos (bukan di dalam Filament Resource Page), 2 hal ini **tidak otomatis** dan harus ditambah manual:
- `->query(Model::query())` eksplisit di table (Resource biasanya inject otomatis lewat `getEloquentQuery()`).
- Authorization (`->authorize('create', Model::class)` dkk) — CreateAction/EditAction standalone **tidak** otomatis cek Policy tanpa dipanggil eksplisit, beda dari di dalam Resource yang auto-wired.

## 4. Konfigurasi placeholder di Holding (BUKAN data gaji asli)

Buat buktiin Generate Payroll jalan end-to-end, company Holding diisi angka bulat placeholder (bukan nominal sungguhan — perlu dikoreksi manual oleh Keuangan): `pangkat_salaries` (6 pangkat), `position_allowances` (matrix 90 sel, flat Rp200rb), `transport_allowances` (15 kelas, flat Rp150rb), 1 bracket `tenure_allowances` terbuka (Rp100rb), `pangkat_bpjs_bases` (semua pangkat → basis UMP), setting `ump` Holding = Rp3.000.000. Payroll periode Juli 2026 Holding sudah digenerate dengan config ini (23 payroll, sebagian nol karena data profil karyawan itu sendiri belum lengkap — bukan masalah config).

## 5. Test baru sesi ini

`tests/Feature/Payroll/` (banyak file baru) + `tests/Feature/CutiTahunanMonthlyLimitTest.php` + `tests/Feature/SalaryComponentsPageTest.php` — total 46 test payroll-related, semua hijau di akhir sesi. 2 file test lama (`SalaryComponentAssignmentTest`, `SalaryComponentCrudTest`) dihapus karena menguji class yang sudah dihapus total sejak Fase 1.

## 6. Yang belum dikerjakan / masih terbuka

- Verifikasi manual browser end-to-end (klik langsung) untuk sebagian besar fitur baru — baru dites lewat request HTTP terprogram + Livewire test, belum diklik manual sepenuhnya oleh user.
- Data riil (bukan placeholder) buat Holding dan seluruh company lain di 6 tabel config payroll.
- Template Excel + riwayat import untuk Import Insentif.
- `getLeaveUserBalance()` (saldo Cuti Tahunan) masih pakai cocok-nama teks, belum disatukan ke `LeaveTypeCategoryEnum` yang baru dipakai untuk batas per-bulan — inkonsistensi kecil, dicatat bukan diperbaiki.
- Cleanup `m_employee_types` (data tidak konsisten) — masih "dicatat dulu" dari sesi-sesi sebelumnya.
- Deploy ke production — semua pekerjaan sesi ini masih di branch `dev`.

## 7. Referensi
- `planning/2026-07-17-payroll-insentif-rsu-import.md`
- `planning/2026-07-17-payroll-mapping-komponen-gaji.md` (§3.1 addendum Lembur per_pangkat)
- `planning/2026-07-17-batas-cuti-tahunan-per-bulan.md`
- `planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md` (rekap sebelum Fase 3 ini dimulai)
- `deployment/CHECKLIST.md` — checklist deploy, belum diupdate untuk Fase 3.
