# Planning: Payroll Fase 2 — BPJS Toggle+Mapping, Gabung Izin Terlambat/Pulang Cepat, Izin Seharian (2026-07-17)

Status: **dieksekusi penuh ke database LOKAL (2026-07-17), TERMASUK 1 koreksi desain BPJS di luar rencana awal dokumen ini (lihat §10 Addendum).** Migration jalan bersih, `regeneratePayroll()` disanity-check via transaction+rollback (tidak ada exception, payroll `is_paid=true` tidak tersentuh). **BELUM di-commit ke git, BELUM di-deploy ke production, `php artisan test` BELUM dijalankan ulang.** UI mapping per-pangkat untuk BPJS (§2.3.c versi awal) **SENGAJA DITUNDA** — bukan bagian eksekusi ini, lihat §10.

Lanjutan dari [`planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md`](2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md) (Fase 1 — sudah di-commit ke `dev`, belum deploy). Dasar keputusan: [`analisis-payroll-4`](../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) §7B (addendum kedua, 2026-07-17).

Beda dari Fase 0/1: ini bukan rearsitektur besar, cuma 4 penyesuaian tertarget ke Registry yang sudah ada. Tidak ada tabel di-drop, tidak ada migration destruktif — semua **additive atau rename kolom/enum**.

---

## 1. Prinsip Keamanan (tetap berlaku dari Fase 0/1)

- `payrolls.is_paid = true` tetap batas suci — tidak disentuh.
- Rename `PayrollComponentEnum` case (§3) mengubah **string value** yang tersimpan di `company_payroll_components.component` dan `payroll_items.source_type` — butuh migration data (UPDATE existing rows), bukan cuma migration schema. Karena baru Fase 1 yang jalan di lokal (belum ada payroll live pakai skema baru), risikonya kecil sekarang, tapi kalau ini sudah pernah di-generate di lokal, backfill datanya juga (§4).
- Urutan Calculator di orchestrator berubah (§5) — ini titik paling rawan regresi diam-diam (total gaji salah tanpa error) di seluruh Fase 2, butuh verifikasi manual paling ketat (§6).

---

## 2. Item 1 — BPJS: dari asumsi "2 kelompok pangkat" ke toggle + mapping per pangkat

### 2.1 Yang sudah ADA (jangan dibangun ulang)

Diverifikasi dari kode Fase 1 — mekanismenya **sudah nyaris pas**, cuma butuh dilengkapi, bukan dirombak:
- `m_pangkats.bpjs_basis` (string, nullable) — sudah ada dari Fase 0, isinya rencana `'ump'` | `'declared_value'`.
- `AbstractBpjsCalculator` — sudah baca `$user->profile?->pangkat?->bpjs_basis`, resolve ke `GlobalPayrollSettingEnum::UMP` atau `Setting('bpjs_declared_value', $companyId)`.
- `PayrollComponentEnum::requiredSettings()` — BPJS sudah punya `bpjs_declared_value` di daftar.

### 2.2 Yang KURANG (gap dari kebutuhan user)

1. Kalau `bpjs_basis` pangkat itu `NULL` (belum di-mapping), `AbstractBpjsCalculator` saat ini **skip total** (0 baris) — bukan fallback ke UMP. Ini beda dari kebutuhan user: "kalau tidak dicentang [bedanya], otomatis pakai UMP".
2. Belum ada toggle company-level "apakah ada perbedaan basis" — tanpa itu, admin harus mapping manual tiap pangkat walau company itu tidak butuh pembedaan sama sekali.
3. `PangkatsForm` (`app/Filament/Resources/Master/Pangkats/Schemas/PangkatsForm.php`) **belum expose field `bpjs_basis`** sama sekali — cuma ada `company_id` + `name`. Ini yang jadi "halaman mapping" yang dimaksud user — reuse Resource yang sudah ada, jangan bikin halaman baru.

### 2.3 Perubahan

