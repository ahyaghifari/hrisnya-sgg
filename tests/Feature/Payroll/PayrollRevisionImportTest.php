<?php

namespace Tests\Feature\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\PayrollSlipRekapExport;
use App\Imports\Payroll\PayrollRevisionImport;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Diuji lewat DB sungguhan (bukan setRelation manual seperti PayrollSlipRekapExportTest) karena
 * PayrollRevisionImport & PayrollService::applyManualRevision query User/Payroll langsung dari
 * database (cocokkan NIP, cek is_paid, dst) - bukan cuma transformasi array di memori.
 */
class PayrollRevisionImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithGajiPokokActive(): Companies
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'PT Uji', 'location' => 'Banjarbaru']);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::GAJI_POKOK->value, 'is_active' => true]);
        CompanyPayrollComponent::create(['company_id' => $company->id, 'component' => PayrollComponentEnum::TUNJANGAN_JABATAN->value, 'is_active' => true]);

        return $company;
    }

    private function makePayrollWithItem(Companies $company, string $nip, bool $isPaid = false): Payroll
    {
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => now(), 'end_date' => now(), 'payment_date' => now()]);
        $user = User::create(['name' => 'Karyawan Uji', 'email' => "{$nip}@test.local", 'password' => bcrypt('secret'), 'nip' => $nip, 'company_id' => $company->id, 'is_active' => true]);

        $payroll = Payroll::create([
            'user_id' => $user->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5_000_000,
            'total_deductions' => 0,
            'net_salary' => 5_000_000,
            'is_paid' => $isPaid,
        ]);

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'quantity' => 1,
            'amount' => 5_000_000,
            'source_type' => PayrollComponentEnum::GAJI_POKOK->value,
        ]);

        return $payroll->fresh(['payrollPeriod', 'user']);
    }

    /** Bikin baris file dari header PERSIS hasil export - persis skenario nyata (user upload
     *  ulang file hasil Export Excel), bukan header buatan sendiri. */
    private function buildFileRows(Companies $company, array $overridesByLabel): Collection
    {
        $headings = (new PayrollSlipRekapExport(collect(), $company->id))->headings();
        $row = array_fill(0, count($headings), '');

        foreach ($overridesByLabel as $label => $value) {
            $index = array_search($label, $headings, true);
            $row[$index] = $value;
        }

        return collect([collect($headings), collect($row)]);
    }

    public function test_import_detects_diff_for_editable_column_matched_by_nip(): void
    {
        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123');

        $rows = $this->buildFileRows($company, ['ID' => '00123', 'Gaji Pokok' => 6_000_000]);

        $import = new PayrollRevisionImport($payroll->payrollPeriod);
        $import->collection($rows);

        $this->assertSame([], $import->getErrors());
        $diffs = $import->getDiffs();
        $this->assertCount(1, $diffs);
        $this->assertSame($payroll->id, $diffs[0]['payroll_id']);
        $this->assertSame('gaji_pokok', $diffs[0]['component']);
        $this->assertSame(5_000_000.0, $diffs[0]['old']);
        $this->assertSame(6_000_000.0, $diffs[0]['new']);
    }

    public function test_import_ignores_readonly_columns_even_if_present_in_file(): void
    {
        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123');

        // "TOTAL GAJI" itu kolom hasil hitungan (read-only) - biarpun dibawa file & nilainya
        // beda dari yang seharusnya, TIDAK boleh jadi diff yang bisa "diterapkan".
        $rows = $this->buildFileRows($company, ['ID' => '00123', 'TOTAL GAJI' => 99_999_999]);

        $import = new PayrollRevisionImport($payroll->payrollPeriod);
        $import->collection($rows);

        $this->assertSame([], $import->getDiffs());
    }

    /** Presiden Direktur/Sekretaris Direktur (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS) dibuang dari
     * User::query() lewat global scope 'non_karyawan' - import revisi harus tetap bisa
     * mencocokkan NIP mereka (instruksi user 2026-08-20), bukan dilaporkan "tidak ditemukan". */
    public function test_import_matches_executive_nip_excluded_from_default_user_scope(): void
    {
        $company = $this->makeCompanyWithGajiPokokActive();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => now(), 'end_date' => now(), 'payment_date' => now()]);

        $execId = User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS[0];
        $exec = User::create(['id' => $execId, 'name' => 'Presiden Direktur Uji', 'email' => 'presdir@test.local', 'password' => bcrypt('secret'), 'nip' => '00001', 'company_id' => $company->id, 'is_active' => true]);

        $payroll = Payroll::create([
            'user_id' => $exec->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5_000_000,
            'total_deductions' => 0,
            'net_salary' => 5_000_000,
        ]);
        PayrollItem::create(['payroll_id' => $payroll->id, 'name' => 'Gaji Pokok', 'type' => 'earning', 'quantity' => 1, 'amount' => 5_000_000, 'source_type' => PayrollComponentEnum::GAJI_POKOK->value]);

        $rows = $this->buildFileRows($company, ['ID' => '00001', 'Gaji Pokok' => 9_000_000]);

        $import = new PayrollRevisionImport($period);
        $import->collection($rows);

        $this->assertSame([], $import->getErrors());
        $this->assertCount(1, $import->getDiffs());
        $this->assertSame('00001', $import->getDiffs()[0]['nip']);
        $this->assertSame(9_000_000.0, $import->getDiffs()[0]['new']);
    }

    public function test_import_reports_error_for_unknown_nip(): void
    {
        $company = $this->makeCompanyWithGajiPokokActive();
        $period = PayrollPeriod::create(['company_id' => $company->id, 'start_date' => now(), 'end_date' => now(), 'payment_date' => now()]);

        $rows = $this->buildFileRows($company, ['ID' => '99999', 'Gaji Pokok' => 1_000_000]);

        $import = new PayrollRevisionImport($period);
        $import->collection($rows);

        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('99999', $import->getErrors()[0]);
    }

    public function test_import_skips_paid_payroll_silently(): void
    {
        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123', isPaid: true);

        $rows = $this->buildFileRows($company, ['ID' => '00123', 'Gaji Pokok' => 9_999_999]);

        $import = new PayrollRevisionImport($payroll->payrollPeriod);
        $import->collection($rows);

        $this->assertSame([], $import->getErrors());
        $this->assertSame([], $import->getDiffs());
    }

    public function test_apply_manual_revision_updates_item_and_payroll_totals_for_earning(): void
    {
        $this->actingAsAuditableUser();

        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123');

        app(PayrollService::class)->applyManualRevision(
            $payroll,
            PayrollComponentEnum::GAJI_POKOK,
            6_000_000,
            'Import Revisi Excel',
            null,
        );

        $payroll->refresh();
        $item = $payroll->items()->where('source_type', 'gaji_pokok')->first();

        $this->assertSame(6_000_000.0, (float) $item->amount);
        $this->assertSame(6_000_000.0, (float) $payroll->gross_salary);
        $this->assertSame(6_000_000.0, (float) $payroll->net_salary);
    }

    public function test_apply_manual_revision_for_deduction_lowers_net_salary(): void
    {
        $this->actingAsAuditableUser();

        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123');

        app(PayrollService::class)->applyManualRevision(
            $payroll,
            PayrollComponentEnum::BON,
            200_000,
            'Import Revisi Excel',
            null,
        );

        $payroll->refresh();

        $this->assertSame(200_000.0, (float) $payroll->total_deductions);
        $this->assertSame(4_800_000.0, (float) $payroll->net_salary);
    }

    public function test_apply_manual_revision_throws_for_paid_payroll(): void
    {
        $this->actingAsAuditableUser();

        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123', isPaid: true);

        $this->expectException(\RuntimeException::class);

        app(PayrollService::class)->applyManualRevision($payroll, PayrollComponentEnum::GAJI_POKOK, 1, 'x', null);
    }

    public function test_apply_manual_revision_writes_audit_log(): void
    {
        $this->actingAsAuditableUser();

        $company = $this->makeCompanyWithGajiPokokActive();
        $payroll = $this->makePayrollWithItem($company, '00123');

        app(PayrollService::class)->applyManualRevision($payroll, PayrollComponentEnum::GAJI_POKOK, 6_000_000, 'Import Revisi Excel', null);

        // old_value/new_value di-cast 'encrypted' (bukan plaintext di kolom) - bandingkan lewat
        // model, bukan assertDatabaseHas mentah yang baca ciphertext apa adanya.
        $log = \App\Models\AuditLog::where('module', 'payroll_adjustment')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('5000000', $log->old_value);
        $this->assertSame('6000000', $log->new_value);
    }

    /** AuditLog::record() no-op kalau tidak ada user login (Auth::check()) - butuh 1 user
     *  ter-login biar assertion audit log-nya berarti. */
    private function actingAsAuditableUser(): void
    {
        $actor = User::create(['name' => 'Actor', 'email' => 'actor@test.local', 'password' => bcrypt('secret'), 'is_active' => true]);
        $this->actingAs($actor);
    }
}
