# Import Bon mode Detail + nama potongan tampil di slip (bukan "Bon" generik)

## Context

Bon punya 2 metode (setting `bon_metode`): **Simple** (1 baris nominal per periode, sudah bisa
diimport Excel lewat `BonImport`) dan **Detail** (banyak baris rincian potongan per periode,
SELAMA INI cuma bisa ditambah satu-satu manual lewat halaman Detail Payroll — import Excel
sengaja diblokir totalr, lihat docblock `BonImport`). Di slip gaji, SEMUA baris Bon (baik Simple
maupun Detail) tampil dengan nama generik **"Bon"** — tidak ada tempat menyimpan nama potongan
spesifik (mis. "Angsuran Motor", "Titipan Koperasi").

User minta 2 hal: (1) import Excel juga tersedia untuk mode Detail, (2) tiap rincian Bon
tampil di slip dengan **nama potongannya sendiri**, bukan "Bon" generik — untuk itu, `payroll_manual_lines`
butuh kolom baru buat nama tampilan per baris, dan itu HARUS ikut disalurkan sampai ke
`payroll_items.name` (yang dibaca `PayrollService::componentRows()` lalu dirender apa adanya di
`resources/views/livewire/keuangan/payroll-detail-page.blade.php` — cek kode: **tidak ada
agregasi/grouping by source_type**, tiap `PayrollItem` selalu 1 baris slip sendiri, jadi begitu
`name`-nya beda-beda per baris Bon, otomatis tampil sebagai baris terpisah tanpa perlu ubah
layer render slip).

Keputusan yang sudah dikonfirmasi user:
- Re-import NIP + nama potongan yang SAMA → **timpa** nominal (bukan baris baru). Kunci
  upsert: `(payroll_period_id, user_id, component='bon', label=nama_potongan)`.
- Field "Catatan" yang sudah ada di form manual line **tetap ada** sebagai keterangan bebas
  terpisah dari "Nama Potongan" (dua field beda fungsi).

## Perubahan data

**Migrasi baru**: tambah kolom `label` (string, nullable) ke `payroll_manual_lines`, setelah
`component`. Nullable karena Koperasi & Bon mode Simple tidak butuh nama custom (fallback ke
label komponen bawaan).

**Model** `PayrollManualLine` (`app/Models/Payroll/PayrollManualLine.php`): tambah `label` ke
`$fillable`.

**Calculator** `AbstractManualLineCalculator::calculate()`
(`app/Services/Payroll/Calculators/AbstractManualLineCalculator.php` baris ~38-40): `PayrollItem::create()`
pakai `'name' => $line->label ?: $this->componentLabel()` (bukan `$this->componentLabel()` polos).
Efeknya otomatis berlaku ke Koperasi & Bon Simple juga, tapi karena `label` selalu null di sana,
hasilnya identik dengan sekarang (fallback ke "Koperasi"/"Bon") — perubahan ini aman, tidak
butuh percabangan per-component.

## Import Excel mode Detail

**Kelas baru** `app/Imports/Payroll/BonDetailImport.php`, sibling dari `BonImport` (bukan
extends — validasi & guard-nya kebalikan, lebih jelas dipisah), interface publik SAMA
(`getErrors()`/`getSuccessCount()`/`getSuccessUserIds()`/`getPreview()`) supaya
`PayrollsPage::makeImport()` bisa pilih salah satu tanpa ubah kode pemanggil:
- Kolom: `nip`, `nama_potongan` (wajib, jadi `label`), `nominal`.
- Guard `bon_metode` KEBALIKAN dari `BonImport` — tolak kalau company modenya bukan `'detail'`.
- Validasi lain sama persis `BonImport::validateRow()` (user ketemu, payroll ada & belum
  dibayar, nominal > 0, employee type eligible lewat `PayrollComponentRecipient::isEligible()`)
  + validasi baru: `nama_potongan` tidak boleh kosong.
- Tulis via `PayrollManualLine::updateOrCreate(['payroll_period_id'=>..,'user_id'=>..,'component'=>'bon','label'=>$namaPotongan], ['amount'=>..,'note'=>'Import Excel'])`.