**a. Setting baru** — tambah ke `requiredSettings()` BPJS di `PayrollComponentEnum`:
```php
self::BPJS_KESEHATAN, self::BPJS_KETENAGAKERJAAN => ['bpjs_basis_berbeda', 'bpjs_declared_value'],
```
`bpjs_basis_berbeda` — `SettingTypeEnum::BOOLEAN`, company-scoped, default `false`. Tambah ke `PayrollSettingsSeeder`.

**b. `AbstractBpjsCalculator::calculate()`** — logic basis diubah:
```php
$basisBerbeda = Setting::resolve('bpjs_basis_berbeda', $period->company_id, default: false);

if (! $basisBerbeda) {
    $basis = Setting::resolve(GlobalPayrollSettingEnum::UMP->value, null);
} else {
    $pangkatBasis = $user->profile?->pangkat?->bpjs_basis;
    $basis = match ($pangkatBasis) {
        'declared_value' => Setting::resolve('bpjs_declared_value', $period->company_id),
        default => Setting::resolve(GlobalPayrollSettingEnum::UMP->value, null), // 'ump' ATAU belum di-mapping -> fallback UMP
    };
}
```
Beda dari sekarang: **tidak pernah skip diam-diam lagi** — kalau `$basis` tetap kosong (UMP company itu sendiri belum diisi), baru skip (kasus itu tetap valid, bukan bug).

**c. `PangkatsForm`** — tambah field baru (di bawah `name`, `columnSpanFull`):
```php
Select::make('bpjs_basis')
    ->label('Basis BPJS')
    ->options(['ump' => 'UMP (default)', 'declared_value' => 'Nilai Tetap Perusahaan'])
    ->default('ump')
    ->helperText('Cuma berlaku kalau toggle "Basis BPJS berbeda per pangkat" aktif di halaman Komponen Gaji.')
    ->columnSpanFull(),
```
Tidak perlu `visible()` bersyarat ke toggle company — biarkan selalu terlihat di form Pangkat (kesederhanaan lebih penting daripada menyembunyikan field yang secara teknis tetap valid diisi kapan saja, toggle di Komponen Gaji yang menentukan APAKAH nilainya dipakai, bukan APAKAH bisa diisi).

### 2.4 TIDAK diubah
- Skema tabel — tidak ada kolom/tabel baru selain 1 baris `settings`.
- `BpjsKesehatanCalculator`/`BpjsKetenagakerjaanCalculator` (subclass concrete) — tidak berubah, semua logic ada di abstract parent.

---

## 3. Item 2 — Gabung Izin Terlambat Masuk + Izin Pulang Cepat jadi 1 komponen gaji

Cakupan: **cuma sisi payroll**. `PermissionTypeCategoryEnum` (sisi absensi/pengajuan izin) TIDAK berubah — tetap 2 kategori (`IZIN_TERLAMBAT_MASUK`, `IZIN_PULANG_CEPAT`), dipakai buat pengajuan & laporan seperti sekarang.

### 3.1 `PayrollComponentEnum`
Hapus `IZIN_TERLAMBAT_MASUK` + `IZIN_PULANG_CEPAT`, ganti 1 case:
```php
case IZIN_TERLAMBAT_PULANG_CEPAT = 'izin_terlambat_pulang_cepat';
```
Update `type()` (DEDUCTION), `label()` ("Izin Terlambat Masuk / Pulang Cepat"), `requiredSettings()` (`['izin_potongan_flat']`, key setting-nya tidak berubah).

### 3.2 `AbstractIzinFlatCalculator`
`permissionTypeCategory(): string` jadi **array**, query `whereIn` bukan `where`:
```php
abstract protected function permissionTypeCategories(): array;
// ...
->whereHas('permissionType', fn ($q2) => $q2->whereIn('category', $this->permissionTypeCategories()));
```

### 3.3 Calculator
Hapus `IzinTerlambatMasukCalculator.php` + `IzinPulangCepatCalculator.php`, ganti 1 file `IzinTerlambatPulangCepatCalculator.php`:
```php
protected function permissionTypeCategories(): array
{
    return [
        PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value,
        PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value,
    ];
}
protected function componentValue(): string { return PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->value; }
// componentLabel()/componentType() sama pola, ganti referensi enum
```

