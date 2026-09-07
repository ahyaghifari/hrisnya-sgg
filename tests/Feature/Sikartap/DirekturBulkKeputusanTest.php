<?php

namespace Tests\Feature\Sikartap;

use App\Enum\OrgStructurLevel;
use App\Livewire\PendingApprovalsCard;
use App\Livewire\Sikartap\DirekturBulkKeputusanPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Keputusan Direktur massal + pengelompokan widget "Butuh Persetujuan Anda". Lepas Approval
 * Engine (2026-09-07) — Direktur = pemegang jabatan Setting sikartap_direktur_job_position_id. */
class DirekturBulkKeputusanTest extends TestCase
{
    use RefreshDatabase;

    private ?Companies $company = null;

    private User $hrd;

    private User $direktur;

    private function setUpActors(): void
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $this->hrd = User::factory()->create(['company_id' => $this->company->id]);
        $hrdRole = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $hrdRole->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']),
        ]);
        $this->hrd->assignRole($hrdRole);

        $dept = OrgStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Direksi',
            'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value,
        ]);
        $pos = JobPositions::create([
            'company_id' => $this->company->id,
            'name' => 'Direktur Utama',
            'planned_manpower' => 1,
            'org_structure_id' => $dept->id,
        ]);

        $this->direktur = User::factory()->create(['company_id' => $this->company->id]);
        UserJobPositions::create([
            'user_id' => $this->direktur->id,
            'job_position_id' => $pos->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        Setting::updateOrCreate(
            ['key' => SikartapDirectorDecisionService::SETTING_KEY, 'company_id' => $this->company->id],
            ['module' => 'sikartap', 'type' => 'string', 'label' => 'Jabatan Direktur', 'value' => $pos->id],
        );
    }

    private function makeEligibleAndSubmitted(): Sikartap
    {
        $applicant = User::factory()->create(['company_id' => $this->company->id]);

        $sikartap = Sikartap::create([
            'user_id' => $applicant->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
            'status_tes' => 'lulus',
            'status_wawancara' => 'disarankan',
            'status_mcu' => 'fit',
        ]);

        $this->actingAs($this->hrd);
        app(SikartapDirectorDecisionService::class)->submitForDecision($sikartap);

        return $sikartap;
    }

    public function test_widget_groups_single_submission_straight_to_detail_page(): void
    {
        $this->setUpActors();
        $sikartap = $this->makeEligibleAndSubmitted();

        $this->actingAs($this->direktur);

        $requests = Livewire::test(PendingApprovalsCard::class)->viewData('requests');
        $row = $requests->firstWhere('module', 'sikartap_director_decision');

        $this->assertNotNull($row);
        $this->assertSame('1 orang diajukan', $row->keterangan);
        $this->assertSame(route('sikartap.pengajuan.show', $sikartap), $row->url);
    }

    public function test_widget_groups_multiple_submissions_to_bulk_page(): void
    {
        $this->setUpActors();
        $this->makeEligibleAndSubmitted();
        $this->makeEligibleAndSubmitted();
        $this->makeEligibleAndSubmitted();

        $this->actingAs($this->direktur);

        $requests = Livewire::test(PendingApprovalsCard::class)->viewData('requests');
        $row = $requests->firstWhere('module', 'sikartap_director_decision');

        $this->assertNotNull($row);
        $this->assertSame('3 orang diajukan', $row->keterangan);
        $this->assertSame(route('sikartap.direktur.bulk'), $row->url);
        $this->assertSame($this->hrd->name, $row->user_name);
    }

    public function test_bulk_page_returns_404_when_nothing_pending(): void
    {
        $this->setUpActors();
        $this->actingAs($this->direktur);

        Livewire::test(DirekturBulkKeputusanPage::class)->assertStatus(404);
    }

    public function test_bulk_page_can_approve_selected(): void
    {
        $this->setUpActors();
        $a = $this->makeEligibleAndSubmitted();
        $b = $this->makeEligibleAndSubmitted();
        $c = $this->makeEligibleAndSubmitted();

        $this->actingAs($this->direktur);

        Livewire::test(DirekturBulkKeputusanPage::class)
            ->set('selected', [$a->id, $b->id])
            ->call('openConfirm', 'approve')
            ->call('confirmDecision');

        $this->assertSame('approved', $a->fresh()->status_keputusan_direktur);
        $this->assertSame('approved', $b->fresh()->status_keputusan_direktur);
        $this->assertSame('diajukan', $c->fresh()->status_keputusan_direktur);
    }

    public function test_bulk_page_can_reject_selected_with_note(): void
    {
        $this->setUpActors();
        $a = $this->makeEligibleAndSubmitted();
        $b = $this->makeEligibleAndSubmitted();

        $this->actingAs($this->direktur);

        Livewire::test(DirekturBulkKeputusanPage::class)
            ->set('selected', [$a->id, $b->id])
            ->call('openConfirm', 'reject')
            ->set('note', 'Belum memenuhi kualifikasi')
            ->call('confirmDecision');

        $this->assertSame('rejected', $a->fresh()->status_keputusan_direktur);
        $this->assertSame('rejected', $b->fresh()->status_keputusan_direktur);
    }

    public function test_toggle_select_all(): void
    {
        $this->setUpActors();
        $this->makeEligibleAndSubmitted();
        $this->makeEligibleAndSubmitted();

        $this->actingAs($this->direktur);

        $component = Livewire::test(DirekturBulkKeputusanPage::class)
            ->call('toggleSelectAll');

        $this->assertCount(2, $component->get('selected'));

        $component->call('toggleSelectAll');
        $this->assertCount(0, $component->get('selected'));
    }
}
