<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Imports\Payroll\BonImport;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * BonImport cuma berlaku buat setting `bon_metode` = 'simple' (default) - company mode 'detail'
 * ditolak (lihat docblock BonImport.php), harus tambah rincian Bon satu-satu lewat halaman
 * detail payroll. Gate utama ada di PayrollsPage::importGuardPasses() (tombol disembunyikan),
 * validateRow() di sini lapis kedua kalau class dipanggil langsung.
 */
class BonImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyUserPayroll(string $nip): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => $nip]);
        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::BON->value, 'employee_type_id' => $employeeType->id]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = Payroll::create(['user_id' => $user->id, 'employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);

        return [$company, $user, $period, $payroll];
    }

    public function test_simple_mode_re_import_replaces_existing_line(): void
    {
        [, , $period, $payroll] = $this->makeCompanyUserPayroll('NIP001');

        $import = new BonImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP001', 'nominal' => 100000]]));

        $import = new BonImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP001', 'nominal' => 250000]]));

        // Mode Simple sekarang nulis LANGSUNG ke payroll_items (bukan payroll_manual_lines lagi,
        // 2026-08-22) - update-in-place lewat applyManualRevision(), bukan baris baru.
        $items = $payroll->items()->where('source_type', 'bon')->get();

        $this->assertCount(1, $items);
        $this->assertSame(250000.0, (float) $items->first()->amount);
        $this->assertTrue((bool) $items->first()->edited_manually);
    }

    public function test_detail_mode_rejects_import_entirely(): void
    {
        [$company, , $period, $payroll] = $this->makeCompanyUserPayroll('NIP001');
        Setting::create(['company_id' => $company->id, 'key' => 'bon_metode', 'module' => 'payroll', 'type' => 'string', 'value' => 'detail', 'label' => 'Metode Bon']);

        $import = new BonImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP001', 'nominal' => 100000]]));

        $this->assertSame(0, $payroll->items()->where('source_type', 'bon')->count());
        $this->assertNotEmpty($import->getErrors());
    }
}
