# Planning: Insentif RSU — Setting Metode Master/Import + Import Excel per-NIP (2026-07-17)

Status: **dieksekusi penuh (kode + test), migrasi tidak perlu (tidak ada skema baru — reuse `settings` + `payroll_items`).** Setting `insentif_metode` sudah di-seed ke DB lokal (`php artisan db:seed --class=PayrollSettingsSeeder`, idempotent). **BELUM diverifikasi manual di browser** (upload file Excel sungguhan), **BELUM di-commit**, **BELUM di-deploy**.

Lanjutan dari [`planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md`](next/2026-07-17-rekap-sesi-dan-lanjutan.md) poin 7 ("Fitur import Insentif RSU Banjarbaru — workstream terpisah, belum disentuh").

## 1. Latar belakang

`IncentiveCalculator` (Fase 1) sudah bisa baca tabel `incentives` (input manual, dipakai Holding — nominal tetap per bulan). RSU beda: perhitungan insentifnya sangat dinamis (banyak faktor), jadi tidak realistis diinput manual lewat tabel master. Rencana: fitur import Excel yang mencocokkan karyawan lewat NIP, dan importnya **dilakukan SETELAH payroll periode itu digenerate** (bukan sebelum, beda dari alur "master") — menempel nominal insentif ke `Payroll` yang sudah ada.

Karena 2 alur ini beda total (baca-sebelum-generate vs tempel-sesudah-generate) dan berbeda per company, dibutuhkan 1 setting company-scoped `insentif_metode` (`'master'` | `'import'`) — pola sama seperti `lembur_minimum_jam` (required setting, muncul otomatis di halaman Komponen Gaji saat komponen Insentif di-expand).

## 2. Keputusan desain (dikonfirmasi user sebelum eksekusi)

1. **Re-import periode yang sama = REPLACE, bukan additive.** Upload ulang file (misal ada koreksi) untuk NIP yang sama mengganti nominal `PayrollItem` sebelumnya (`updateOrCreate` keyed `[payroll_id, source_type=insentif]`), tidak menumpuk baris baru. Mencegah dobel-hitung kalau admin tidak sengaja upload 2x.
2. **Regenerate payroll periode yang sudah punya insentif ter-import DIBLOKIR di UI**, bukan cuma diterima risikonya. Insentif "import" tidak dihitung ulang dari sumber manapun (beda dari komponen lain) — kalau `regeneratePayroll()` menghapus payroll unpaid, insentif itu hilang tanpa cara otomatis menulis ulang. `PayrollPeriodsTable`'s action `regeneratePayroll` sekarang cek `PayrollService::periodHasImportedIncentives()` dulu sebelum dispatch job; kalau true, tampilkan Notification danger dan batalkan (bukan exception di background job — guard di level Action supaya admin dapat feedback langsung, bukan cuma di log).
3. **UI import ditaruh di halaman baru terpisah** (`/keuangan/import-insentif`, menu "Penggajian > Import Insentif"), bukan tab di halaman Payroll Period yang sudah ada — konsisten sama pola halaman Keuangan lain (Komponen Gaji, Insentif) yang masing-masing punya halaman sendiri.
4. **Baris dengan NIP tidak ketemu / belum ada payroll di periode itu / payroll sudah `is_paid` → di-skip, dilaporkan sebagai error per baris, TIDAK menghentikan baris lain.** Pola sama `EmployeeImport` (`ToCollection` + `DB::transaction` per baris + `try/catch` kumpulin error, bukan all-or-nothing).

## 3. Perubahan

### 3.1 Setting baru
- `PayrollComponentEnum::requiredSettings()` — `self::INSENTIF => ['insentif_metode']` (`app/Enum/Payroll/PayrollComponentEnum.php`).
- `PayrollSettingsSeeder` — key `insentif_metode`, type `string`, default `'master'` (SettingTypeEnum cuma punya integer/decimal/boolean/string — tidak ada tipe "select", jadi UI-nya di-special-case di blade, lihat 3.2).
- `Setting::resolve()` sudah otomatis cocok buat type `string` (default branch, cast apa adanya) — tidak perlu perubahan di `app/Models/Setting.php`.

### 3.2 UI Komponen Gaji
`resources/views/livewire/keuangan/partials/component-card.blade.php` — special-case key `insentif_metode` render `<select>` (Master/Import) alih-alih `<input>` generik. Sengaja tidak menambah `SettingTypeEnum::SELECT` baru (cuma 1 key yang butuh ini sejauh ini, generalisasi belum diperlukan).

### 3.3 `IncentiveCalculator`
Baca `Setting::resolve('insentif_metode', $period->company_id, default: 'master')` di awal `calculate()`. `!== 'master'` → `return` langsung, tidak sentuh tabel `incentives` sama sekali (baris-barisnya tetap `is_processed=false`, tidak "hilang", cuma tidak pernah dibaca kalau company itu pakai metode import).

