# Planning: Payroll Fase 1 — Registry Rebuild (Enum+Toggle) & Calculator Classes (2026-07-17)

Status: **dieksekusi penuh ke database LOKAL (2026-07-17), termasuk redesign UI Komponen Gaji lanjutan (di luar rencana awal dokumen ini — lihat addendum di `planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md`).** Migration jalan bersih, generate payroll uji (rollback) tervalidasi manual benar. **BELUM di-commit ke git, BELUM di-deploy ke production, `php artisan test` BELUM dijalankan ulang pasca perubahan ini.**

Lanjutan langsung dari [`planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md`](2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md) (Fase 0 — **sudah dieksekusi** ke lokal: `m_kelas.level`, klasifikasi `m_pangkats`, tabel `pangkat_salaries`/`position_allowances`/`transport_allowances`/`tenure_allowances`/`settings` semua sudah ada). Dasar desain: [`analisis-payroll-3`](../knowledge/markdown/analisis-payroll-3-skema-komponen-real.md) (arsitektur Registry+Strategy+Config), [`analisis-payroll-4`](../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) (data Excel + jawaban user), [`analisis-payroll-5`](../knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md) (klasifikasi Enum/Hardcode/Config).

Fase 0 §7 (addendum) sudah memutuskan arahnya: Registry (`salary_components`/`salary_component_assignments`/`salary_component_employee_types`) diganti total jadi `PayrollComponentEnum` + `company_payroll_components`. Fase 1 = **eksekusi keputusan itu** — bangun Calculator per komponen, migrasi skema, dan **wajib deploy sepaket** dengan drop tabel lama (dicatat eksplisit di Fase 0 §7.5: `generatePayroll()` sekarang akan langsung rusak begitu `salary_components` hilang tanpa kode barunya siap).

---

## 1. Prinsip Keamanan (lanjutan dari Fase 0, tetap berlaku)

Semua prinsip Fase 0 §Prinsip Keamanan tetap berlaku, ditambah 2 khusus Fase 1:

1. **`payrolls.is_paid = true` tetap batas suci.** 6 payroll yang sudah `is_paid=true` (dicek di Fase 0) tidak boleh tersentuh — `regeneratePayroll()` sudah otomatis melindungi ini, Fase 1 tidak mengubah invarian itu.
2. **Migration Fase 1 (drop 3 tabel lama + create yang baru) dan deploy kode Calculator baru WAJIB 1 paket, 1 deploy.** Tidak ada window waktu di mana salah satu sudah jalan tapi yang lain belum — kalau perlu, migration & code merge ke `main` dalam 1 PR/commit-set, ditest end-to-end di lokal dulu sebelum ke production.
3. **Regression bukan lewat "bandingkan ke output lama"** — karena payroll belum pernah digenerate ulang sejak redesign 12 Juli (source_type semua NULL, temuan riset attendance sebelumnya), tidak ada "golden output" pasca-redesign buat dibandingkan. Verifikasi Fase 1 pakai **test per-Calculator** (unit, fixture data) + **1 regenerate manual** ke periode `is_paid=false` yang sudah ada di lokal, dicek manual masuk akal (bukan dibandingkan ke angka lama yang memang belum representative).

---

## 2. Scope — Apa yang DIBANGUN vs DITUNDA di Fase 1

Ini bagian terpenting biar Fase 1 tidak "boil the ocean". Berdasar status jawaban di `analisis-payroll-5` §7 (ringkasan) + `analisis-payroll-4` §4:

### 2.1 Dapat Calculator SUNGGUHAN (aturan sudah cukup jelas)

