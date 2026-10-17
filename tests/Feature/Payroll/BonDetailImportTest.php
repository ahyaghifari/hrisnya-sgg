<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Imports\Payroll\BonDetailImport;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\Calculators\BonCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * BonDetailImport cuma berlaku buat setting `bon_metode` = 'detail' - company mode 'simple'
 * ditolak (kebalikan dari BonImportTest). Beda dari BonImport: kunci upsert ikut `label`
 * (nama_potongan), jadi 1 karyawan boleh punya banyak baris bernama beda, tapi NIP + nama
 * potongan yang SAMA menimpa nominal (bukan dobel baris) - lihat planning/2026-08-05-payroll-bon-detail-import-nama-potongan.md.
 */
class BonDetailImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyUserPayroll(string $nip): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => $nip]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BON->value, 'employee_type_id' => $employeeType->id]);
        Setting::create(['company_id' => $company->id, 'key' => 'bon_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'detail', 'label' => 'Metode Bon']);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        return [$company, $user, $period, $payroll];
    }

    public function test_creates_separate_lines_per_named_deduction(): void
    {
        [, , $period] = $this->makeCompanyUserPayroll('NIP001');

        $import = new BonDetailImport($period->id);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'nama_potongan' => 'Angsuran Motor', 'nominal' => 300000],
            ['nip' => 'NIP001', 'nama_potongan' => 'Titipan Koperasi', 'nominal' => 200000],
        ]));

        $lines = PayrollManualLine::where('payroll_period_id', $period->id)->where('component', 'bon')->get();

        $this->assertCount(2, $lines);
        $this->assertSame(['Angsuran Motor', 'Titipan Koperasi'], $lines->pluck('label')->sort()->values()->all());
    }

    public function test_reimporting_same_nip_and_label_overwrites_amount_not_duplicates(): void
    {
        [, , $period] = $this->makeCompanyUserPayroll('NIP001');

        (new BonDetailImport($period->id))->collection(new Collection([
            ['nip' => 'NIP001', 'nama_potongan' => 'Angsuran Motor', 'nominal' => 300000],
        ]));

        (new BonDetailImport($period->id))->collection(new Collection([
            ['nip' => 'NIP001', 'nama_potongan' => 'Angsuran Motor', 'nominal' => 350000],
        ]));

        $lines = PayrollManualLine::where('payroll_period_id', $period->id)->where('component', 'bon')->get();

        $this->assertCount(1, $lines);
        $this->assertSame(350000.0, (float) $lines->first()->amount);
    }

    public function test_simple_mode_company_rejects_import(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'NIP001']);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);
        // Sengaja TIDAK set bon_metode -> default 'simple'.

        $import = new BonDetailImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP001', 'nama_potongan' => 'Angsuran Motor', 'nominal' => 100000]]));

        $this->assertSame(0, PayrollManualLine::where('payroll_period_id', $period->id)->count());
        $this->assertNotEmpty($import->getErrors());
    }

    public function test_empty_nama_potongan_is_rejected(): void
    {
        [, , $period] = $this->makeCompanyUserPayroll('NIP001');

        $import = new BonDetailImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP001', 'nama_potongan' => '', 'nominal' => 100000]]));

        $this->assertSame(0, PayrollManualLine::where('payroll_period_id', $period->id)->count());
        $this->assertNotEmpty($import->getErrors());
    }

    public function test_imported_label_becomes_payroll_item_name(): void
    {
        [, $user, $period, $payroll] = $this->makeCompanyUserPayroll('NIP001');

        (new BonDetailImport($period->id))->collection(new Collection([
            ['nip' => 'NIP001', 'nama_potongan' => 'Angsuran Motor', 'nominal' => 300000],
        ]));

        $context = new PayrollContext();
        (new BonCalculator())->calculate($user, $payroll, $period, $context);

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'name' => 'Angsuran Motor',
            'amount' => 300000,
        ]);
    }
}
