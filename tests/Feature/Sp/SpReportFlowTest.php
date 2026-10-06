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
            'dasar_items' => ['Rekap presensi'],
        ]);
    }

    private function untilWaitingSignature(): SpReport
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);
        $this->service->fillClarificationByHc($report, $this->hc, 'macet di jalan');
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

    public function test_there_is_no_paraphrase_step(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);
        $this->service->fillClarificationByHc($report, $this->hc, '<div>macet di jalan</div>');

        $this->assertNull($report->fresh()->klarifikasi_parafrase);

        $this->service->saveAnalysis($report->fresh(), $this->hc, ['analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f']);
        $this->service->sendForSigning($report->fresh(), $this->hc);

        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->fresh()->status);
        $this->assertNull($report->fresh()->klarifikasi_parafrase);
    }

    public function test_clarification_is_filled_by_creator_without_employee_step(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);

        $this->service->fillClarificationByHc($report, $this->hc, 'macet di jalan');

        $report->refresh();
        $this->assertSame(SpReportStatus::KLARIFIKASI_DIAJUKAN, $report->status);
        $this->assertSame('macet di jalan', $report->subject_clarification);
        $this->assertTrue($report->klarifikasi_diisi_hc);
    }

    public function test_full_flow_ends_selesai_and_locks_content(): void
    {
        $report = $this->untilWaitingSignature();
        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->status);

        $this->sign($report, $this->hc);
        $this->sign($report, $this->knower);
        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->fresh()->status);
        $this->sign($report, $this->employee);

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::SELESAI, $report->status);

        $this->expectException(\LogicException::class);
        $report->update(['analysis' => 'diubah diam-diam']);
    }

    public function test_decision_after_selesai_is_allowed_and_only_once(): void
    {
        $report = $this->untilWaitingSignature();
        foreach ([$this->hc, $this->knower, $this->employee] as $user) {
            $this->sign($report, $user);
        }

        $this->service->decide($report->fresh(), $this->hc, SpReportDecision::LANJUT_SP);
        $this->assertSame(SpReportDecision::LANJUT_SP, $report->fresh()->keputusan);

        $this->expectException(ValidationException::class);
        $this->service->decide($report->fresh(), $this->hc, SpReportDecision::TIDAK_LAYAK_SP);
    }

    public function test_employee_and_knower_cannot_sign_before_creator(): void
    {
        $report = $this->untilWaitingSignature();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->sign($report, $this->employee);
    }

    public function test_supervisor_signs_after_knower_and_before_employee(): void
    {
        $supervisor = User::factory()->create(['company_id' => $this->company->id]);
        $report = $this->untilWaitingSignature();
        $report->update(['supervisor_id' => $supervisor->id]);

        $this->assertSame(['created_by', 'knows_by', 'supervisor', 'employee'], array_keys($report->fresh()->expectedSignerRoles()));

        $this->sign($report, $this->hc);
        $this->assertNull($report->fresh()->assignedSigners()['supervisor']);
        $this->sign($report, $this->knower);
        $this->assertNull($report->fresh()->assignedSigners()['employee']);
        $this->sign($report, $supervisor);
        $this->assertSame($this->employee->id, $report->fresh()->assignedSigners()['employee']->id);
        $this->sign($report, $this->employee);

        $this->assertSame(SpReportStatus::SELESAI, $report->fresh()->status);
    }

    public function test_employee_can_request_reclarification_and_old_version_is_kept(): void
    {
        $report = $this->untilWaitingSignature();

        $this->service->requestReclarification($report, $this->employee, 'Parafrase tidak sesuai maksud saya');

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::MENUNGGU_KLARIFIKASI, $report->status);
        $this->assertSame('macet di jalan', $report->riwayat_klarifikasi[0]['klarifikasi']);
    }

    public function test_reclarification_blocked_after_creator_signed(): void
    {
        $report = $this->untilWaitingSignature();
        $this->sign($report, $this->hc);

        $this->expectException(ValidationException::class);
        $this->service->requestReclarification($report->fresh(), $this->employee, 'terlambat');
    }

    public function test_draft_requires_kejadian_and_dasar_klarifikasi(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create($this->hc, ['user_id' => $this->employee->id, 'knows_by' => $this->knower->id, 'kejadian' => 'x']);
    }

    public function test_dasar_items_are_numbered_only_when_more_than_one(): void
    {
        $report = $this->draft();
        $this->assertSame('Rekap presensi', (string) $report->dasarKlarifikasiHtml());

        $this->service->updateDraft($report, $this->hc, ['dasar_items' => ['A', 'B']]);
        $report = $report->fresh();

        $this->assertSame('<ol class="dasar-items"><li>A</li><li>B</li></ol>', (string) $report->dasarKlarifikasiHtml());
        $this->assertSame("A\nB", $report->dasar_klarifikasi);
    }

    public function test_only_creator_or_hc_can_fill_clarification(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->fillClarificationByHc($report, $this->employee, 'diisi sendiri');
    }
}