| Komponen | Kejelasan aturan | Sumber data |
|---|---|---|
| `GAJI_POKOK` | ✅ Rumus additive terkonfirmasi (`analisis-payroll-4` §7.3) | `pangkat_salaries`, `m_pangkats.start_kelas_level/class_increment_rate`, `m_kelas.level` |
| `TUNJANGAN_JABATAN` | ✅ Matriks, tinggal lookup | `position_allowances` |
| `TUNJANGAN_TRANSPORTASI` | ✅ Per kelas, tinggal lookup | `transport_allowances` |
| `TUNJANGAN_MASA_KERJA` | ✅ Bracket tahun, tinggal lookup | `tenure_allowances`, `joining_date` |
| `JASA_PELAYANAN` | ✅ Logic existing (`handleServiceFeeComponent`) sudah benar, cuma sumber pool-nya pindah | `job_evaluation_companies` + `service_fee_pools` (baru) |
| `INSENTIF` (Holding) | ✅ Manual input, sudah jalan apa adanya | `incentives` (tidak berubah) |
| `BPJS_KESEHATAN` / `BPJS_KETENAGAKERJAAN` | ✅ Mekanismenya jelas (rate × basis) | `settings` (`bpjs_kesehatan_rate`, dst) + `m_pangkats.bpjs_basis` |
| `KETERLAMBATAN` | ✅ Mekanismenya jelas (grace + denda) | `settings` (`keterlambatan_*`) + `attendance_records` |
| `IZIN_TERLAMBAT_MASUK` / `IZIN_PULANG_CEPAT` | ✅ Potongan flat, tanpa grace | `settings` (`izin_potongan_flat`) + `attendance_requests` |
| `FEE_CLAIM` (jasa dokter, earning) + `PAJAK_JASA_DOKTER` (potongan) | ✅ Logic existing (`handleFeeItemsComponent`) sudah benar, cuma rate pajak pindah dari katalog ke `settings` | `fee_submissions`/`fee_items` (tidak berubah) + `settings` (`pajak_jasa_dokter_rate`, baru) |
| `KOPERASI` / `BON` | ✅ Mekanisme jelas (input manual), belum ada tabelnya | `payroll_manual_lines` (baru) |
| `CUTI` | ✅ Selalu Rp0 (no-op calculator, cuma buat konsistensi provenance) | — |

**Catatan `bpjs_basis`**: mekanisme calculator-nya sudah bisa dibangun sekarang, TAPI datanya (`m_pangkats.bpjs_basis`) masih `NULL` semua (sengaja tidak di-backfill Fase 0 — perlu keputusan manual per pangkat, `analisis-payroll-5` §3.1). Calculator harus **skip aman** (tidak buat baris apa pun) kalau `bpjs_basis` NULL, bukan asumsi default — supaya tidak salah potong sebelum admin isi datanya.

### 2.2 DITUNDA total (jangan bangun Calculator, jangan tebak logic)

| Komponen | Kenapa ditunda |
|---|---|
| `LEMBUR` | Rumus Holding sudah kebaca (Excel), tapi arti "TL"/"TM" masih belum jelas (`analisis-payroll-4` §7.8, user akan tanya ulang) — DAN belum jelas apakah rate 8 company lain pakai `shift_overtime_costs`/`extra_hour_rules` existing atau rumus lain. |
| `IZIN_SEHARIAN` | Rumus Excel yang diberikan user sendiri diakui tidak yakin (`analisis-payroll-4` §7.4) |
| `IZIN_MENINGGALKAN_JAM_KERJA` | User konfirmasi ADA rumus khusus tapi lupa detailnya (`analisis-payroll-4` §7.5) |
| Insentif RSU Banjarbaru | Bukan Calculator — keputusan arsitektur sudah berubah jadi **fitur import** terpisah (`analisis-payroll-4` §7.6), di luar scope Registry/Calculator, dikerjakan sebagai workstream sendiri kalau sudah waktunya. |

Untuk komponen yang ditunda: **case enum-nya tetap didaftarkan** (biar `company_payroll_components` bisa "disiapkan" strukturnya), tapi Registry (peta enum→Calculator class) **belum punya entry buat mereka** — kalau admin coba aktifkan komponen ini di UI toggle, sistem harus tampilkan pesan jelas "belum tersedia", BUKAN error/silent no-op yang membingungkan. Detail di §5.4.

