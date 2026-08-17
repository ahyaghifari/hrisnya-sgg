<?php

namespace Tests\Feature\Coaching;

use App\Livewire\Coaching\CoachingDetailPage;
use App\Livewire\Coaching\CoachingPage;
use App\Livewire\Coaching\CreateCoachingPage;
use App\Models\Coaching\Coaching;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Notifications\CoachingAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Akses "Coaching" (row 2) sekarang punya 2 jalur beda scope (instruksi user 2026-07-26):
 * permission Shield ViewAny:Coaching (HC, company-wide) ATAU sekadar punya bawahan (di-scope
 * ke bawahannya sendiri saja, sama pola MyStaff).
 */
class CoachingSubordinateAccessTest extends TestCase
{
    use RefreshDatabase;

    private function assignJobPosition(User $user, JobPositions $jobPosition): void
    {
        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    /** @return array{0: User, 1: User, 2: User, 3: Companies} manager, subordinate, karyawan lain (bukan bawahan) */
    private function makeManagerWithSubordinate(): array
    {
        // SISTEM_PEMBINAAN_KARYAWAN - gerbang modul dicek duluan di mount() (CreateCoachingPage/
        // CoachingDetailPage dkk) SEBELUM gerbang subordinate-only - tanpa ini abort_unless()
        // 403 di baris pertama, subordinate access-nya sendiri tidak pernah kena tes.
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'guard_name' => 'web']);
        $moduleRole = Role::firstOrCreate(['name' => 'Akses Modul Coaching Test', 'guard_name' => 'web']);
        $moduleRole->givePermissionTo(\App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->assignRole($moduleRole);
        $this->assignJobPosition($manager, $managerPosition);

        $subordinate = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($subordinate, $staffPosition);

        $outsider = User::factory()->create(['company_id' => $company->id]);

        return [$manager, $subordinate, $outsider, $company];
    }