### 3.4 `PayrollCalculatorRegistry`
Ganti 2 entry `IZIN_TERLAMBAT_MASUK`/`IZIN_PULANG_CEPAT` jadi 1 entry `IZIN_TERLAMBAT_PULANG_CEPAT => IzinTerlambatPulangCepatCalculator::class`.

### 3.5 Migration data
`company_payroll_components` yang sudah punya baris `component = 'izin_terlambat_masuk'` ATAU `'izin_pulang_cepat'` untuk company yang sama — gabung jadi 1 baris `izin_terlambat_pulang_cepat` (`is_active` = true kalau salah satu tadinya aktif). Kalau ini masih di database lokal (belum ada di production), migration data cukup simpel — cek dulu `SELECT * FROM company_payroll_components WHERE component IN ('izin_terlambat_masuk','izin_pulang_cepat')` sebelum tulis migration, supaya tidak menulis migration kosong untuk kasus yang tidak pernah ada datanya (Fase 1 belum sempat dipakai generate payroll sungguhan).

---

## 4. Item 3 — `IzinSeharianCalculator` (rumus terkonfirmasi, `analisis-payroll-4` §7B.3)

```
rate_harian = T8 / hari_dalam_bulan_periode
potongan    = rate_harian * jumlah_kejadian_izin_seharian
```

### 4.1 Kontrak
```php
class IzinSeharianCalculator implements PayrollComponentCalculator
{
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $count = AttendanceRequests::query()
            ->where('user_id', $user->id)->where('type', 'permission')
            ->where('status', AttendanceReqStatus::APPROVED->value)
            ->whereHas('permissionDetails', fn ($q) => $q
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->whereHas('permissionType', fn ($q2) => $q2->where('category', PermissionTypeCategoryEnum::IZIN_SEHARIAN->value)))
            ->count();

        if ($count <= 0) return;

        $hariDalamBulan = $period->end_date->daysInMonth; // Carbon, EOMONTH-equivalent
        if ($hariDalamBulan <= 0) return;

        $rateHarian = $context->grossSalary / $hariDalamBulan; // T8 = total pendapatan TERKUMPUL SAAT INI
        $amount = round($rateHarian * $count, 2);
        if ($amount <= 0) return;

        PayrollItem::create([...'quantity' => $count, 'unit' => 'hari', 'unit_amount' => round($rateHarian, 2), 'amount' => $amount, 'source_type' => PayrollComponentEnum::IZIN_SEHARIAN->value]);
        $context->addDeduction($amount);
    }
}
```

**Catatan `$period->end_date->daysInMonth`**: `DAY(EOMONTH($B$2,0))` di Excel = jumlah hari di bulan tanggal referensi (`$B$2`, tanggal akhir periode) — di kode ini dipetakan ke `daysInMonth` milik `$period->end_date` (bukan `now()`), supaya konsisten kalau payroll di-generate telat/retroaktif.

**Catatan urutan `PermissionTypeCategoryEnum::IZIN_SEHARIAN`**: cek dulu case ini sudah ada — kalau belum, tambah 1 case baru ke enum itu (§7 sebelumnya cuma menyebut 4 case: TERLAMBAT_MASUK, PULANG_CEPAT, SEHARIAN, MENINGGALKAN_JAM_KERJA — SEHARIAN kemungkinan sudah ada, verifikasi saat implementasi, bukan diasumsikan).

### 4.2 Daftarkan ke Registry
```php
PayrollComponentEnum::IZIN_SEHARIAN->value => IzinSeharianCalculator::class,
```
Keluar dari `PayrollComponentEnum::notYetAvailable()` (sisa cuma `IZIN_MENINGGALKAN_JAM_KERJA` sebelum §5... tapi §5 menghapusnya juga, jadi `notYetAvailable()` akhirnya kosong — lihat §5).

---

## 5. Item 4 — Izin Meninggalkan Jam Kerja: hapus dari payroll total