---

## 3. Arsitektur

### 3.1 `PayrollComponentEnum` (final, dikoreksi dari draft Fase 0 §7.1 — tambah 2 case yang kelewat)

Ditemukan waktu audit ulang: draft Fase 0 §7.1 **kelewat 2 case** yang ternyata dipakai kode existing (`handleFeeItemsComponent()`/`getMasterComponent(PAJAK_JASA_DOKTER)`) — `FEE_CLAIM` dan `PAJAK_JASA_DOKTER`. Ditambahkan di sini:

```php
enum PayrollComponentEnum: string
{
    // PENDAPATAN
    case GAJI_POKOK = 'gaji_pokok';
    case TUNJANGAN_JABATAN = 'tunjangan_jabatan';
    case TUNJANGAN_TRANSPORTASI = 'tunjangan_transportasi';
    case TUNJANGAN_MASA_KERJA = 'tunjangan_masa_kerja';
    case INSENTIF = 'insentif';
    case LEMBUR = 'lembur';
    case JASA_PELAYANAN = 'jasa_pelayanan';
    case FEE_CLAIM = 'fee_claim'; // klaim jasa dokter/dst (earning, per kejadian)

    // POTONGAN
    case BPJS_KESEHATAN = 'bpjs_kesehatan';
    case BPJS_KETENAGAKERJAAN = 'bpjs_ketenagakerjaan';
    case KETERLAMBATAN = 'keterlambatan';
    case IZIN_TERLAMBAT_MASUK = 'izin_terlambat_masuk';
    case IZIN_PULANG_CEPAT = 'izin_pulang_cepat';
    case IZIN_SEHARIAN = 'izin_seharian';
    case IZIN_MENINGGALKAN_JAM_KERJA = 'izin_meninggalkan_jam_kerja';
    case CUTI = 'cuti';
    case PAJAK_JASA_DOKTER = 'pajak_jasa_dokter'; // potongan atas FEE_CLAIM
    case KOPERASI = 'koperasi';
    case BON = 'bon';

    public function type(): PayrollComponentTypeEnum { /* sama pola Fase 0 §7.1 */ }
}
```

`FEE_CLAIM`/`PAJAK_JASA_DOKTER` **statusnya beda** dari komponen lain: keduanya **tidak digate lewat `company_payroll_components`** (tidak perlu di-toggle manual admin) — jalannya murni data-driven, sama seperti sekarang (`handleFeeItemsComponent()` selalu jalan, cuma menghasilkan 0 baris kalau tidak ada `fee_items` untuk company/periode itu). Alasannya: fitur ini sudah reliably "self-gating" lewat keberadaan data `fee_submissions`, memaksa toggle manual cuma nambah langkah tanpa manfaat. Dicatat di Registry sebagai **"always-on, tidak butuh entry `company_payroll_components`"**.

### 3.2 `company_payroll_components` (Registry, final — sama draft Fase 0 §7.1, 1 catatan tambahan)

```php
Schema::create('company_payroll_components', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->string('component'); // PayrollComponentEnum, KECUALI fee_claim/pajak_jasa_dokter (§3.1)
    $table->boolean('is_active')->default(true);
    $table->json('eligible_employee_statuses')->nullable();
    $table->timestamps();
    $table->unique(['company_id', 'component']);
});
```

**Catatan `eligible_employee_statuses`**: isinya **ID `m_employee_types`** (ULID), BUKAN enum literal — karena cleanup "status karyawan jadi enum" (`analisis-payroll-5` §7.3) **sengaja belum dieksekusi** (masih "dicatat dulu", nunggu keputusan Freelance/Outsourcing itu data usang atau kategori riil). Kalau nanti cleanup itu jalan, kolom ini ikut menyesuaikan — **tidak dicampur ke Fase 1** ini supaya scope tidak melebar ke 2 keputusan besar sekaligus.