    public function test_coaching_karyawan_lainnya_permission_grants_company_wide_access_without_subordinates(): void
    {
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'HC Coaching Test', 'guard_name' => 'web']);
        $role->givePermissionTo([
            \App\Enum\CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value,
            \App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value,
        ]);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hc = User::factory()->create(['company_id' => $company->id]); // tidak punya bawahan sama sekali
        $hc->assignRole($role);
        $randomEmployee = User::factory()->create(['company_id' => $company->id]);

        Coaching::create([
            'company_id' => $company->id, 'user_id' => $randomEmployee->id, 'coach_by' => $randomEmployee->id,
            'title' => 'Coaching karyawan random', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        // Bisa akses halaman list (bukan 403) walau bukan atasan siapa-siapa...
        Livewire::actingAs($hc)
            ->test(CoachingPage::class)
            ->assertSee('Coaching karyawan random');

        // ...dan bisa bikin coaching buat karyawan mana pun, bukan cuma bawahan (yang memang
        // tidak dia punya sama sekali).
        Livewire::actingAs($hc)
            ->test(CreateCoachingPage::class)
            ->set('user_id', $randomEmployee->id)
            ->set('coach_by', $hc->id)
            ->set('title', 'Coaching dari HC')
            ->set('improvement_due_date', now()->addWeek()->toDateString())
            ->set('goals', 'Tujuan')
            ->set('employee_condition', 'Kondisi')
            ->set('solution', 'Solusi')
            ->set('improvement_action', 'Tindakan')
            ->call('save');

        $this->assertDatabaseHas('coachings', ['user_id' => $randomEmployee->id, 'title' => 'Coaching dari HC']);
    }

    public function test_user_without_subordinates_or_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CoachingPage::class)
            ->assertForbidden();
    }

    public function test_manager_without_permission_only_sees_own_subordinates_coaching(): void
    {
        [$manager, $subordinate, $outsider, $company] = $this->makeManagerWithSubordinate();

        Coaching::create([
            'company_id' => $company->id, 'user_id' => $subordinate->id, 'coach_by' => $manager->id,
            'title' => 'Coaching bawahan', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);
        Coaching::create([
            'company_id' => $company->id, 'user_id' => $outsider->id, 'coach_by' => $outsider->id,
            'title' => 'Coaching karyawan lain', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        $component = Livewire::actingAs($manager)->test(CoachingPage::class);

        $component->assertSee('Coaching bawahan')->assertDontSee('Coaching karyawan lain');
    }

    public function test_manager_can_create_coaching_for_own_subordinate(): void
    {
        [$manager, $subordinate, , $company] = $this->makeManagerWithSubordinate();

        Livewire::actingAs($manager)
            ->test(CreateCoachingPage::class)
            ->set('user_id', $subordinate->id)
            ->set('coach_by', $manager->id)
            ->set('title', 'Perbaikan performa')
            ->set('improvement_due_date', now()->addWeek()->toDateString())
            ->set('goals', 'Tujuan')
            ->set('employee_condition', 'Kondisi')
            ->set('solution', 'Solusi')
            ->set('improvement_action', 'Tindakan')
            ->call('save');

        $this->assertDatabaseHas('coachings', [
            'company_id' => $company->id,
            'user_id' => $subordinate->id,
            'coach_by' => $manager->id,
            'title' => 'Perbaikan performa',
        ]);
    }

    public function test_subordinate_gets_notified_when_assigned_a_coaching(): void
    {
        Notification::fake();
        [$manager, $subordinate] = $this->makeManagerWithSubordinate();

        Livewire::actingAs($manager)
            ->test(CreateCoachingPage::class)
            ->set('user_id', $subordinate->id)
            ->set('coach_by', $manager->id)
            ->set('title', 'Perbaikan performa')
            ->set('improvement_due_date', now()->addWeek()->toDateString())
            ->set('goals', 'Tujuan')
            ->set('employee_condition', 'Kondisi')
            ->set('solution', 'Solusi')
            ->set('improvement_action', 'Tindakan')
            ->call('save');

        Notification::assertSentTo($subordinate, CoachingAssigned::class);
    }

    public function test_deadline_perbaikan_cannot_be_in_the_past(): void
    {
        [$manager, $subordinate] = $this->makeManagerWithSubordinate();

        Livewire::actingAs($manager)
            ->test(CreateCoachingPage::class)
            ->set('user_id', $subordinate->id)
            ->set('coach_by', $manager->id)
            ->set('title', 'Perbaikan performa')
            ->set('improvement_due_date', now()->subDay()->toDateString())
            ->set('goals', 'Tujuan')
            ->set('employee_condition', 'Kondisi')
            ->set('solution', 'Solusi')
            ->set('improvement_action', 'Tindakan')
            ->call('save')
            ->assertHasErrors(['improvement_due_date']);
    }

    public function test_manager_cannot_create_coaching_for_non_subordinate(): void
    {
        [$manager, , $outsider] = $this->makeManagerWithSubordinate();

        Livewire::actingAs($manager)
            ->test(CreateCoachingPage::class)
            ->set('user_id', $outsider->id)
            ->set('coach_by', $manager->id)
            ->set('title', 'Coba tembus')
            ->set('improvement_due_date', now()->addWeek()->toDateString())
            ->set('goals', 'Tujuan')
            ->set('employee_condition', 'Kondisi')
            ->set('solution', 'Solusi')
            ->set('improvement_action', 'Tindakan')
            ->call('save')
            ->assertForbidden();
    }

    public function test_manager_can_view_own_subordinate_coaching_detail(): void
    {
        [$manager, $subordinate, , $company] = $this->makeManagerWithSubordinate();

        $coaching = Coaching::create([
            'company_id' => $company->id, 'user_id' => $subordinate->id, 'coach_by' => $manager->id,
            'title' => 'Coaching bawahan', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        Livewire::actingAs($manager)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertSee('Coaching bawahan');
    }

    /**
     * Regresi: pagination di halaman ini pakai wire:navigate (full page-visit), yang bikin
     * komponen di-mount ULANG dari URL — tanpa #[Url] filter `status` hilang begitu pindah
     * halaman. Test GET langsung (bukan Livewire::test) biar simulasi mount dari URL yang
     * sebenarnya kejadian saat wire:navigate.
     */
    public function test_status_filter_is_read_from_url_on_page_load(): void
    {
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'HC Coaching Pagination Test', 'guard_name' => 'web']);
        $role->givePermissionTo([
            \App\Enum\CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value,
            \App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value,
        ]);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hc = User::factory()->create(['company_id' => $company->id]);
        $hc->assignRole($role);
        $employee = User::factory()->create(['company_id' => $company->id]);

        for ($i = 1; $i <= 15; $i++) {
            Coaching::create([
                'company_id' => $company->id, 'user_id' => $employee->id, 'coach_by' => $hc->id,
                'title' => "Coaching Selesai {$i}", 'improvement_due_date' => now()->addWeek(), 'status' => 'completed',
            ]);
        }
        Coaching::create([
            'company_id' => $company->id, 'user_id' => $employee->id, 'coach_by' => $hc->id,
            'title' => 'Coaching Berjalan', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        $response = $this->actingAs($hc)->get(route('coaching', ['status' => 'completed', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('Coaching Selesai 11');
        $response->assertDontSee('Coaching Berjalan');
    }

    public function test_manager_cannot_view_non_subordinate_coaching_detail(): void
    {
        [$manager, , $outsider, $company] = $this->makeManagerWithSubordinate();

        $coaching = Coaching::create([
            'company_id' => $company->id, 'user_id' => $outsider->id, 'coach_by' => $outsider->id,
            'title' => 'Coaching karyawan lain', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        Livewire::actingAs($manager)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertForbidden();
    }
}
