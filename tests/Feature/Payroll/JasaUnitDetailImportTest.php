<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Imports\Payroll\JasaUnitDetailImport;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\JasaUnitDetail;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Pola test sama BonImportTest - panggil collection() langsung dengan Collection buatan
 * tangan, tanpa file Excel sungguhan.
 */
class JasaUnitDetailImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibleUser(Companies $company, string $nip): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => $nip]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::JASA_UNIT->value, 'employee_type_id' => $employeeType->id]);

        return $user;
    }

    public function test_valid_row_creates_jasa_unit_detail(): void
    {
        $company = Companies::create(['code' => 'JDI1', 'name' => 'RS Import Detail 1', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $user = $this->makeEligibleUser($company, 'NIP001');
        $jasa = JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        $import = new JasaUnitDetailImport($company->id, $period->id, null);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'kode' => 'JU-001', 'nominal' => 150000],
        ]));

        $this->assertSame(1, $import->getSuccessCount());
        $this->assertEmpty($import->getErrors());

        $detail = JasaUnitDetail::where('payroll_period_id', $period->id)
            ->where('user_id', $user->id)
            ->where('jasa_unit_type_id', $jasa->id)
            ->first();
        $this->assertNotNull($detail);
        $this->assertSame(150000.0, (float) $detail->amount);
    }

    public function test_nip_not_found_is_reported(): void
    {
        $company = Companies::create(['code' => 'JDI2', 'name' => 'RS Import Detail 2', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        $import = new JasaUnitDetailImport($company->id, $period->id, null);
        $import->collection(new Collection([
            ['nip' => 'NIP999', 'kode' => 'JU-001', 'nominal' => 150000],
        ]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
        $this->assertSame(0, JasaUnitDetail::count());
    }

    public function test_code_not_found_is_reported(): void
    {
        $company = Companies::create(['code' => 'JDI3', 'name' => 'RS Import Detail 3', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $this->makeEligibleUser($company, 'NIP001');

        $import = new JasaUnitDetailImport($company->id, $period->id, null);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'kode' => 'JU-NOTEXIST', 'nominal' => 150000],
        ]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
    }

    public function test_ineligible_employee_type_is_rejected(): void
    {
        $company = Companies::create(['code' => 'JDI4', 'name' => 'RS Import Detail 4', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'NIP001']);
        $employeeType = EmployeeTypes::create(['name' => 'Magang', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        // Tidak didaftarkan sebagai PayrollComponentRecipient untuk JASA_UNIT - sengaja.
        JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        $import = new JasaUnitDetailImport($company->id, $period->id, null);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'kode' => 'JU-001', 'nominal' => 150000],
        ]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
    }

    public function test_zero_or_negative_amount_is_rejected(): void
    {
        $company = Companies::create(['code' => 'JDI5', 'name' => 'RS Import Detail 5', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $this->makeEligibleUser($company, 'NIP001');
        JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        $import = new JasaUnitDetailImport($company->id, $period->id, null);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'kode' => 'JU-001', 'nominal' => 0],
        ]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
    }

    public function test_reimport_same_combination_updates_existing_amount_instead_of_duplicating(): void
    {
        $company = Companies::create(['code' => 'JDI6', 'name' => 'RS Import Detail 6', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $this->makeEligibleUser($company, 'NIP001');
        JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        (new JasaUnitDetailImport($company->id, $period->id, null))
            ->collection(new Collection([['nip' => 'NIP001', 'kode' => 'JU-001', 'nominal' => 100000]]));

        (new JasaUnitDetailImport($company->id, $period->id, null))
            ->collection(new Collection([['nip' => 'NIP001', 'kode' => 'JU-001', 'nominal' => 250000]]));

        $this->assertSame(1, JasaUnitDetail::where('payroll_period_id', $period->id)->count());
        $this->assertSame(250000.0, (float) JasaUnitDetail::where('payroll_period_id', $period->id)->first()->amount);
    }

    public function test_one_invalid_row_does_not_block_other_valid_rows(): void
    {
        $company = Companies::create(['code' => 'JDI7', 'name' => 'RS Import Detail 7', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $this->makeEligibleUser($company, 'NIP001');
        $this->makeEligibleUser($company, 'NIP002');
        JasaUnitType::create(['company_id' => $company->id, 'name' => 'Jasa Ambulan', 'code' => 'JU-001']);

        $import = new JasaUnitDetailImport($company->id, $period->id, null);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'kode' => 'JU-NOTEXIST', 'nominal' => 100000],
            ['nip' => 'NIP002', 'kode' => 'JU-001', 'nominal' => 200000],
        ]));

        $this->assertSame(1, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
        $this->assertSame(1, JasaUnitDetail::where('payroll_period_id', $period->id)->count());
    }
}