**Template baru** `app/Exports/Payroll/BonDetailImportTemplateExport.php` — headings
`['nip', 'nama_potongan', 'nominal']`, contoh baris pakai nama potongan nyata (mis. "Angsuran
Motor", "Titipan Koperasi").

**Controller** `PayrollManualLineTemplateController::downloadTemplate()` — terima query param
`mode` (default `simple`), pilih export class sesuai mode.

**`PayrollsPage`** (`app/Livewire/Keuangan/PayrollsPage.php`):
- `importGuardPasses()` (baris ~362): hapus blok yang menolak mode Detail — sekarang KEDUA mode
  boleh lewat gate ini (tetap cek `bonAktif`).
- `makeImport()`: `return $this->bonMetode === 'detail' ? new BonDetailImport(...) : new BonImport(...);`
- Blade `payrolls-page.blade.php`: link download template tambah `?mode={{ $this->bonMetode }}`;
  tabel preview import tambah kolom "Nama Potongan" ketika mode Detail (kondisional, karena
  `BonImport` punya `nama_import`/`nama_sistem` sedangkan `BonDetailImport` punya `nama_potongan`
  — bentuk array preview beda per kelas, disesuaikan di blade dengan cek `$importType`/`bonMetode`).

## Halaman Detail Payroll (input manual satu-satu, mode Detail)

**`PayrollDetailPage`** (`app/Livewire/Keuangan/PayrollDetailPage.php`):
- Tambah `public ?string $manualLineLabel = null;`.
- `openManualLineModal()`: isi `manualLineLabel` dari `$line->label` saat edit, kosong saat
  tambah baru.
- `saveManualLine()`: validasi `manualLineLabel` **wajib** kalau `manualLineComponent === 'bon'
  && $this->bonMetode === 'detail'` (pakai `Rule::requiredIf` atau cek manual sebelum
  `$this->validate()`), sertakan `'label' => $this->manualLineLabel` di data create/update.
  Untuk Koperasi/Bon Simple, `label` tetap dikirim null (tidak divalidasi wajib).

**Blade** `payroll-detail-page.blade.php`:
- Modal manual line: tambah input "Nama Potongan" (di atas field Nominal), muncul cuma kalau
  `manualLineComponent === 'bon' && $this->bonMetode === 'detail'`.
- **Perbaikan struktural**: blok "+Tambah" dan sub-list Ubah/Hapus baris Bon (baris ~139-165)
  sekarang DI DALAM `@forelse ($rows['deductions'] as $row)`, dicocokkan ke tiap `$row` yang
  `component === 'bon'` — begitu ada BANYAK baris Bon dengan nama beda-beda (bukan cuma 1 baris
  "Bon" seperti sekarang), blok ini akan render ULANG untuk SETIAP baris Bon (bug laten yang
  baru kelihatan sekarang). Pindahkan blok "+Tambah"/sub-list editor itu KELUAR dari loop
  `$rows['deductions']`, render SEKALI saja setelah `@forelse` selesai (gated `$this->bonAktif`
  saja, bukan per-`$row`).

## Yang TIDAK diubah

- Koperasi tetap fixed label "Koperasi" (tidak pernah diisi `manualLineLabel`, `label` selalu
  null di DB) — cuma Bon yang dapat fitur nama custom.
- `PayrollService::componentRows()` — TIDAK perlu diubah, sudah otomatis mendukung banyak baris
  bernama beda per `source_type` karena tidak melakukan agregasi.
- Bon mode Simple — perilaku sekarang (1 baris, `BonImport`, label selalu "Bon") tidak berubah
  sama sekali.

## Verifikasi

- `php artisan migrate` bersih.
- Test baru `tests/Feature/Payroll/BonDetailImportTest.php`: import Excel mode Detail bikin
  PayrollItem dengan `name` = nama potongan (bukan "Bon"); re-import NIP+nama sama menimpa
  bukan duplikat; company mode Simple ditolak; nama_potongan kosong ditolak.
- Update `tests/Feature/Payroll/BonImportTest.php` kalau ada asersi yang kebentur perubahan
  `AbstractManualLineCalculator` (harusnya tidak, karena `label` null → fallback "Bon" identik
  perilaku lama) — jalankan scoped buat pastikan masih hijau.
- Test baru untuk `PayrollDetailPage::saveManualLine()` mode Detail: field Nama Potongan wajib,
  tersimpan ke `PayrollItem.name` setelah generate/regenerate.
- Manual: buka halaman Payroll → pilih company mode Bon Detail → tombol Import Bon sekarang
  aktif → download template → isi nip/nama_potongan/nominal → upload → cek slip payroll
  karyawan itu menampilkan tiap potongan dengan namanya sendiri, bukan "Bon" berulang.
