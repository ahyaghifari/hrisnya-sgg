<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\PangkatSalary;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Baris payroll_items yang edited_manually=true (hasil EditAction/Add Adjustment di halaman
 * detail payroll) HARUS dibawa pindah apa adanya ke payroll baru pas Generate/Regenerate,
 * Calculator komponen itu WAJIB di-skip - lihat PayrollService::generateForUser()/runComponent()
 * dan PayrollContext::protectedSourceTypes.
 */
class PayrollManualAdjustmentSurvivesRegenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_regenerate_preserves_edited_gaji_pokok_and_manual_adjustment_rows(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        // Kalkulator otomatis akan hasilkan 3.100.000 kalau TIDAK di-skip - dipakai buat
        // membuktikan proteksinya benar-benar mencegah Calculator jalan, bukan kebetulan sama.
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_100_000]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $employeeType->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5_000_000, 'total_deductions' => 0, 'net_salary' => 5_000_000]);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok (override HR)',
            'type' => 'earning',
            'quantity' => 1,
            'amount' => 5_000_000,
            'note' => 'override manual',
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
            'edited_manually' => true,
        ]);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Bonus proyek',
            'type' => 'earning',
            'quantity' => 1,
            'amount' => 250_000,
            'note' => 'adjustment manual',
            'source_type' => 'manual',
            'edited_manually' => true,
        ]);

        $newPayroll = app(PayrollService::class)->regenerateSinglePayroll($payroll);

        $gajiPokokItem = $newPayroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->first();
        $manualItem = $newPayroll->items()->where('source_type', 'manual')->first();

        // Bukan 3.100.000 (hasil Calculator) - override manualnya yang harus tetap dipakai.
        $this->assertSame(5_000_000.0, (float) $gajiPokokItem->amount);
        $this->assertTrue((bool) $gajiPokokItem->edited_manually);
        $this->assertSame(1, $newPayroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->count());

        $this->assertNotNull($manualItem);
        $this->assertSame(250_000.0, (float) $manualItem->amount);
        $this->assertTrue((bool) $manualItem->edited_manually);

        $this->assertSame(5_250_000.0, (float) $newPayroll->fresh()->gross_salary);
    }

    /**
     * regeneratePayroll(periode) DULU bulk-delete semua payroll periode itu SEBELUM
     * capturePreservedItems() sempat jalan per user - baris edited_manually ikut lenyap tanpa
     * dibawa pindah (beda perilaku dari regenerateSinglePayroll() yang capture dulu baru
     * delete). Dibenarkan 2026-08-22 - test ini mengunci baris berkat capturePreservedItems()
     * dibaca sebelum payroll lama dihapus di loop generatePayroll(), bukan bulk-delete duluan.
     */
    public function test_period_level_regenerate_also_preserves_edited_gaji_pokok_row(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        Setting::create(['company_id' => $company->id, 'key' => 'gaji_pokok_kenaikan_per_kelas', 'module' => 'payroll', 'type' => 'decimal', 'value' => '0', 'label' => 'Kenaikan Gaji Pokok per Kelas']);

        $kelas1 = Kelas::create(['name' => 'Kelas I', 'company_id' => $company->id, 'level' => 1]);
        $pangkat = Pangkats::create(['name' => 'Perawat', 'company_id' => $company->id, 'start_kelas_id' => $kelas1->id, 'max_kelas_id' => $kelas1->id]);
        PangkatSalary::create(['pangkat_id' => $pangkat->id, 'base_salary' => 3_100_000]);

        $employeeType = EmployeeTypes::create(['name' => 'Tetap', 'company_id' => $company->id]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        PayrollComponentRecipient::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'employee_type_id' => $employeeType->id]);

        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::create(['user_id' => $user->id, 'pangkat_id' => $pangkat->id, 'kelas_id' => $kelas1->id, 'employee_type_id' => $employeeType->id]);

        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'gross_salary' => 5_000_000, 'total_deductions' => 0, 'net_salary' => 5_000_000]);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok (override HR)',
            'type' => 'earning',
            'quantity' => 1,
            'amount' => 5_000_000,
            'note' => 'override manual',
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
            'edited_manually' => true,
        ]);

        app(PayrollService::class)->regeneratePayroll($period);

        $newPayroll = Payroll::where('user_id', $user->id)->where('payroll_period_id', $period->id)->firstOrFail();
        $gajiPokokItem = $newPayroll->items()->where('source_type', PayrollComponentEnum::GAJI_POKOK->value)->first();

        $this->assertNotNull($gajiPokokItem);
        // Bukan 3.100.000 (hasil Calculator) - override manualnya harus tetap dipakai.
        $this->assertSame(5_000_000.0, (float) $gajiPokokItem->amount);
        $this->assertTrue((bool) $gajiPokokItem->edited_manually);
    }
}
