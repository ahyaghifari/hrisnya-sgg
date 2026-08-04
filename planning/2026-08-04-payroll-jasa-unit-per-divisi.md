# Fitur "Jasa Unit" — komponen gaji per divisi

## Context

Sistem payroll saat ini punya 17 komponen gaji tetap (`PayrollComponentEnum`), semuanya berlaku sama untuk semua karyawan tipe tertentu di suatu company. Tapi di rumah sakit ada banyak jasa/tunjangan yang cuma berlaku untuk divisi tertentu (Jasa Perujuk Rawat → IGD/RANAP/ICU saja, Jasa Ambulan → divisi Ambulan saja, Jasa Paket Infus → IGD saja, dst) dan jumlahnya bisa sangat banyak jenisnya — tidak realistis dibuatkan 1 enum case per jasa.

Solusinya: 1 komponen baru `JASA_UNIT` yang jadi "wadah" — admin bikin katalog jasa apa saja yang ada (nama bebas), assign tiap jasa ke divisi mana saja yang berhak, lalu tiap generate periode payroll, finance input manual nominalnya per karyawan (karyawan mana dapat jasa apa, berapa) di halaman input massal mirip pola Omset/Tunjangan Masa Kerja yang sudah ada.

Pola yang direplikasi: `AbstractManualLineCalculator` (Koperasi/Bon) untuk cara baca input manual saat generate, dan pola add/remove/save-all-rows di `MappingKomponenGajiPage` (tenureRows/omsetCategoryRows) untuk UI bulk entry — bukan bikin pola baru.

## Data model

**Migrasi baru (3 file, urutan ini):**
1. `jasa_unit_types` — katalog jasa per company: `id` (ulid), `company_id` (FK companies), `name`, timestamps.
2. `jasa_unit_type_org_structures` — pivot assignment ke divisi: `jasa_unit_type_id` (FK), `org_structure_id` (FK m_org_structures), unique gabungan. Tanpa ulid/timestamps (pivot murni).
3. `jasa_unit_details` — input nominal per periode: `id` (ulid), `payroll_period_id` (FK), `user_id` (FK users), `jasa_unit_type_id` (FK), `amount`, `note` nullable, `created_by` (FK users), timestamps.

**Model baru:**
- `app/Models/Payroll/JasaUnitType.php` — `belongsTo(Companies)`, `belongsToMany(OrgStructure, 'jasa_unit_type_org_structures')` untuk divisi yang berhak.
- `app/Models/Payroll/JasaUnitDetail.php` — `belongsTo` ke PayrollPeriod/User/JasaUnitType/creator, pola sama persis `PayrollManualLine`.

**Enum:** tambah case di `app/Enum/Payroll/PayrollComponentEnum.php`:
```php
case JASA_UNIT = 'jasa_unit'; // di grup PENDAPATAN
```
`type()` → EARNING, `label()` → 'Jasa Unit'. Tidak perlu `requiredSettings()` (default `[]` sudah cukup) — otomatis muncul di halaman "Komponen Gaji" (`SalaryComponentsPage`) karena halaman itu sudah loop semua `PayrollComponentEnum::cases()`, tidak perlu diubah.

## Calculator

`app/Services/Payroll/Calculators/JasaUnitCalculator.php` (implements `PayrollComponentCalculator`, bukan extends `AbstractManualLineCalculator` karena label per baris beda-beda, bukan 1 label tetap):

```php
public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
{
    $details = JasaUnitDetail::with('jasaUnitType')
        ->where('user_id', $user->id)
        ->where('payroll_period_id', $period->id)
        ->get();

    foreach ($details as $detail) {
        $amount = (float) $detail->amount;
        if ($amount <= 0) continue;

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => $detail->jasaUnitType->name,
            'type' => PayrollComponentTypeEnum::EARNING->value,
            'quantity' => 1,
            'amount' => $amount,
            'note' => $detail->note,
            'source_type' => PayrollComponentEnum::JASA_UNIT->value,
        ]);

        $context->addEarning($amount);
    }
}
```
Tiap jasa jadi baris `PayrollItem` terpisah dengan nama jasanya sendiri (sesuai keputusan: baris terpisah per jenis jasa di slip) — `source_type` sama semua (`jasa_unit`) supaya fallback Rp0 di `PayrollService::runComponent()` tidak dobel-buat baris kalau minimal 1 baris nyata sudah ada.

Daftarkan di `PayrollCalculatorRegistry::MAP`:
```php
PayrollComponentEnum::JASA_UNIT->value => JasaUnitCalculator::class,
```

Eligibility (toggle ON per company + tipe karyawan penerima) otomatis jalan lewat mekanisme yang sudah ada (`CompanyPayrollComponent`, `PayrollComponentRecipient`) — tidak ada kode tambahan di `PayrollService`.

## Halaman 1: Master data jasa + assign divisi

Tambah sebagai **tab ke-3** di `MappingKomponenGajiPage` (`app/Livewire/Keuangan/MappingKomponenGajiPage.php`), pola persis `omsetCategoryRows` (baris 550-611) tapi tiap row juga punya multi-select divisi:

```php
public array $jasaUnitRows = []; // ['id' => ?, 'name' => '', 'org_structure_ids' => []]

private function loadJasaUnitTypes(): void
private function addJasaUnitRow(): void
private function removeJasaUnitRow(int $index): void
private function saveJasaUnitTypes(): void  // updateOrCreate row lalu sync() pivot divisi
```
Divisi yang bisa dipilih: `OrgStructure::where('company_id', $this->companyId)->where('level', OrgStructurLevel::LEVEL_DIVISION->value)->orderBy('name')->get()` — pakai `<select multiple>`/tom-select (sudah dipakai di halaman lain, lihat `tests/e2e/helpers/tom-select.ts`) untuk UX pilih banyak divisi.