### 3.3 Tabel baru pendukung

```php
// Pool "Total Jaspel" bulanan (Fase 0 §7.3.6) — angka berubah tiap periode, beda dari config stabil.
Schema::create('service_fee_pools', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
    $table->decimal('total_amount', 15, 2);
    $table->timestamps();
    $table->unique(['company_id', 'payroll_period_id']);
});

// Koperasi & Bon — input manual per user per periode, tidak ada rumus otomatis sama sekali.
Schema::create('payroll_manual_lines', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('component'); // 'koperasi' | 'bon'
    $table->decimal('amount', 15, 2);
    $table->text('note')->nullable();
    $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### 3.4 `Setting` baru yang perlu ditambah ke seeder (`PayrollSettingsSeeder`, Fase 0)

| Key | Type | Alasan |
|---|---|---|
| `pajak_jasa_dokter_rate` | decimal | Menggantikan lookup `salary_components` category=`pajak_jasa_dokter` di `handleFeeItemsComponent()`. |

(11 key lain yang sudah di-seed Fase 0 tetap dipakai apa adanya — `ump`, `bpjs_kesehatan_rate`, `bpjs_ketenagakerjaan_rate`, `bpjs_declared_value`, `keterlambatan_*`, `izin_potongan_flat`, `lembur_minimum_jam` (dipakai nanti kalau Lembur sudah tidak ditunda), `izin_max_per_periode` (attendance, tidak dipakai payroll).)

### 3.5 Kontrak Calculator

```php
interface PayrollComponentCalculator
{
    /**
     * Hitung & buat PayrollItem (0 atau lebih baris) untuk 1 user, 1 periode.
     * TIDAK boleh menyentuh $payroll->gross_salary/total_deductions langsung — orchestrator
     * yang menjumlahkan dari baris PayrollItem yang dibuat (single source of truth).
     */
    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void;
}
```

```php
/**
 * State yang di-share antar-Calculator dalam 1 generate — dipakai komponen yang butuh
 * cross-reference hasil komponen lain (mis. Izin Seharian butuh total pendapatan — nanti
 * kalau sudah tidak ditunda). Dibangun sekarang walau baru dipakai minimal, biar urutan
 * eksekusi (pendapatan dulu baru potongan yang cross-reference) sudah punya tempatnya.
 */
class PayrollContext
{
    public float $grossSalary = 0.0;
    public float $totalDeductions = 0.0;

    public function addEarning(float $amount): void { $this->grossSalary += $amount; }
    public function addDeduction(float $amount): void { $this->totalDeductions += $amount; }
}
```

**Perubahan penting dari `PayrollService` lama**: sekarang `$grossSalary`/`$totalDeductions` **tidak lagi jadi parameter reference (`&$grossSalary`) yang dioper ke tiap handler** — itu pola yang gampang salah (lupa nambah di 1 handler = angka total meleset diam-diam). `PayrollContext` jadi 1 object yang di-pass, tiap Calculator manggil `$context->addEarning()`/`addDeduction()` eksplisit, orchestrator baca total dari situ di akhir. Ini simplifikasi tambahan yang ditemukan pas nulis planning ini, bukan bagian rencana awal — dicatat karena mengurangi 1 kelas bug yang sudah pernah kejadian pola serupa di codebase (referensi: parameter `&$grossSalary` di 6+ method `handleXxxComponent()` lama).

### 3.6 Registry (peta enum → Calculator, Layer 1→2 wiring)

```php
class PayrollCalculatorRegistry
{
    private const MAP = [
        PayrollComponentEnum::GAJI_POKOK->value => GajiPokokCalculator::class,
        PayrollComponentEnum::TUNJANGAN_JABATAN->value => TunjanganJabatanCalculator::class,
        PayrollComponentEnum::TUNJANGAN_TRANSPORTASI->value => TunjanganTransportasiCalculator::class,
        PayrollComponentEnum::TUNJANGAN_MASA_KERJA->value => TunjanganMasaKerjaCalculator::class,
        PayrollComponentEnum::JASA_PELAYANAN->value => JasaPelayananCalculator::class,
        PayrollComponentEnum::INSENTIF->value => IncentiveCalculator::class,
        PayrollComponentEnum::BPJS_KESEHATAN->value => BpjsKesehatanCalculator::class,
        PayrollComponentEnum::BPJS_KETENAGAKERJAAN->value => BpjsKetenagakerjaanCalculator::class,
        PayrollComponentEnum::KETERLAMBATAN->value => KeterlambatanCalculator::class,
        PayrollComponentEnum::IZIN_TERLAMBAT_MASUK->value => IzinTerlambatMasukCalculator::class,
        PayrollComponentEnum::IZIN_PULANG_CEPAT->value => IzinPulangCepatCalculator::class,
        PayrollComponentEnum::KOPERASI->value => ManualLineCalculator::class,
        PayrollComponentEnum::BON->value => ManualLineCalculator::class,
        PayrollComponentEnum::CUTI->value => NoopCalculator::class,
        // LEMBUR, IZIN_SEHARIAN, IZIN_MENINGGALKAN_JAM_KERJA sengaja TIDAK didaftarkan (§2.2)
    ];

