<?php

namespace Tests\Feature\Sikartap;

use App\Enum\OrgStructurLevel;
use App\Enum\Sikartap\SikartapSettingEnum;
use App\Livewire\PendingApprovalsCard;
use App\Livewire\Sikartap\DirekturBulkIdeInovasiPage;
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

/** Pengesahan Direktur atas Ide Inovasi — massal + pengelompokan widget "Butuh Persetujuan Anda". */
class DirekturBulkIdeInovasiTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $direktur;

    private function setUpActors(): void
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $dept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Direksi', 'level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        $pos = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Direktur Utama', 'planned_manpower' => 1, 'org_structure_id' => $dept->id]);

        $this->direktur = User::factory()->create(['company_id' => $this->company->id]);
        UserJobPositions::create(['user_id' => $this->direktur->id, 'job_position_id' => $pos->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        $role = Role::firstOrCreate(['name' => 'Direktur'], ['guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::NILAI_IDE_INOVASI_DIREKTUR->value, 'guard_name' => 'web']));
        $this->direktur->assignRole($role);

        \App\Models\Master\CompanyDirector::updateOrCreate(
            ['company_id' => $this->company->id],
            ['job_position_id' => $pos->id],
        );
        Setting::updateOrCreate(
            ['key' => SikartapSettingEnum::WAJIB_IDE_INOVASI->value, 'company_id' => $this->company->id],
            ['module' => 'sikartap', 'type' => 'boolean', 'label' => 'Wajib Ide Inovasi', 'value' => '1'],
        );
        Setting::updateOrCreate(
            ['key' => SikartapSettingEnum::IDE_INOVASI_PENGESAHAN_DIREKTUR->value, 'company_id' => $this->company->id],
            ['module' => 'sikartap', 'type' => 'boolean', 'label' => 'Pengesahan Direktur', 'value' => '1'],
        );
    }

    private function makeIdePendingDirektur(): Sikartap
    {
        $applicant = User::factory()->create(['company_id' => $this->company->id]);
        $sikartap = Sikartap::create([
            'user_id' => $applicant->id,
            'status_berkas' => 'approved', 'berkas_lengkap' => true,
            'status_tes' => 'lulus', 'status_wawancara' => 'disarankan', 'status_mcu' => 'fit',
        ]);
        $sikartap->ideInovasi()->create([
            'file_ide_inovasi' => 'sikartap/ide-inovasi/fake.pdf',
            'status_atasan' => 'approved',
            'reviewed_at_atasan' => now(),
        ]);

        return $sikartap;
    }

    public function test_widget_single_goes_to_detail(): void
    {
        $this->setUpActors();
        $sikartap = $this->makeIdePendingDirektur();

        $this->actingAs($this->direktur);
        $row = Livewire::test(PendingApprovalsCard::class)->viewData('requests')->firstWhere('module', 'sikartap_ide_inovasi_direktur');

        $this->assertNotNull($row);
        $this->assertSame('1 ide inovasi', $row->keterangan);
        $this->assertSame(route('sikartap.pengajuan.show', $sikartap), $row->url);
    }

    public function test_widget_multiple_goes_to_bulk(): void
    {
        $this->setUpActors();
        $this->makeIdePendingDirektur();
        $this->makeIdePendingDirektur();

        $this->actingAs($this->direktur);
        $row = Livewire::test(PendingApprovalsCard::class)->viewData('requests')->firstWhere('module', 'sikartap_ide_inovasi_direktur');

        $this->assertNotNull($row);
        $this->assertSame('2 ide inovasi', $row->keterangan);
        $this->assertSame(route('sikartap.ide-inovasi.direktur.bulk'), $row->url);
    }

    public function test_widget_hidden_when_setting_off(): void
    {
        $this->setUpActors();
        Setting::where('company_id', $this->company->id)
            ->where('key', SikartapSettingEnum::IDE_INOVASI_PENGESAHAN_DIREKTUR->value)
            ->update(['value' => '0']);
        $this->makeIdePendingDirektur();

        $this->actingAs($this->direktur);
        $row = Livewire::test(PendingApprovalsCard::class)->viewData('requests')->firstWhere('module', 'sikartap_ide_inovasi_direktur');

        $this->assertNull($row);
    }

    public function test_bulk_page_404_when_nothing_pending(): void
    {
        $this->setUpActors();
        $this->actingAs($this->direktur);

        Livewire::test(DirekturBulkIdeInovasiPage::class)->assertStatus(404);
    }

    public function test_bulk_approve_and_reject(): void
    {
        $this->setUpActors();
        $a = $this->makeIdePendingDirektur();
        $b = $this->makeIdePendingDirektur();
        $c = $this->makeIdePendingDirektur();

        $this->actingAs($this->direktur);

        Livewire::test(DirekturBulkIdeInovasiPage::class)
            ->set('selected', [$a->id, $b->id])
            ->call('openConfirm', 'approve')
            ->call('confirmDecision');

        $this->assertSame('approved', $a->fresh()->ideInovasi->status_direktur);
        $this->assertSame('approved', $b->fresh()->ideInovasi->status_direktur);
        $this->assertSame('pending', $c->fresh()->ideInovasi->status_direktur);
        $this->assertTrue($a->fresh()->ideInovasiApproved());

        Livewire::test(DirekturBulkIdeInovasiPage::class)
            ->set('selected', [$c->id])
            ->call('openConfirm', 'reject')
            ->set('note', 'Perlu revisi analisis')
            ->call('confirmDecision');

        $this->assertSame('rejected', $c->fresh()->ideInovasi->status_direktur);
        $this->assertSame('Perlu revisi analisis', $c->fresh()->ideInovasi->catatan_direktur);
        $this->assertFalse($c->fresh()->ideInovasiApproved());
    }

    public function test_bulk_reject_requires_note(): void
    {
        $this->setUpActors();
        $a = $this->makeIdePendingDirektur();

        $this->actingAs($this->direktur);

        Livewire::test(DirekturBulkIdeInovasiPage::class)
            ->set('selected', [$a->id])
            ->call('openConfirm', 'reject')
            ->call('confirmDecision')
            ->assertHasErrors('note');

        $this->assertSame('pending', $a->fresh()->ideInovasi->status_direktur);
    }
}