Gate simpan pakai permission yang sudah ada: `CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI` (sama seperti tab lain di halaman ini) — tidak perlu permission baru.

View: tambah section baru di `resources/views/livewire/keuangan/mapping-komponen-gaji-page.blade.php`, niru markup section Kategori Omset yang sudah ada.

## Halaman 2: Input nominal per periode

Livewire baru `app/Livewire/Keuangan/JasaUnitInputPage.php` (view: `resources/views/livewire/keuangan/jasa-unit-input-page.blade.php`), struktur gabungan `OmsetPage` (company + period selector, filter company yang komponen JASA_UNIT-nya aktif) dan pola bulk-row `tenureRows`:

```php
public ?string $companyId = null;
public ?string $periodId = null;
/** @var array<int, array{id:?string, user_id:?string, jasa_unit_type_id:?string, amount:?string}> */
public array $rows = [];

addRow(): void        // tambah baris kosong
removeRow(int $i): void   // hapus dari DB kalau id ada, lalu unset+reindex array
save(): void           // validasi tiap baris (user+jasa+amount required, amount numeric>0),
                        // updateOrCreate per baris by id, backfill id baru ke array
```

- **Company/period picker**: sama pola `OmsetPage` — company list difilter yang `CompanyPayrollComponent` JASA_UNIT-nya aktif.
- **Dropdown karyawan** per baris: `User::where('company_id', $companyId)->where('is_active', true)` DAN eligible lewat `PayrollComponentRecipient::isEligible(..., PayrollComponentEnum::JASA_UNIT->value, ...)` — pola sama `KoperasiPage::getAvailableUsersProperty()`.
- **Dropdown jasa** per baris: difilter sesuai divisi karyawan yang dipilih di baris itu (keputusan: filter, bukan tampil semua) — method `jasaOptionsFor(?string $userId)`:
  ```php
  $orgStructureId = User::find($userId)?->activeJobPosition?->jobPosition?->org_structure_id;
  return JasaUnitType::where('company_id', $this->companyId)
      ->whereHas('orgStructures', fn ($q) => $q->where('org_structures.id', $orgStructureId))
      ->orderBy('name')->get();
  ```
  Kalau karyawan belum dipilih atau tidak punya divisi → dropdown jasa kosong (disabled) sampai karyawan dipilih.
- Gate simpan: reuse `EDIT_MAPPING_KOMPONEN_GAJI` juga (konsisten dengan `OmsetPage::getCanEditProperty()` yang reuse permission yang sama untuk halaman input terpisah).
- Load awal: `JasaUnitDetail::where('payroll_period_id', $periodId)->get()` → map ke `$rows`.

## Routing & navigasi

`routes/web.php` — 1 route baru (master data cukup nempel di route `keuangan.mapping-komponen-gaji` yang sudah ada, tidak perlu route baru untuk itu):
```php
Route::get('/keuangan/jasa-unit', JasaUnitInputPage::class)->name('keuangan.jasa-unit');
```

`resources/views/components/nav.blade.php` — tambah 1 item di dropdown "Penggajian" grup Payroll (dekat link Omset), plus tambahkan `keuangan.jasa-unit` ke daftar `routeIs([...])` untuk highlight aktif.

`resources/views/components/pwa-bottom-nav.blade.php` — tambah 1 `pwa-menu-grid-item` di divider "Sistem Keuangan" (nav PWA saat ini memang belum lengkap tapi ikut ditambahkan untuk fitur baru ini, bukan untuk fitur lama yang sudah tertinggal).

## Yang TIDAK diubah (sudah otomatis kompatibel)

- `PayrollService::runComponent()` / `generateForUser()` — gating aktif/nonaktif + eligibility employee type sudah generic per `PayrollComponentEnum`, tidak perlu sentuh.
- `SalaryComponentsPage` (halaman "Komponen Gaji" toggle ON/OFF) — loop semua `PayrollComponentEnum::cases()`, JASA_UNIT otomatis muncul.
- `PayrollComponentRecipientsPage` (halaman "Penerima Komponen Gaji") — filter `CompanyPayrollComponent::is_active`, otomatis include JASA_UNIT begitu company mengaktifkannya.
- Tampilan slip gaji (`payroll-detail-page.blade.php`, `pdf/payroll/payroll.blade.php`) — render generic dari `payroll_items`, otomatis menampilkan baris-baris jasa unit tanpa perubahan.

## Verifikasi

- `php artisan migrate` jalan bersih di SQLite testing.
- Buat 1 test feature baru `tests/Feature/Payroll/JasaUnitCalculatorTest.php` (pola sama `tests/Feature/Payroll/BonImportTest.php`/manual-line test lain): seed company + org structure divisi + jasa unit type ter-assign ke divisi itu + karyawan di divisi itu → input `JasaUnitDetail` → generate payroll → assert `PayrollItem` muncul dengan nama & amount benar, dan karyawan di divisi lain yang tidak diberi input tidak dapat baris jasa apa pun.
- Manual: jalankan `php artisan test --filter=JasaUnit` scoped saja (bukan full suite).
- Cek manual di browser: halaman Mapping Komponen Gaji tab baru bisa tambah/hapus jasa + assign divisi; halaman Input Jasa Unit bisa tambah beberapa baris karyawan+jasa+nominal lalu simpan; generate payroll periode itu dan lihat slip menampilkan baris jasa terpisah.