    public static function resolve(PayrollComponentEnum $component): ?string
    {
        return self::MAP[$component->value] ?? null; // null = belum ada Calculator (§2.2)
    }
}
```

### 3.7 Orchestrator (`PayrollService::generatePayroll()` setelah refactor)

```php
public function generatePayroll(PayrollPeriod $period): void
{
    DB::transaction(function () use ($period) {
        $users = User::where('company_id', $period->company_id)->where('is_active', true)->whereNull('deleted_at')->get();
        $activeComponents = CompanyPayrollComponent::where('company_id', $period->company_id)->where('is_active', true)->get();

        foreach ($users as $user) {
            if ($this->payrollAlreadyExists($user, $period)) continue;

            $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'is_paid' => false]);
            $context = new PayrollContext();

            foreach ($activeComponents as $companyComponent) {
                $component = PayrollComponentEnum::from($companyComponent->component);
                if (! $this->isEligibleForEmployeeStatus($companyComponent, $user)) continue;

                $calculatorClass = PayrollCalculatorRegistry::resolve($component);
                if (! $calculatorClass) continue; // belum ada Calculator (§2.2), skip aman

                app($calculatorClass)->calculate($user, $payroll, $period, $context);
            }

            // FEE_CLAIM/PAJAK_JASA_DOKTER — always-on, di luar loop Registry (§3.1)
            app(FeeClaimCalculator::class)->calculate($user, $payroll, $period, $context);

            $payroll->update([
                'gross_salary' => $context->grossSalary,
                'total_deductions' => $context->totalDeductions,
                'net_salary' => $context->grossSalary - $context->totalDeductions,
            ]);
        }

        $period->update(['processed_at' => now()]);
    });
}
```

Turun dari **~840 baris jadi orchestrator tipis** (~30 baris) — logika bisnis pindah semua ke Calculator masing-masing (Layer 2), tiap file bisa dites sendiri.

---

## 4. Migration & Urutan Eksekusi (1 paket, sesuai Prinsip Keamanan §1.2)

1. `create_company_payroll_components_table`
2. `create_service_fee_pools_table`
3. `create_payroll_manual_lines_table`
4. Tambah `settings` row `pajak_jasa_dokter_rate` (lewat update `PayrollSettingsSeeder`, dijalankan manual — pola sama Fase 0)
5. `payroll_items` — drop kolom `calculation_type` (keputusan user, sesi sebelumnya)
6. `drop_salary_components_and_assignments_tables` — drop `salary_components`, `salary_component_assignments`, `salary_component_employee_types` (urutan aman FK: pivot dulu, assignment, baru katalog)
7. **Bareng di commit yang sama**: semua Calculator class, `PayrollCalculatorRegistry`, `PayrollContext`, refactor `PayrollService::generatePayroll()`, hapus `SalaryComponentCategoryEnum`/`CalculationTypeEnum`/`PayrollItemSourceEnum` (dead code, dicek ulang tidak ada pemakai tersisa sebelum dihapus — pola sama Fase 0 §7.3), hapus `Companies::selectedSalaryComponent()`, hapus model `SalaryComponents`/`SalaryComponentAssignments`.
8. Hapus Filament Resource lama: `app/Filament/Resources/SalaryComponents/`, `app/Filament/Resources/SalaryComponentAssignments/` — ganti Resource baru **super simpel** (§6).
9. `shield:generate` (permission Resource baru/hilang berubah).

**Test lokal dulu (§7) sebelum PR dibuka** — bukan cuma "migrate jalan tanpa error", tapi generate ulang 1 payroll period lokal dan cek hasilnya masuk akal.

---

## 5. Detail per Calculator (ringkas — kontrak lengkap ditulis saat implementasi, bukan di planning)

### 5.1 `GajiPokokCalculator`
```
steps = user.kelas.level - pangkat.start_kelas_level
gajiPokok = pangkatSalary.base_salary * (1 + pangkat.class_increment_rate * max(0, steps))
```
Skip (0 baris) kalau `pangkat_salaries`/`m_pangkats.start_kelas_level`/`m_kelas.level` ada yang NULL — jangan asumsikan 0 sebagai default yang "aman", karena Gaji Pokok Rp0 yang ke-generate diam-diam itu bug serius (beda dari komponen tunjangan yang wajar Rp0 kalau tidak eligible).

### 5.2 `TunjanganJabatanCalculator` / `TunjanganTransportasiCalculator` / `TunjanganMasaKerjaCalculator`
Lookup langsung ke tabel matriks/bracket masing-masing (`position_allowances` by pangkat_id+kelas_id, `transport_allowances` by kelas_id, `tenure_allowances` by company_id+tahun kerja dari `joining_date`). Tidak ketemu baris = skip (0 baris), bukan error — wajar kalau finance belum isi data buat kombinasi tertentu.

### 5.3 `BpjsKesehatanCalculator` / `BpjsKetenagakerjaanCalculator`
```
basis = pangkat.bpjs_basis === 'ump' ? Setting::resolve('ump', companyId)
      : (pangkat.bpjs_basis === 'declared_value' ? Setting::resolve('bpjs_declared_value', companyId) : null)
