<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Enum\Sp\SpReportStatus;
use App\Models\Master\Companies;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Services\Document\DocumentSigningService;
use App\Services\Sp\SpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Alur Berita Acara baru (instruksi user 2026-10-01): status, urutan TTD, dan penguncian. */
class SpReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $hc;
    private User $employee;
    private User $knower;
    private SpReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'SPR', 'name' => 'Company SPR', 'location' => 'Test', 'is_active' => true]);

        foreach ([CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->hc = User::factory()->create(['company_id' => $this->company->id]);
        $this->hc->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp');
        $this->employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->knower = User::factory()->create(['company_id' => $this->company->id]);
        $this->service = app(SpReportService::class);
    }

    private function draft(): SpReport
    {
        return $this->service->create($this->hc, [
            'user_id' => $this->employee->id,
            'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat 5 kali',
            'dasar_klarifikasi' => 'Rekap presensi',
        ]);
    }

    private function untilWaitingSignature(): SpReport
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);
        $this->service->submitClarification($report, $this->employee, 'macet di jalan');
        $this->service->saveAnalysis($report, $this->hc, [
            'klarifikasi_parafrase' => 'Karyawan terlambat karena kemacetan.',
            'analysis' => 'Berulang', 'kesimpulan' => 'Melanggar', 'followup_plan' => 'SP1',
        ]);

        return $this->service->sendForSigning($report, $this->hc);
    }

    private function sign(SpReport $report, User $user): void
    {
        $document = $report->fresh()->document;
        app(DocumentSigningService::class)->sign($document, $user);
    }

    public function test_full_flow_ends_selesai_and_locks_content(): void
    {
        $report = $this->untilWaitingSignature();
        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->status);

        $this->sign($report, $this->employee);
        $this->sign($report, $this->hc);
        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->fresh()->status);
        $this->sign($report, $this->knower);

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::SELESAI, $report->status);

        $this->expectException(\LogicException::class);
        $report->update(['analysis' => 'diubah diam-diam']);
    }

    public function test_decision_after_selesai_is_allowed_and_only_once(): void
    {
        $report = $this->untilWaitingSignature();
        foreach ([$this->employee, $this->hc, $this->knower] as $user) {
            $this->sign($report, $user);
        }

        $this->service->decide($report->fresh(), $this->hc, SpReportDecision::LANJUT_SP);
        $this->assertSame(SpReportDecision::LANJUT_SP, $report->fresh()->keputusan);

        $this->expectException(ValidationException::class);
        $this->service->decide($report->fresh(), $this->hc, SpReportDecision::TIDAK_LAYAK_SP);
    }

    public function test_creator_and_knower_cannot_sign_before_employee(): void
    {
        $report = $this->untilWaitingSignature();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->sign($report, $this->hc);
    }

    public function test_employee_can_request_reclarification_and_old_version_is_kept(): void
    {
        $report = $this->untilWaitingSignature();

        $this->service->requestReclarification($report, $this->employee, 'Parafrase tidak sesuai maksud saya');

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::MENUNGGU_KLARIFIKASI, $report->status);
        $this->assertNull($report->klarifikasi_parafrase);
        $this->assertSame('Karyawan terlambat karena kemacetan.', $report->riwayat_klarifikasi[0]['parafrase']);
    }

    public function test_reclarification_blocked_after_employee_signed(): void
    {
        $report = $this->untilWaitingSignature();
        $this->sign($report, $this->employee);

        $this->expectException(ValidationException::class);
        $this->service->requestReclarification($report->fresh(), $this->employee, 'terlambat');
    }

    public function test_draft_requires_kejadian_and_dasar_klarifikasi(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create($this->hc, ['user_id' => $this->employee->id, 'knows_by' => $this->knower->id, 'kejadian' => 'x']);
    }

    public function test_only_the_employee_can_submit_clarification(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->submitClarification($report, $this->knower, 'bukan saya');
    }
}
