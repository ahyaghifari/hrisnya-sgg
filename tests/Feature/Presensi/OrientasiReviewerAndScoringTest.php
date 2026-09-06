<?php

namespace Tests\Feature\Presensi;

use App\Enum\OrgStructurLevel;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\OrientationSubmission;
use App\Models\SikartapReviewer;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserOrientation;
use App\Services\OrientationService;
use App\Services\SikartapReviewerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penilaian orientasi tanpa Approval Engine (2026-09-06): penilai di-resolve dari
 * sikartap_reviewers (jabatan -> departemen), skor disimpan per indikator per bulan.
 */
class OrientasiReviewerAndScoringTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private OrgStructure $dept;
    private OrgStructure $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->dept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Departemen A', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $this->division = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Divisi A1', 'level' => OrgStructurLevel::LEVEL_DIVISION->value, 'parent_id' => $this->dept->id]);
    }

    private function userWithPosition(OrgStructure $node): array
    {
        $pos = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Jabatan ' . $node->name, 'planned_manpower' => 1, 'org_structure_id' => $node->id]);
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $pos->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        return [$user, $pos];
    }

    public function test_resolver_finds_reviewer_by_jobposition_at_department(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        [$reviewer, $reviewerPos] = $this->userWithPosition($this->dept);

        SikartapReviewer::create([
            'context' => SikartapReviewer::CONTEXT_ORIENTATION,
            'company_id' => $this->company->id,
            'org_structure_id' => $this->dept->id,
            'job_position_id' => $reviewerPos->id,
        ]);

        $resolved = app(SikartapReviewerResolver::class)->reviewersFor(SikartapReviewer::CONTEXT_ORIENTATION, $orientee);

        $this->assertTrue($resolved->contains('id', $reviewer->id));
    }

    public function test_resolver_falls_back_to_parent_department_when_position_vacant(): void
    {
        $parentDept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Direktorat', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $this->dept->update(['parent_id' => $parentDept->id]);

        [$orientee] = $this->userWithPosition($this->division);

        // Assignment di dept anak menunjuk jabatan yang TIDAK ada pemegang aktif.
        $vacantPos = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Kosong', 'planned_manpower' => 1, 'org_structure_id' => $this->dept->id]);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $this->dept->id, 'job_position_id' => $vacantPos->id]);

        // Assignment di dept induk menunjuk jabatan yang ADA pemegangnya.
        [$bigBoss, $bigBossPos] = $this->userWithPosition($parentDept);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $parentDept->id, 'job_position_id' => $bigBossPos->id]);

        $resolved = app(SikartapReviewerResolver::class)->reviewersFor(SikartapReviewer::CONTEXT_ORIENTATION, $orientee);

        $this->assertTrue($resolved->contains('id', $bigBoss->id));
    }

    public function test_submit_rejected_when_no_reviewer(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        $submission = $this->makeSubmission($orientee);

        $this->expectException(\RuntimeException::class);
        app(OrientationService::class)->submit($submission, $orientee);
    }

    public function test_review_stores_score_rows_per_month_and_replaces_on_re_review(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        [$reviewer, $reviewerPos] = $this->userWithPosition($this->dept);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $this->dept->id, 'job_position_id' => $reviewerPos->id]);

        $submission = $this->makeSubmission($orientee);
        app(OrientationService::class)->submit($submission, $orientee);
        $submission->refresh();
        $this->assertSame('diajukan', $submission->status);

        $rows = [[
            'label' => 'Kedisiplinan',
            'comment' => 'Baik',
            'sort_order' => 1,
            'monthly' => [
                ['period_month' => '2026-08-01', 'score' => 4, 'comment' => null],
                ['period_month' => '2026-09-01', 'score' => 4.5, 'comment' => null],
                ['period_month' => '2026-10-01', 'score' => 5, 'comment' => null],
            ],
        ]];

        app(OrientationService::class)->review($submission, $reviewer->id, $rows, 'lanjut_kontrak', 'Lolos orientasi');
        $submission->refresh();

        // Review = keputusan + skor, status `dinilai` (nunggu tindak lanjut HR).
        $this->assertSame('dinilai', $submission->status);
        $this->assertSame('lanjut_kontrak', $submission->decision);
        $this->assertCount(1, $submission->assessments);
        $this->assertSame(3, $submission->assessments->first()->scores()->count());

        // Re-review: 1 indikator, 1 bulan -> baris lama terhapus, keputusan ganti ke perpanjang.
        app(OrientationService::class)->review($submission, $reviewer->id, [[
            'label' => 'Kerapian',
            'comment' => null,
            'monthly' => [['period_month' => '2026-08-01', 'score' => 3, 'comment' => null]],
        ]], 'perpanjang', 'Perlu perpanjangan');
        $submission->refresh();

        $this->assertCount(1, $submission->assessments);
        $this->assertSame('Kerapian', $submission->assessments->first()->label);
        $this->assertSame(1, $submission->assessments->first()->scores()->count());
        $this->assertSame('perpanjang', $submission->decision);
        $this->assertSame('dinilai', $submission->status);
    }

    public function test_hr_follow_up_extend_creates_new_period(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        [$reviewer, $reviewerPos] = $this->userWithPosition($this->dept);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $this->dept->id, 'job_position_id' => $reviewerPos->id]);

        $submission = $this->makeSubmission($orientee);
        app(OrientationService::class)->submit($submission, $orientee);
        app(OrientationService::class)->review($submission, $reviewer->id, [[
            'label' => 'Kedisiplinan', 'comment' => null,
            'monthly' => [['period_month' => '2026-08-01', 'score' => 2, 'comment' => null]],
        ]], 'perpanjang', 'Perlu perpanjangan');
        $submission->refresh();

        $before = $orientee->orientations()->count();
        app(OrientationService::class)->followUpExtend($submission, '2026-11-01', 3, $reviewer->id);
        $submission->refresh();

        $this->assertSame($before + 1, $orientee->orientations()->count());
        $this->assertSame('selesai', $submission->status);
        $new = $orientee->orientations()->orderByDesc('start_date')->first();
        $this->assertSame('extension', $new->source);
        $this->assertSame(3, $new->duration_months);
        $this->assertSame('2026-11-01', $new->start_date->toDateString());
    }

    public function test_review_rejected_when_no_score_filled(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        [$reviewer, $reviewerPos] = $this->userWithPosition($this->dept);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $this->dept->id, 'job_position_id' => $reviewerPos->id]);

        $submission = $this->makeSubmission($orientee);
        app(OrientationService::class)->submit($submission, $orientee);

        $this->expectException(\RuntimeException::class);
        app(OrientationService::class)->review($submission, $reviewer->id, [[
            'label' => 'Kedisiplinan', 'comment' => null,
            'monthly' => [['period_month' => '2026-08-01', 'score' => null, 'comment' => null]],
        ]], 'lanjut_kontrak', 'Tanpa skor');
    }

    public function test_request_revision_sends_back_to_draft_and_clears_on_resubmit(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        [$reviewer, $reviewerPos] = $this->userWithPosition($this->dept);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $this->dept->id, 'job_position_id' => $reviewerPos->id]);

        $submission = $this->makeSubmission($orientee);
        app(OrientationService::class)->submit($submission, $orientee);

        app(OrientationService::class)->requestRevision($submission, $reviewer->id, 'Bukti PDF terpotong, unggah ulang.');
        $submission->refresh();
        $this->assertSame('draft', $submission->status);
        $this->assertSame('Bukti PDF terpotong, unggah ulang.', $submission->decision_note);
        $this->assertSame($reviewer->id, $submission->decided_by);

        app(OrientationService::class)->submit($submission, $orientee);
        $submission->refresh();
        $this->assertSame('diajukan', $submission->status);
        $this->assertNull($submission->decision_note);
        $this->assertNull($submission->decided_by);
    }

    public function test_hr_follow_up_contract_creates_contract_and_marks_selesai(): void
    {
        [$orientee] = $this->userWithPosition($this->division);
        [$reviewer, $reviewerPos] = $this->userWithPosition($this->dept);
        SikartapReviewer::create(['context' => SikartapReviewer::CONTEXT_ORIENTATION, 'company_id' => $this->company->id, 'org_structure_id' => $this->dept->id, 'job_position_id' => $reviewerPos->id]);

        $kontrakType = \App\Models\Master\EmployeeTypes::create([
            'name' => 'Karyawan Kontrak', 'category' => \App\Enum\EmployeeTypeCategory::KONTRAK->value, 'company_id' => $this->company->id,
        ]);

        $submission = $this->makeSubmission($orientee);
        app(OrientationService::class)->submit($submission, $orientee);
        app(OrientationService::class)->review($submission, $reviewer->id, [[
            'label' => 'Kedisiplinan', 'comment' => null,
            'monthly' => [['period_month' => '2026-08-01', 'score' => 4, 'comment' => null]],
        ]], 'lanjut_kontrak', 'Layak kontrak');
        $submission->refresh();

        app(OrientationService::class)->followUpContract($submission, $kontrakType->id, '2026-11-01', '2027-10-31', $reviewer->id);
        $submission->refresh();

        $this->assertSame('selesai', $submission->status);
        $this->assertDatabaseHas('user_contracts', [
            'user_id' => $orientee->id,
            'employee_type_id' => $kontrakType->id,
        ]);
    }

    private function makeSubmission(User $orientee): OrientationSubmission
    {
        $period = UserOrientation::create([
            'user_id' => $orientee->id,
            'company_id' => $this->company->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
            'duration_months' => 3,
            'source' => 'manual',
        ]);

        return OrientationSubmission::create([
            'user_id' => $orientee->id,
            'user_orientation_id' => $period->id,
            'company_id' => $this->company->id,
            'status' => 'draft',
            'attachments' => [['path' => 'x.pdf', 'name' => 'x.pdf']],
        ]);
    }
}
