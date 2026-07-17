<?php

namespace Tests\Feature\Payroll;

use App\Imports\Payroll\IncentiveImport;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Insentif RSU (metode "import", lihat planning insentif Fase 3) — ditempel ke Payroll yang
 * SUDAH digenerate lewat NIP. Idempotent per (payroll, NIP): re-import mengganti nominal
 * sebelumnya, bukan menambah baris baru (keputusan eksplisit user, beda dari EmployeeImport
 * yang additive-by-nature untuk field profil).
 */
class IncentiveImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyUserPayroll(string $nip): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => $nip]);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'processed_at' => now()]);
        $payroll = Payroll::create(['user_id' => $user->id, 'payroll_period_id' => $period->id, 'gross_salary' => 3_000_000, 'net_salary' => 3_000_000]);

        return [$company, $user, $period, $payroll];
    }

    public function test_creates_payroll_item_and_increments_totals(): void
    {
        [, , $period, $payroll] = $this->makeCompanyUserPayroll('NIP001');

        $import = new IncentiveImport($period->id);
        $import->collection(new Collection([
            ['nip' => 'NIP001', 'nominal' => 500000],
        ]));

        $this->assertSame(1, $import->getSuccessCount());
        $this->assertSame([], $import->getErrors());
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'insentif',
            'amount' => 500000,
        ]);
        $payroll->refresh();
        $this->assertSame(3_500_000.0, (float) $payroll->gross_salary);
        $this->assertSame(3_500_000.0, (float) $payroll->net_salary);
    }

    public function test_reimport_replaces_previous_amount_instead_of_adding(): void
    {
        [, , $period, $payroll] = $this->makeCompanyUserPayroll('NIP001');
        $import = new IncentiveImport($period->id);

        $import->collection(new Collection([['nip' => 'NIP001', 'nominal' => 500000]]));
        $import->collection(new Collection([['nip' => 'NIP001', 'nominal' => 800000]]));

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payroll->id,
            'source_type' => 'insentif',
            'amount' => 800000,
        ]);
        $this->assertSame(1, \App\Models\Payroll\PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'insentif')->count());

        $payroll->refresh();
        $this->assertSame(3_800_000.0, (float) $payroll->gross_salary);
    }

    public function test_unknown_nip_is_skipped_and_reported(): void
    {
        [, , $period] = $this->makeCompanyUserPayroll('NIP001');

        $import = new IncentiveImport($period->id);
        $import->collection(new Collection([['nip' => 'TIDAK-ADA', 'nominal' => 500000]]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('TIDAK-ADA', $import->getErrors()[0]);
    }

    public function test_nip_without_payroll_in_period_is_skipped_and_reported(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'NIP002']);
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'processed_at' => now()]);
        // Sengaja tidak bikin Payroll untuk user ini di periode ini.

        $import = new IncentiveImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP002', 'nominal' => 500000]]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('belum ada payroll', $import->getErrors()[0]);
    }

    public function test_paid_payroll_is_skipped_and_reported(): void
    {
        [, , $period, $payroll] = $this->makeCompanyUserPayroll('NIP001');
        $payroll->update(['is_paid' => true]);

        $import = new IncentiveImport($period->id);
        $import->collection(new Collection([['nip' => 'NIP001', 'nominal' => 500000]]));

        $this->assertSame(0, $import->getSuccessCount());
        $this->assertDatabaseMissing('payroll_items', ['payroll_id' => $payroll->id, 'source_type' => 'insentif']);
    }
}
