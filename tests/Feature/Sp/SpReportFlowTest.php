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
use Tests\Support\SpFixtures;
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
            'invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id,
            'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat 5 kali',
            'dasar_items' => ['Rekap presensi'],
        ]);
    }

    private function untilWaitingSignature(): SpReport
    {
        $report = $this->draft();
        $this->service->updateDraft($report, $this->hc, [
            'subject_clarification' => 'macet di jalan',
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
        $this->service->updateDraft($report, $this->hc, ['subject_clarification' => '<div>macet di jalan</div>', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f']);

        $this->assertNull($report->fresh()->klarifikasi_parafrase);

        $this->service->sendForSigning($report->fresh(), $this->hc);

        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->fresh()->status);
        $this->assertNull($report->fresh()->klarifikasi_parafrase);
    }

    public function test_all_hc_content_is_filled_in_draft_and_fix_requires_it_complete(): void
    {
        $report = $this->draft();
        $this->service->updateDraft($report, $this->hc, ['subject_clarification' => 'macet di jalan']);

        $report->refresh();
        $this->assertSame(SpReportStatus::DRAFT, $report->status);
        $this->assertSame('macet di jalan', $report->subject_clarification);

        try {
            $this->service->sendForSigning($report, $this->hc);
            $this->fail('FIX harus ditolak saat analisis belum diisi');
        } catch (ValidationException) {
        }

        $this->service->updateDraft($report, $this->hc, ['analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f']);
        $this->service->sendForSigning($report->fresh(), $this->hc);

        $report->refresh();
        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->status);
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

    public function test_supervisor_is_written_as_creator_and_signs_first(): void
    {
        $supervisor = User::factory()->create(['company_id' => $this->company->id]);
        $report = $this->untilWaitingSignature();
        $report->update(['supervisor_id' => $supervisor->id]);

        $this->assertSame(['supervisor', 'created_by', 'knows_by', 'employee'], array_keys($report->fresh()->expectedSignerRoles()));
        $this->assertSame('Atasan Langsung', $report->fresh()->expectedSignerRoles()['supervisor']);
        $this->assertSame('Mengetahui', $report->fresh()->expectedSignerRoles()['created_by']);

        // Atasan langsung = HC pembuat: HC menempati slot Atasan Langsung, tidak tanda tangan dua kali.
        $asCreator = $this->untilWaitingSignature();
        $asCreator->update(['supervisor_id' => $this->hc->id]);
        $this->assertSame(['created_by', 'knows_by', 'employee'], array_keys($asCreator->fresh()->expectedSignerRoles()));
        $this->assertSame('Atasan Langsung', $asCreator->fresh()->expectedSignerRoles()['created_by']);

        $this->assertNull($report->fresh()->assignedSigners()['created_by']);
        $this->assertSame($supervisor->id, $report->fresh()->assignedSigners()['supervisor']->id);
        $this->sign($report, $supervisor);
        $this->assertNull($report->fresh()->assignedSigners()['knows_by']);
        $this->sign($report, $this->hc);
        $this->assertNull($report->fresh()->assignedSigners()['employee']);
        $this->sign($report, $this->knower);
        $this->assertSame($this->employee->id, $report->fresh()->assignedSigners()['employee']->id);
        $this->sign($report, $this->employee);

        $this->assertSame(SpReportStatus::SELESAI, $report->fresh()->status);
    }

    public function test_hc_can_revise_after_fix_even_with_signatures_and_order_changes(): void
    {
        $supervisor = User::factory()->create(['company_id' => $this->company->id]);
        $report = $this->untilWaitingSignature();
        $report->update(['supervisor_id' => $supervisor->id]);
        $this->sign($report, $supervisor);
        $this->assertSame(1, $report->fresh()->document->signatures()->count());

        $this->service->revise($report->fresh(), $this->hc, 'Kejadian salah tanggal');

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::DRAFT, $report->status);
        $this->assertNotNull($report->revised_at);
        $this->assertSame(0, $report->document->signatures()->count());
        $this->assertNull($report->document->fixed_at);
        $voided = $report->document->events()->where('event', 'document_revised')->firstOrFail()->meta;
        $this->assertSame('supervisor', $voided['voided_signatures'][0]['role']);

        // Edit lalu FIX ulang: urutan TTD = HC pembuat, atasan langsung, mengetahui, karyawan.
        $this->service->updateDraft($report, $this->hc, ['kejadian' => 'Kejadian dikoreksi']);
        $this->service->sendForSigning($report->fresh(), $this->hc);

        $report = $report->fresh();
        $this->assertSame(['created_by', 'supervisor', 'knows_by', 'employee'], array_keys($report->expectedSignerRoles()));
        $this->assertSame($this->hc->id, $report->assignedSigners()['created_by']->id);
        $this->assertNull($report->assignedSigners()['supervisor']);
    }

    public function test_verification_page_lists_all_signers_and_voided_code_says_revised(): void
    {
        $supervisor = User::factory()->create(['company_id' => $this->company->id]);
        $report = $this->untilWaitingSignature();
        $report->update(['supervisor_id' => $supervisor->id]);
        $this->sign($report, $supervisor);

        $code = $report->fresh()->document->signatures()->with('verification')->firstOrFail()->verification->verification_code;

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('Seluruh Penanda Tangan')
            ->assertSee('1 dari 4 sudah menandatangani')
            ->assertSee('Belum menandatangani')
            ->assertSee('yang dipindai');

        $this->service->revise($report->fresh(), $this->hc, 'Kejadian salah tanggal');

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('TANDA TANGAN DIBATALKAN')
            ->assertSee('Kejadian salah tanggal');
    }

    public function test_coaching_source_is_recorded_on_the_report_and_must_belong_to_the_employee(): void
    {
        $coaching = \App\Models\Coaching\Coaching::create([
            'company_id' => $this->company->id, 'user_id' => $this->employee->id, 'coach_by' => $this->hc->id,
            'title' => 'Coaching keterlambatan', 'improvement_due_date' => now()->addWeek(),
        ]);
        $data = fn ($coachingId) => [
            'invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'x', 'dasar_items' => ['d'], 'coaching_id' => $coachingId,
        ];

        $report = $this->service->create($this->hc, $data($coaching->id));
        $this->assertSame($coaching->id, $report->fresh()->coaching_id);

        // Coaching karyawan lain ditolak.
        $other = \App\Models\Coaching\Coaching::create([
            'company_id' => $this->company->id, 'user_id' => $this->knower->id, 'coach_by' => $this->hc->id,
            'title' => 'Coaching lain', 'improvement_due_date' => now()->addWeek(),
        ]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->create($this->hc, $data($other->id));
    }

    public function test_revise_requires_reason_and_waiting_signature_status(): void
    {
        $draft = $this->draft();

        try {
            $this->service->revise($draft, $this->hc, 'x');
            $this->fail('draft tidak boleh direvisi');
        } catch (ValidationException) {
        }

        $report = $this->untilWaitingSignature();
        $this->expectException(ValidationException::class);
        $this->service->revise($report, $this->hc, '');
    }

    public function test_employee_can_request_reclarification_and_old_version_is_kept(): void
    {
        $report = $this->untilWaitingSignature();

        $this->service->requestReclarification($report, $this->employee, 'Parafrase tidak sesuai maksud saya');

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::DRAFT, $report->status);
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
        $this->service->create($this->hc, ['invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id, 'knows_by' => $this->knower->id, 'kejadian' => 'x']);
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

    public function test_only_creator_or_hc_can_edit_draft(): void
    {
        $report = $this->draft();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->updateDraft($report, $this->employee, ['subject_clarification' => 'diisi sendiri']);
    }
}
