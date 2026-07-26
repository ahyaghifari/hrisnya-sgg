<?php

namespace Tests\Feature\Counseling;

use App\Livewire\Counseling\CounselingDetailPage;
use App\Livewire\Counseling\CounselingManagePage;
use App\Livewire\Counseling\CounselingPage;
use App\Models\Counseling\Counseling;
use App\Models\Master\Companies;
use App\Models\User;
use App\Notifications\CounselingReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Karyawan menunjuk sendiri Konselor saat submit Konsul — isi keluhan/tanggapan rahasia,
 * cuma dia dan Konselor yang ditunjuk yang boleh baca, TERMASUK user dengan permission Shield
 * ViewAny:Counseling/Update:Counseling (mis. Super Admin) tidak otomatis bisa lihat.
 */
class CounselingConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCounselorPermissionUser(Companies $company): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:Counseling', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Counseling', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Punya Semua Permission Counseling', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:Counseling', 'Update:Counseling']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_employee_can_pick_own_counselor_when_submitting(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->call('openConsultModal')
            ->set('issue', 'Ada masalah dengan rekan kerja.')
            ->set('counselorId', $counselor->id)
            ->call('submitConsult');

        $this->assertDatabaseHas('counselings', [
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'issue' => 'Ada masalah dengan rekan kerja.',
        ]);
    }

    public function test_counselor_gets_notified_when_consulted(): void
    {
        Notification::fake();
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);

        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->set('issue', 'Ada masalah dengan rekan kerja.')
            ->set('counselorId', $counselor->id)
            ->call('submitConsult');

        Notification::assertSentTo($counselor, CounselingReceived::class);
    }

    public function test_employee_cannot_submit_consult_to_self_even_via_tampered_request(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        // Dropdown UI sudah exclude diri sendiri — ini simulasi wire:model ditembak manual.
        Livewire::actingAs($employee)
            ->test(CounselingPage::class)
            ->set('issue', 'Coba konsul ke diri sendiri.')
            ->set('counselorId', $employee->id)
            ->call('submitConsult')
            ->assertHasErrors(['counselorId']);

        $this->assertDatabaseMissing('counselings', ['user_id' => $employee->id, 'counselor_id' => $employee->id]);
    }

    public function test_assigned_counselor_can_view_and_respond(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'company_id' => $company->id,
            'issue' => 'Keluhan rahasia.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($counselor)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertSet('response', '')
            ->set('response', 'Sudah ditindaklanjuti.')
            ->set('solution', 'Mediasi dengan rekan kerja.')
            ->call('submitResponse');

        $this->assertDatabaseHas('counselings', [
            'id' => $record->id,
            'response' => 'Sudah ditindaklanjuti.',
            'status' => 'done',
        ]);
    }

    public function test_non_counselor_with_full_permission_cannot_view_others_counseling(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $bystander = $this->makeCounselorPermissionUser($company);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'company_id' => $company->id,
            'issue' => 'Keluhan rahasia.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($bystander)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertForbidden();
    }

    public function test_manage_page_only_lists_counseling_assigned_to_current_user_as_counselor(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $bystander = $this->makeCounselorPermissionUser($company);

        $ownRecord = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'company_id' => $company->id,
            'issue' => 'Punya konselor ini.',
            'status' => 'pending',
        ]);
        Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $bystander->id,
            'company_id' => $company->id,
            'issue' => 'Punya konselor lain.',
            'status' => 'pending',
        ]);

        $counselings = Livewire::actingAs($counselor)
            ->test(CounselingManagePage::class)
            ->instance()
            ->counselings;

        $this->assertCount(1, $counselings);
        $this->assertSame($ownRecord->id, $counselings->first()->id);
    }

    public function test_inbox_button_shown_only_for_non_supervisor_counselor_with_pending_consult(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselorWithoutSubordinates = User::factory()->create(['company_id' => $company->id]);

        Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselorWithoutSubordinates->id,
            'company_id' => $company->id,
            'issue' => 'Keluhan.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($counselorWithoutSubordinates)
            ->test(CounselingPage::class)
            ->assertSee('Konsul ke Saya');

        // Karyawan yang belum pernah ditunjuk jadi konselor sama sekali tidak lihat tombolnya.
        $neverCounselor = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($neverCounselor)
            ->test(CounselingPage::class)
            ->assertDontSee('Konsul ke Saya');
    }

    public function test_submitter_can_view_own_consult_detail_read_only(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);
        $record = Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'company_id' => $company->id,
            'issue' => 'Keluhan saya sendiri.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($employee)
            ->test(CounselingDetailPage::class, ['record' => $record])
            ->assertSee('Keluhan saya sendiri.')
            ->assertSet('isCounselor', false)
            ->assertSet('canRespond', false);
    }

    public function test_inbox_button_hidden_when_all_assigned_consults_already_done(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $counselor = User::factory()->create(['company_id' => $company->id]);

        Counseling::create([
            'user_id' => $employee->id,
            'counselor_id' => $counselor->id,
            'company_id' => $company->id,
            'issue' => 'Sudah selesai ditanggapi.',
            'status' => 'done',
        ]);

        Livewire::actingAs($counselor)
            ->test(CounselingPage::class)
            ->assertDontSee('Konsul ke Saya');
    }
}