### 3.4 `App\Imports\Payroll\IncentiveImport` (baru)
`ToCollection` + `WithHeadingRow` (pola sama `App\Imports\Employees\EmployeeImport`). Kolom wajib: `nip`, `nominal` (terima juga `jumlah` sebagai alias). Per baris (dalam `DB::transaction`): resolve `User` by `nip` → cari `Payroll` (`user_id` + `payroll_period_id`, HARUS sudah ada) → tolak kalau `is_paid` → hitung `delta = nominal_baru - nominal_lama` → `PayrollItem::updateOrCreate` (source_type=`insentif`) → `$payroll->increment('gross_salary', $delta)` + `increment('net_salary', $delta)`. Error per baris ditangkap, tidak menghentikan baris lain (`getErrors()`/`getSuccessCount()`, sama kontrak `EmployeeImport`).

### 3.5 `App\Livewire\Keuangan\ImportIncentivesPage` (baru)
Livewire polos (bukan Filament Resource Page — pola sama `SalaryComponentsPage`/`IncentivesPage`) + `WithFileUploads` (native Livewire, bukan `Filament\Forms\Components\FileUpload` — tidak butuh schema/form machinery Filament buat 1 field file). Pilih company → pilih `PayrollPeriod` (cuma yang `processed_at` sudah terisi) → upload file → `Excel::import()` sinkron (tidak ada cabang job untuk file besar seperti `EmployeeImport` — dataset insentif per periode jauh lebih kecil dari data karyawan, YAGNI). Kalau `insentif_metode` company itu bukan `'import'`, form disembunyikan, tampil pesan arahan ke halaman Komponen Gaji.

Route `keuangan/import-insentif` (`routes/web.php`), permission `SISTEM_KEUANGAN` (`config/portal-pages.php`), link di `nav.blade.php` + `pwa-bottom-nav.blade.php` (dropdown/grid "Penggajian" yang sudah ada, sebelah "Insentif").

### 3.6 Guard regenerate
`PayrollService::periodHasImportedIncentives(PayrollPeriod $period): bool` — cek ada `payroll_items.source_type=insentif` di payroll unpaid periode itu. Dipanggil di `PayrollPeriodsTable`'s action `regeneratePayroll` SEBELUM dispatch `RegeneratePayrollJob` — kalau true, Notification danger + batalkan (job tidak jalan). **Catatan**: guard ini generik (cek `source_type=insentif` apa adanya, tidak bedakan asal master/import) — untuk company metode `'master'`, kalau kebetulan ada insentif ter-generate, regenerate juga akan diblokir. Ini bukan bug: PayrollItem insentif "master" pun tidak akan otomatis ditulis ulang kalau `Incentive.is_processed` sudah `true` (batasan lama yang sudah ada sebelum sesi ini, di luar scope untuk diperbaiki sekarang) — jadi memblokir regenerate untuk kasus itu juga aman/wajar, bukan cuma buat kasus import.

## 4. Test baru
- `tests/Feature/Payroll/IncentiveCalculatorMetodeTest.php` — master baca dari `incentives`, import skip total (baris `incentives` tidak tersentuh), default ke master kalau setting belum ada.
- `tests/Feature/Payroll/IncentiveImportTest.php` — create+increment totals, re-import replace (bukan dobel baris), NIP tidak ketemu di-skip+dilaporkan, payroll belum ada di periode di-skip+dilaporkan, payroll `is_paid` di-skip.
- `tests/Feature/Payroll/PayrollServiceRegenerateGuardTest.php` — true/false `periodHasImportedIncentives()`, false kalau item cuma ada di payroll yang sudah `is_paid` (tidak relevan buat regenerate karena payroll itu tidak disentuh).

Semua lulus (`php artisan test tests/Feature/Payroll` — 15 test, 32 assertion).

## 5. Yang TIDAK termasuk sesi ini
- Template Excel download (`Export` class) untuk kolom `nip`/`nominal` — belum dibangun, admin RSU perlu tahu formatnya secara manual/dikomunikasikan langsung dulu.
- Riwayat/log import (siapa import kapan, berapa baris) — tidak ada tabel/tampilan riwayat, cuma hasil sukses/error yang tampil sekali di halaman saat itu juga.
- Verifikasi manual di browser dengan file Excel sungguhan (test yang ada baru unit-level, panggil `$import->collection()` langsung, bukan lewat `Excel::import()` file asli) — perlu dicoba end-to-end sebelum dipakai RSU beneran.
- Deploy ke production.

## 6. Referensi
- [`planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md`](next/2026-07-17-rekap-sesi-dan-lanjutan.md) — poin 7 (sumber tugas ini).
- `app/Imports/Employees/EmployeeImport.php` + `app/Filament/Resources/Employees/Pages/ListEmployees.php:38-108` — pola import Excel sinkron + Notification error-list yang dicontek.
- `app/Filament/Resources/Payroll/Payrolls/RelationManagers/PayrollItemsRelationManager.php:101-150` — pola "tambah PayrollItem ke Payroll yang sudah ada + re-sync total lewat increment/decrement" yang dicontek `IncentiveImport`.