**Final**: tidak ada dampak payroll sama sekali (`analisis-payroll-4` §7B.4) — beda dari "belum tersedia" (yang menyiratkan nanti akan ada).

- Hapus `case IZIN_MENINGGALKAN_JAM_KERJA` dari `PayrollComponentEnum` (dan dari `type()`/`label()`).
- Hapus dari `notYetAvailable()` — array itu jadi kosong array `[]` (boleh tetap ada methodnya untuk masa depan kalau ada komponen lain yang perlu status ini).
- `PermissionTypeCategoryEnum::IZIN_MENINGGALKAN_JAM_KERJA` **tetap ada** (sisi absensi, dipakai pengajuan izin & laporan).
- Cek dulu: kalau ada baris `company_payroll_components.component = 'izin_meninggalkan_jam_kerja'` di lokal (kemungkinan besar tidak ada, karena Fase 1 sengaja tidak mendaftarkannya ke Registry — tapi bisa saja admin sempat toggle-nya di UI walau Calculator belum ada), hapus barisnya di migration data yang sama dengan §3.5.

---

## 6. Perubahan Orchestrator — urutan eksekusi (KRITIS, sumber bug diam-diam kalau kelewat)

**Masalah**: `IzinSeharianCalculator` butuh `$context->grossSalary` (T8) **sudah lengkap** sebelum jalan. Tapi orchestrator sekarang (`PayrollService::generatePayroll()`) iterasi `$activeComponents` **tanpa urutan eksplisit** (order dari DB, arbitrary), DAN `FeeClaimCalculator` (earning, always-on) dipanggil **SETELAH** loop `$activeComponents` selesai — jadi kalau Izin Seharian sempat jalan sebelum FeeClaim, T8-nya belum termasuk Fee Klaim Jasa Dokter.

**Perbaikan** — urutan baru di `generatePayroll()`:
```php
$context = new PayrollContext();

// 1. SEMUA earning dulu (toggle company, urutan tidak penting antar-sesama earning)
foreach ($activeComponents->filter(fn ($c) => PayrollComponentEnum::tryFrom($c->component)?->type() === PayrollComponentTypeEnum::EARNING) as $companyComponent) {
    // ... eligibility + resolve + calculate, sama seperti sekarang
}

// 2. Fee Claim (earning, always-on) — SEBELUM deduction, T8 harus include ini
app(FeeClaimCalculator::class)->calculate($user, $payroll, $period, $context);

// 3. Semua deduction KECUALI Izin Seharian
foreach ($activeComponents->filter(fn ($c) => PayrollComponentEnum::tryFrom($c->component)?->type() === PayrollComponentTypeEnum::DEDUCTION && PayrollComponentEnum::tryFrom($c->component) !== PayrollComponentEnum::IZIN_SEHARIAN) as $companyComponent) {
    // ...
}

// 4. Izin Seharian TERAKHIR — T8 (context->grossSalary) sudah final di titik ini
$izinSeharianComponent = $activeComponents->first(fn ($c) => $c->component === PayrollComponentEnum::IZIN_SEHARIAN->value);
if ($izinSeharianComponent && $izinSeharianComponent->isEligibleForEmployeeType(...)) {
    app(IzinSeharianCalculator::class)->calculate($user, $payroll, $period, $context);
}
```
Detail eligibility-check/resolve Calculator (yang di-`// ...`) tetap sama seperti kode existing, cuma dipecah jadi 3 lolongan alih-alih 1 — pertimbangkan extract helper private method `runComponent(CompanyPayrollComponent $companyComponent, User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void` supaya tidak duplikasi 3x blok eligibility+resolve+calculate yang sama.

**PAJAK_JASA_DOKTER** (deduction, always-on, dipanggil lewat `FeeClaimCalculator` yang sama atau terpisah — cek kode existing saat implementasi) — posisinya tidak kritis relatif ke Izin Seharian (tidak saling bergantung), boleh di langkah 2 atau 3, yang penting SETELAH semua earning.

---

## 7. Verifikasi

