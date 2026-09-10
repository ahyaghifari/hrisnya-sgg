<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\EmployeeTypeCategory;
use App\Enum\OrgStructurLevel;
use App\Models\ContractSubmission;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\SikartapReviewer;
use App\Models\User;
use App\Models\User\UserContract;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use App\Services\ContractExtensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul Perpanjangan Kontrak (2026-09-06) — lepas Approval Engine, reviewer dari
 * sikartap_reviewers context `contract`, `perpanjang` bikin baris user_contracts baru.
 */
class PerpanjanganKontrakTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private OrgStructure $dept;
    private OrgStructure $division;
    private EmployeeTypes $kontrakType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $this->dept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Departemen A', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $this->division = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Divisi A1', 'level' => OrgStructurLevel::LEVEL_DIVISION->value, 'parent_id' => $this->dept->id]);
        $this->kontrakType = EmployeeTypes::create(['name' => 'Karyawan Kontrak', 'category' => EmployeeTypeCategory::KONTRAK->value, 'company_id' => $this->company->id]);
    }

    private function userWithPosition(OrgStructure $node): array
    {
        $pos = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Jabatan ' . $node->name, 'planned_manpower' => 1, 'org_structure_id' => $node->id]);
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $pos->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        return [$user, $pos];
    }

    private function kontrakEmployee(): User
    {
        [$user] = $this->userWithPosition($this->division);
        UserProfiles::create([
            'user_id' => $user->id,
            'employee_type_id' => $this->kontrakType->id,
            'joining_date' => now()->subYears(1)->toDateString(),
        ]);
        UserContract::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'employee_type_id' => $this->kontrakType->id,
            'contract_start_date' => now()->subMonths(11)->toDateString(),
            'contract_end_date' => now()->addMonth()->toDateString(),
        ]);

        return $user->fresh(['profile.employeeType', 'currentContract']);
    }

    private function makeSubmission(User $employee): ContractSubmission
    {
        return ContractSubmission::create([
            'user_id' => $employee->id,
            'user_contract_id' => $employee->currentContract?->id,
            'company_id' => $this->company->id,
            'status' => 'draft',
            'alasan' => 'Kinerja baik, lanjut.',
        ]);
    }

    private function seedReviewer(): User
    {
        [$reviewer, $pos] = $this->userWithPosition($this->dept);
        SikartapReviewer::create([
            'context' => SikartapReviewer::CONTEXT_CONTRACT,
            'company_id' => $this->company->id,
            'org_structure_id' => $this->dept->id,
            'job_position_id' => $pos->id,
        ]);

        return $reviewer;
    }

    public function test_submit_rejected_when_no_reviewer(): void
    {
        $employee = $this->kontrakEmployee();
        $submission = $this->makeSubmission($employee);
        $submission->documents()->create(['label' => 'Kontrak Terakhir', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);
        $submission->documents()->create(['label' => 'Surat Rekomendasi Atasan', 'path' => 'y.pdf', 'original_name' => 'y.pdf']);

        $this->expectException(\RuntimeException::class);
        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);
    }

    public function test_submit_rejected_when_required_document_missing(): void
    {
        $employee = $this->kontrakEmployee();
        $this->seedReviewer();
        $submission = $this->makeSubmission($employee);
        $submission->documents()->create(['label' => 'Kontrak Terakhir', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);

        $this->expectException(\RuntimeException::class);
        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);
    }

    public function test_review_perpanjang_creates_new_contract(): void
    {
        $employee = $this->kontrakEmployee();
        $reviewer = $this->seedReviewer();
        $submission = $this->makeSubmission($employee);
        $submission->documents()->create(['label' => 'Kontrak Terakhir', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);
        $submission->documents()->create(['label' => 'Surat Rekomendasi Atasan', 'path' => 'y.pdf', 'original_name' => 'y.pdf']);

        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);
        $submission->refresh();
        $this->assertSame('diajukan', $submission->status);

        $before = $employee->contracts()->count();

        // Penilai memutuskan perpanjang — TANPA durasi. Belum ada kontrak baru.
        app(ContractExtensionService::class)->review($submission, $reviewer->id, 'perpanjang', 'Disetujui');
        $submission->refresh();
        $this->assertSame('dinilai', $submission->status);
        $this->assertSame('perpanjang', $submission->decision);
        $this->assertNull($submission->decision_durasi_bulan);
        $this->assertSame($before, $employee->contracts()->count());

        // HR menetapkan durasi → kontrak baru dibuat, selesai.
        app(ContractExtensionService::class)->followUpDuration($submission, 6, $reviewer->id);
        $submission->refresh();

        $this->assertSame('selesai', $submission->status);
        $this->assertSame(6, $submission->decision_durasi_bulan);
        $this->assertSame($before + 1, $employee->contracts()->count());
        $this->assertNotNull($submission->new_contract_id);

        $new = UserContract::find($submission->new_contract_id);
        // anchor = kontrak lama berakhir (+1 bln dari hari ini), end = anchor + 6 bln.
        $this->assertSame(now()->addMonth()->toDateString(), $new->contract_start_date->toDateString());
        $this->assertSame(now()->addMonth()->addMonths(6)->toDateString(), $new->contract_end_date->toDateString());
    }

    public function test_review_tidak_perpanjang_marks_ditolak_no_contract(): void
    {
        $employee = $this->kontrakEmployee();
        $reviewer = $this->seedReviewer();
        $submission = $this->makeSubmission($employee);
        $submission->documents()->create(['label' => 'Kontrak Terakhir', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);
        $submission->documents()->create(['label' => 'Surat Rekomendasi Atasan', 'path' => 'y.pdf', 'original_name' => 'y.pdf']);
        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);

        $before = $employee->contracts()->count();
        app(ContractExtensionService::class)->review($submission->fresh(), $reviewer->id, 'tidak_perpanjang', 'Kinerja kurang');
        $submission->refresh();

        $this->assertSame('ditolak', $submission->status);
        $this->assertSame($before, $employee->contracts()->count());
        $this->assertNull($submission->new_contract_id);
    }

    public function test_request_revision_sends_back_to_draft_and_clears_on_resubmit(): void
    {
        $employee = $this->kontrakEmployee();
        $reviewer = $this->seedReviewer();
        $submission = $this->makeSubmission($employee);
        $submission->documents()->create(['label' => 'Kontrak Terakhir', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);
        $submission->documents()->create(['label' => 'Surat Rekomendasi Atasan', 'path' => 'y.pdf', 'original_name' => 'y.pdf']);
        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);

        app(ContractExtensionService::class)->requestRevision($submission->fresh(), $reviewer->id, 'Kontrak terakhir buram, unggah ulang.');
        $submission->refresh();
        $this->assertSame('draft', $submission->status);
        $this->assertSame('Kontrak terakhir buram, unggah ulang.', $submission->decision_note);

        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);
        $submission->refresh();
        $this->assertSame('diajukan', $submission->status);
        $this->assertNull($submission->decision_note);
        $this->assertNull($submission->decided_by);
    }

    public function test_follow_up_duration_rejected_when_exceeds_max_kontrak_tahun(): void
    {
        $employee = $this->kontrakEmployee();
        // joining 1 thn lalu, default MAX_KONTRAK_TAHUN = 3 → anchor +1bln, +36 bln jauh lewat.
        $reviewer = $this->seedReviewer();
        $submission = $this->makeSubmission($employee);
        $submission->documents()->create(['label' => 'Kontrak Terakhir', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);
        $submission->documents()->create(['label' => 'Surat Rekomendasi Atasan', 'path' => 'y.pdf', 'original_name' => 'y.pdf']);
        app(ContractExtensionService::class)->submit($submission->fresh(), $employee);

        app(ContractExtensionService::class)->review($submission->fresh(), $reviewer->id, 'perpanjang', 'Disetujui');

        $this->expectException(\Throwable::class);
        app(ContractExtensionService::class)->followUpDuration($submission->fresh(), 36, $reviewer->id);
    }
}
