<?php

namespace Tests\Feature\Payroll;

use App\Enum\OrgStructurLevel;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\PajakGajiPokokExport;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Grouping per divisi + fallback holding/manajemen HARUS sama persis PayrollSlipRekapExport
 * (GroupsPayrollsByDivision, dipakai bareng - 2026-08-22 instruksi user). */
class PajakGajiPokokExportTest extends TestCase
{
    use RefreshDatabase;

    private function makePayroll(Companies $company, PayrollPeriod $period, string $name, ?string $orgStructureId): Payroll
    {
        $user = User::factory()->create(['company_id' => $company->id, 'name' => $name]);
        // Org karyawan diturunkan dari jabatan (kolom org_structure_id di profil sudah dihapus).
        $jobId = $orgStructureId
            ? \App\Models\Master\JobPositions::create(['company_id' => $company->id, 'name' => 'Jabatan ' . $name, 'status' => 'approved', 'org_structure_id' => $orgStructureId])->id
            : null;
        UserProfiles::create(['user_id' => $user->id, 'job_position_id' => $jobId]);

        $payroll = Payroll::create(['employee_id' => $user->employee->id, 'payroll_period_id' => $period->id]);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'amount' => 3_000_000,
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        return $payroll->load(['employee.jobPosition.orgStructure.parent.parent', 'items']);
    }

    public function test_no_division_group_labeled_manajemen_for_regular_company_and_sorts_first(): void
    {
        $company = Companies::create(['code' => '0001', 'name' => 'Regular Co', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $divisi = OrgStructure::create(['name' => 'Keuangan', 'company_id' => $company->id, 'level' => OrgStructurLevel::LEVEL_DIVISION->value]);

        $withDivision = $this->makePayroll($company, $period, 'Budi', $divisi->id);
        $withoutDivision = $this->makePayroll($company, $period, 'Ani', null);

        $rows = (new PajakGajiPokokExport(collect([$withDivision, $withoutDivision]), $company->id))->collection();

        // Baris 0 = header grup pertama, harus "Manajemen" (bukan holding) & di atas "Keuangan".
        $this->assertSame('Manajemen', $rows[0][1]);
        $this->assertSame('Ani', $rows[1][1]);
        $this->assertSame('Keuangan', $rows[2][1]);
        $this->assertSame('Budi', $rows[3][1]);
    }

    public function test_banjarbaru_company_gets_tunjangan_jabatan_and_masa_kerja_columns(): void
    {
        $company = new Companies(['code' => '0001', 'name' => 'RSU Syifa Medika Banjarbaru', 'location' => 'Test', 'is_active' => true]);
        $company->forceFill(['id' => '01k8271xa4nq8cezg3dq9vfr14'])->save();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $payroll = $this->makePayroll($company, $period, 'Budi', null);
        PayrollItem::create(['payroll_id' => $payroll->id, 'name' => 'Tunjangan Jabatan', 'type' => 'earning', 'amount' => 500_000, 'source_type' => PayrollComponentEnum::TUNJANGAN_JABATAN->value]);
        $payroll->load('items');

        $export = new PajakGajiPokokExport(collect([$payroll]), $company->id);

        $this->assertSame(['TUNJANGAN JABATAN', 'TUNJANGAN MASA KERJA'], array_slice($export->headings(), 7));
        $this->assertSame([500000.0, 0.0], array_slice($export->collection()[1], 7));
    }

    public function test_no_division_group_labeled_presiden_direktur_for_holding_company(): void
    {
        $company = Companies::create(['code' => '0000', 'name' => 'Holding Co', 'location' => 'Test', 'is_active' => true]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);

        $payroll = $this->makePayroll($company, $period, 'Presdir', null);

        $rows = (new PajakGajiPokokExport(collect([$payroll]), $company->id))->collection();

        $this->assertSame('Presiden Direktur', $rows[0][1]);
    }
}