1. `php artisan migrate` bersih (kalau ada migration data di §3.5/§5).
2. Test unit baru/update (BELUM dibuat — status di planning ini, bukan sudah ada):
   - `AbstractBpjsCalculatorTest` (atau test per concrete class) — kasus: `bpjsBasis` relasi tidak ada sama sekali (skip, 0 baris), `bpjs_basis='ump'`, `bpjs_basis='declared_value'` (sesuai desain final §10.2, BUKAN toggle seperti draft awal §2).
   - `IzinTerlambatPulangCepatCalculatorTest` — kejadian dari 2 kategori berbeda terhitung gabung dalam 1 baris.
   - `IzinSeharianCalculatorTest` — hitung manual 1 kasus (T8 tertentu, hari dalam bulan tertentu, N kejadian) dan cocokkan ke hasil Calculator.
3. **Regenerate manual 1 periode lokal** (`is_paid=false`) — **sudah dilakukan** via transaction+rollback (tinker), tidak ada exception, payroll `is_paid=true` tidak berubah.
4. `php artisan test` — belum dijalankan ulang (di luar permintaan sesi ini).
5. UI Komponen Gaji: komponen "Izin Terlambat Masuk/Pulang Cepat" cuma 1 baris (bukan 2), "Izin Seharian" togglenya sudah tidak disabled, "Izin Meninggalkan Jam Kerja" sudah hilang dari daftar komponen gaji sama sekali — **BELUM diverifikasi manual di browser**, cuma diverifikasi lewat kode (`getComponentsProperty()`/enum). Mapping BPJS per pangkat **belum ada UI-nya sama sekali** (§10.3).

---

## 8. Yang TIDAK termasuk Fase 2

- Lembur (TL/TM Holding) — masih 100% terbuka, tidak disentuh.
- Data riil `pangkat_salaries`/`position_allowances`/dst — tetap kosong, di luar scope ini (itu kerjaan finance/admin lewat UI, bukan migration).
- Deploy ke production — Fase 2 dieksekusi & diverifikasi lokal dulu, gabung ke rencana deploy yang sama dengan Fase 1 (belum ada tanggalnya).

---

## 9. Referensi
- [`knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md`](../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) §7B — keputusan sumber.
- [`planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md`](2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md) — arsitektur Registry+Strategy+Config yang jadi dasar.
- `app/Services/Payroll/Calculators/AbstractBpjsCalculator.php`, `AbstractIzinFlatCalculator.php` — kode existing yang diubah.
- `app/Services/Payroll/PayrollService.php` — orchestrator yang diubah urutannya (§6).

---

## 10. Addendum: Koreksi Desain BPJS Setelah Eksekusi (2026-07-17, lanjutan sesi yang sama)

Setelah §2 dieksekusi persis seperti rencana (toggle `bpjs_basis_berbeda` + field `bpjs_basis` langsung di `PangkatsForm`), user memberi 2 koreksi lanjutan yang **mengubah hasil akhir** — dicatat di sini alih-alih menulis ulang §2, supaya jejak keputusan (kenapa berubah) tetap ada.

### 10.1 `bpjs_basis` dipindah dari kolom `m_pangkats` ke tabel sendiri

**Alasan user**: "masa bpjs_basis gabungnya tabel, padahal beda konteks" — kolom config payroll tidak seharusnya numpang di tabel master (`m_pangkats`), walau relasinya 1:1.

**Perubahan dari §2.3.a/c**:
- `m_pangkats.bpjs_basis` (kolom dari Fase 0) **di-drop**.
- Tabel baru `pangkat_bpjs_bases` (ulid, `pangkat_id` unique FK ke `m_pangkats`, `bpjs_basis` string default `'ump'`) — pola persis `pangkat_salaries` (Fase 0 §2.3).
- Model baru `App\Models\Payroll\PangkatBpjsBasis`, relasi `Pangkats::bpjsBasis(): HasOne`.
- Migration: `database/migrations/2026_07_17_100000_move_bpjs_basis_to_own_table.php` (drop kolom lama + create tabel baru, 1 migration).
- Field `bpjs_basis` **DIHAPUS dari `PangkatsForm`** (form Pangkat biasa) — user eksplisit tidak mau field ini nongol di situ. Rencana: field pengganti muncul khusus di halaman Komponen Gaji (mapping BPJS per pangkat) — **BELUM dibangun, lihat §10.3**.

