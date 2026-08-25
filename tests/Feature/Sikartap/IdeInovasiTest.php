<?php

namespace Tests\Feature\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Enum\Sikartap\SikartapSettingEnum;
use App\Livewire\Sikartap\IdeInovasiAtasanQueuePage;
use App\Livewire\Sikartap\IdeInovasiAtasanReviewPage;
use App\Livewire\Sikartap\IdeInovasiUploadPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Setting;
use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapIdeInovasiReviewer;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 5 — Ide Inovasi. Tahap 1 (atasan) wajib & blocking, tahap 2/3 (direktur/komisaris)
 * opsional (dicover PengajuanDetailPageTest kalau ada, di sini fokus upload + gate atasan).
 */
class IdeInovasiTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    private OrgStructure $orgRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->orgRoot = OrgStructure::create([
            'company_id' => $this->company->id,
            'name' => 'IGD',
            'level' => OrgStructurLevel::LEVEL_DIVISION->value,
        ]);

        Setting::create([
            'company_id' => $this->company->id,
            'module' => 'sikartap',
            'key' => SikartapSettingEnum::WAJIB_IDE_INOVASI->value,
            'type' => 'boolean',
            'value' => '1',
            'label' => 'Wajib Ide Inovasi',
        ]);
    }

    private function makeApplicantWithOrgRoot(): User
    {
        $applicant = User::factory()->create(['company_id' => $this->company->id]);

        $jobPosition = JobPositions::create([
            'company_id' => $this->company->id,
            'org_structure_id' => $this->orgRoot->id,
            'name' => 'Perawat',
        ]);

        UserJobPositions::create([
            'user_id' => $applicant->id,
            'job_position_id' => $jobPosition->id,
            'status' => 'active',
            'start_date' => now()->subYear(),
        ]);

        return $applicant;
    }

    private function makeSikartapReadyForIdeInovasi(User $applicant): Sikartap
    {
        return Sikartap::create([
            'user_id' => $applicant->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
            'status_tes' => 'lulus',
            'status_wawancara' => 'disarankan',
        ]);
    }

    public function test_upload_page_blocked_when_company_does_not_require_ide_inovasi(): void
    {
        Setting::where('company_id', $this->company->id)->update(['value' => '0']);

        $applicant = $this->makeApplicantWithOrgRoot();
        $this->makeSikartapReadyForIdeInovasi($applicant);
        $this->actingAs($applicant);

        Livewire::test(IdeInovasiUploadPage::class)->assertForbidden();
    }

    public function test_upload_page_blocked_before_wawancara_disarankan(): void
    {
        $applicant = $this->makeApplicantWithOrgRoot();
        Sikartap::create(['user_id' => $applicant->id]);
        $this->actingAs($applicant);

        Livewire::test(IdeInovasiUploadPage::class)->assertForbidden();
    }

    public function test_karyawan_can_upload_ide_inovasi(): void
    {
        $applicant = $this->makeApplicantWithOrgRoot();
        $sikartap = $this->makeSikartapReadyForIdeInovasi($applicant);
        $this->actingAs($applicant);

        Livewire::test(IdeInovasiUploadPage::class)
            ->set('file', UploadedFile::fake()->create('ide.pdf', 500))
            ->call('upload');

        $ideInovasi = $sikartap->fresh()->ideInovasi;
        $this->assertNotNull($ideInovasi);
        $this->assertSame('pending', $ideInovasi->status_atasan);
        $this->assertFalse($sikartap->fresh()->ideInovasiApproved());
    }

    public function test_org_root_head_can_review_as_atasan(): void
    {
        $atasan = User::factory()->create(['company_id' => $this->company->id]);
        $this->orgRoot->update(['head_id' => $atasan->id]);

        $applicant = $this->makeApplicantWithOrgRoot();
        $sikartap = $this->makeSikartapReadyForIdeInovasi($applicant);
        $sikartap->ideInovasi()->create(['file_ide_inovasi' => 'sikartap/ide-inovasi/fake.pdf']);

        $this->assertTrue($sikartap->canReviewIdeInovasiAsAtasan($atasan));

        $this->actingAs($atasan);

        Livewire::test(IdeInovasiAtasanQueuePage::class)
            ->assertSet('queue', fn ($queue) => $queue->contains('id', $sikartap->id));

        Livewire::test(IdeInovasiAtasanReviewPage::class, ['sikartap' => $sikartap])
            ->set('skor.skor_identifikasi_masalah', 5)
            ->set('skor.skor_analisis_masalah', 5)
            ->set('skor.skor_kualitas_inovasi', 5)
            ->set('skor.skor_potensi_manfaat', 5)
            ->set('skor.skor_presentasi', 5)
            ->call('submit');

        $ideInovasi = $sikartap->fresh()->ideInovasi;
        $this->assertSame('approved', $ideInovasi->status_atasan);
        $this->assertEquals(100.0, $ideInovasi->nilai_akhir_atasan);
        $this->assertTrue($sikartap->fresh()->ideInovasiApproved());
    }

    public function test_low_score_results_in_rejected_status(): void
    {
        $atasan = User::factory()->create(['company_id' => $this->company->id]);
        $this->orgRoot->update(['head_id' => $atasan->id]);

        $applicant = $this->makeApplicantWithOrgRoot();
        $sikartap = $this->makeSikartapReadyForIdeInovasi($applicant);
        $sikartap->ideInovasi()->create(['file_ide_inovasi' => 'sikartap/ide-inovasi/fake.pdf']);

        $this->actingAs($atasan);

        Livewire::test(IdeInovasiAtasanReviewPage::class, ['sikartap' => $sikartap])
            ->set('skor.skor_identifikasi_masalah', 1)
            ->set('skor.skor_analisis_masalah', 1)
            ->set('skor.skor_kualitas_inovasi', 1)
            ->set('skor.skor_potensi_manfaat', 1)
            ->set('skor.skor_presentasi', 1)
            ->call('submit');

        $this->assertSame('rejected', $sikartap->fresh()->ideInovasi->status_atasan);
    }

    public function test_manually_assigned_reviewer_can_review_even_without_head_id(): void
    {
        $ditunjuk = User::factory()->create(['company_id' => $this->company->id]);
        SikartapIdeInovasiReviewer::create([
            'company_id' => $this->company->id,
            'org_structure_id' => $this->orgRoot->id,
            'user_id' => $ditunjuk->id,
        ]);

        $applicant = $this->makeApplicantWithOrgRoot();
        $sikartap = $this->makeSikartapReadyForIdeInovasi($applicant);
        $sikartap->ideInovasi()->create(['file_ide_inovasi' => 'sikartap/ide-inovasi/fake.pdf']);

        $this->assertTrue($sikartap->canReviewIdeInovasiAsAtasan($ditunjuk));

        $this->actingAs($ditunjuk);
        Livewire::test(IdeInovasiAtasanReviewPage::class, ['sikartap' => $sikartap])->assertOk();
    }

    public function test_non_reviewer_is_forbidden(): void
    {
        $random = User::factory()->create(['company_id' => $this->company->id]);

        $applicant = $this->makeApplicantWithOrgRoot();
        $sikartap = $this->makeSikartapReadyForIdeInovasi($applicant);
        $sikartap->ideInovasi()->create(['file_ide_inovasi' => 'sikartap/ide-inovasi/fake.pdf']);

        $this->actingAs($random);

        Livewire::test(IdeInovasiAtasanReviewPage::class, ['sikartap' => $sikartap])->assertForbidden();
    }

    public function test_director_decision_requires_ide_inovasi_approved_when_required(): void
    {
        $applicant = $this->makeApplicantWithOrgRoot();
        $sikartap = $this->makeSikartapReadyForIdeInovasi($applicant);

        Setting::updateOrCreate(
            ['company_id' => $this->company->id, 'key' => SikartapSettingEnum::WAJIB_MCU->value],
            ['module' => 'sikartap', 'type' => 'boolean', 'value' => '0', 'label' => 'Wajib MCU']
        );

        $this->assertFalse($sikartap->isEligibleForDirectorDecision());

        $sikartap->ideInovasi()->create([
            'file_ide_inovasi' => 'sikartap/ide-inovasi/fake.pdf',
            'status_atasan' => 'approved',
        ]);

        $this->assertTrue($sikartap->fresh()->isEligibleForDirectorDecision());
    }
}