skip kalau basis null (bpjs_basis belum diisi ATAU setting belum diisi)
amount = basis * Setting::resolve('bpjs_kesehatan_rate'/'bpjs_ketenagakerjaan_rate', companyId) / 100
```

### 5.4 UI kalau admin coba aktifkan komponen yang belum punya Calculator
Di Resource toggle baru (§6), komponen `LEMBUR`/`IZIN_SEHARIAN`/`IZIN_MENINGGALKAN_JAM_KERJA` **tetap muncul di daftar** (biar admin tahu itu ada), tapi toggle-nya **disabled** + helper text "Belum tersedia — menunggu konfirmasi aturan perhitungan" (bukan disembunyikan total, biar tidak terkesan fitur hilang).

---

## 6. UI — Resource Baru Menggantikan yang Lama

Sesuai Fase 0 §7.3.1: `SalaryComponentResource`, `SalaryComponentAssignmentResource` (admin) dihapus total. Portal `SalaryComponentsPage` (yang sekarang masih baca `SalaryComponents` model lama) **juga harus diganti** di Fase 1 ini — kalau tidak, halaman itu langsung 500 error begitu tabel lama di-drop.

**Bentuk baru** (both admin `/admin/company-payroll-components` dan portal `/keuangan/komponen-gaji` reuse Table/Form yang sama, pola sama `Setting`/`HolidaysPage` sebelumnya):
- 1 halaman per company (atau filter company kalau admin holding), list semua `PayrollComponentEnum::cases()` — toggle aktif/nonaktif per komponen, badge earning/deduction dari `type()`, kolom "Tipe Karyawan Eligible" (multi-select dari `m_employee_types` company itu).
- Baris komponen yang belum punya Calculator (§5.4) — toggle disabled.
- **Bukan CRUD** (tidak ada create/delete row manual) — barisnya otomatis dari `PayrollComponentEnum::cases()` × company yang login, `company_payroll_components` cuma nyimpen status toggle-nya (auto-create row kalau belum ada saat pertama toggle).

Detail desain visual & interaksi didetailkan saat implementasi (bukan di planning ini) — cukup tahu bentuknya "checklist per company", bukan drilldown 2 halaman seperti Resource lama.

---

## 7. Verifikasi

1. `php artisan migrate` bersih di lokal (7 migration di §4).
2. Test per-Calculator (fixture data, PHPUnit) — minimal: `GajiPokokCalculatorTest` (additive formula, termasuk kasus `steps=0` dan NULL-safety), `BpjsKesehatanCalculatorTest` (2 basis + skip kalau NULL), `KeterlambatanCalculatorTest` (grace count, termasuk kasus tepat di batas).
3. **Regenerate manual 1 periode `is_paid=false`** yang sudah ada di lokal (dari 70 yang tersedia) — bandingkan JUMLAH baris & masuk-akal-nya angka (bukan ke angka lama yang tidak representative, ke ekspektasi manual dari data pangkat/kelas user itu).
4. Cek 6 payroll `is_paid=true` **tidak berubah sama sekali** (checksum/snapshot `payroll_items` sebelum & sesudah migration, harus identik).
5. Toggle 1 komponen di UI baru untuk 1 company test → generate payroll → baris muncul di slip. Nonaktifkan → generate lagi (periode beda) → baris tidak muncul.
6. `/admin` — pastikan tidak ada Resource lama yang masih ke-reference (nav rapi, tidak ada link mati ke `salary-components`/`salary-component-assignments`).
7. Cek `/admin/roles` — permission Resource baru muncul setelah `shield:generate`.

---

## 8. Yang SENGAJA Tidak Dikerjakan di Fase 1

- `LEMBUR`, `IZIN_SEHARIAN`, `IZIN_MENINGGALKAN_JAM_KERJA` — Calculator-nya, sampai jawaban Q1/Q6/Q2 (`analisis-payroll-3` §8, `analisis-payroll-4` §7) ada.
- Fitur import Insentif RSU — workstream terpisah (`analisis-payroll-4` §7.6).
- Cleanup `m_employee_types` jadi enum — item terpisah, `eligible_employee_statuses` tetap pakai ID existing (§3.2).
- Override `settings` per company lewat UI — ditunda sesuai keputusan user (sesi sebelumnya).
- Extend `m_leave_types` (cuti melahirkan/keguguran/haid) & fix bug reversal saldo cuti — item attendance terpisah, di luar scope "payroll registry rebuild".
- Kategori company (hospital/non) — belum ditanya (Q10), tidak menghalangi Fase 1 karena gating Jasa Pelayanan/Fee Claim sekarang cukup lewat toggle `company_payroll_components` manual per company (tidak butuh kategori otomatis).

---

## 9. Referensi

- [`planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md`](2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md) §7 — keputusan Registry rebuild awal.
- [`knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md`](../knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md) — klasifikasi Enum/Hardcode/Config, tabel `settings`.
- `app/Services/Payroll/PayrollService.php` — kode existing yang direfactor (baca full sebelum implementasi, terutama `handleFeeItemsComponent()`/`getMasterComponent()` yang jadi rujukan §3.1/§3.4).
- `app/Models/Payroll/{SalaryComponentAssignments,FeeItem}.php`, `app/Models/SalaryComponent/SalaryComponents.php` — model yang di-drop.