### 10.2 Toggle `bpjs_basis_berbeda` dihapus total — balik ke skip-otomatis

**Alasan user**: "hilangkan saja pengaturan bpjs_basis_berbeda, karena auto aja kalo misalkan bpjsnya kosong di pangkat tidak ada potongan" — user tidak mau ada toggle company terpisah; cukup 1 aturan simpel: **pangkat yang belum di-mapping = otomatis tidak ada potongan BPJS**, tidak perlu ditanya "berbeda atau tidak".

**Perubahan dari §2.3.a/b**:
- Setting `bpjs_basis_berbeda` **dihapus** — tidak ada di `PayrollComponentEnum::requiredSettings()`, tidak ada baris `settings` (baris lama yang sempat dibuat dihapus manual dari DB lokal).
- `AbstractBpjsCalculator::calculate()` balik sederhana: baca `$user->profile?->pangkat?->bpjsBasis?->bpjs_basis`. Kosong/NULL (pangkat belum di-mapping) → **`return` langsung, 0 baris** (BUKAN fallback UMP seperti §2.3.b). Isi `'ump'` → basis UMP. Isi `'declared_value'` → basis `Setting('bpjs_declared_value', companyId)`.
- Behaviour ini sebenarnya **sama persis** dengan kode asli Fase 1 (skip kalau `bpjs_basis` NULL) — bedanya cuma sumber datanya sekarang dari tabel terpisah (§10.1), bukan kolom `m_pangkats` langsung.

### 10.3 Yang MASIH ditunda — UI mapping per-pangkat

Tabel `pangkat_bpjs_bases` **kosong semua** di lokal (belum ada baris sama sekali, beda dari `bpjs_basis` Fase 0 yang minimal ada kolomnya walau NULL). User eksplisit bilang "ditambahkannya nanti" untuk UI pengisiannya — rencananya muncul di halaman Komponen Gaji (`/keuangan/komponen-gaji`), khusus di bagian expand komponen BPJS (`SalaryComponentsPage.php` + `component-card.blade.php`), berupa daftar semua pangkat company yang dipilih + pilihan basis per baris (`'ump'`/`'declared_value'`), BUKAN di form Pangkat.

**Belum dikerjakan sama sekali** — tidak ada kode Livewire/blade untuk ini. Sampai dibangun, BPJS Kesehatan/Ketenagakerjaan efektif **tidak akan pernah menghasilkan potongan untuk company manapun** (karena semua pangkat otomatis skip), meski toggle komponennya diaktifkan di halaman Komponen Gaji. Ini status yang aman (bukan bug) — cuma perlu diingat saat lanjut sesi berikutnya.

### 10.4 File akhir yang benar-benar berubah (menggantikan daftar §2)
- `database/migrations/2026_07_17_100000_move_bpjs_basis_to_own_table.php` (baru)
- `app/Models/Payroll/PangkatBpjsBasis.php` (baru)
- `app/Models/Master/Pangkats.php` — tambah relasi `bpjsBasis()`, hapus `bpjs_basis` dari `$fillable`
- `app/Services/Payroll/Calculators/AbstractBpjsCalculator.php` — baca dari relasi, skip kalau kosong
- `app/Enum/Payroll/PayrollComponentEnum.php` — `requiredSettings()` BPJS tinggal `['bpjs_declared_value']`
- `database/seeders/PayrollSettingsSeeder.php` — baris `bpjs_basis_berbeda` dihapus dari seeder
- `app/Filament/Resources/Master/Pangkats/Schemas/PangkatsForm.php` — **TIDAK diubah** (field yang sempat ditambah, di-revert lagi sebelum sempat di-commit)
