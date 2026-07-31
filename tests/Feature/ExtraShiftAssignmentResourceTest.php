<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\ExtraShiftAssignments\Pages\ManageExtraShiftAssignments;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sisi admin dari halaman portal Extra Shift - lihat tests/Feature/Presensi/ExtraShiftPageTest.php
 * untuk cakupan lengkap logika assign-nya (query scoping dkk, sama persis reused di sini).
 * Test ini cuma pastikan Resource-nya kebaca & digerbangi permission manual dengan benar.
 */
class ExtraShiftAssignmentResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsGlobalAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']));
        $this->actingAs($admin);
    }

    public function test_user_without_permission_cannot_view_list(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        Livewire::test(ManageExtraShiftAssignments::class)->assertForbidden();
    }

    public function test_global_admin_can_see_extra_shift_records(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '14:00',
            'end_time' => '21:00',
        ]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->actingAsGlobalAdmin();

        $record = ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $siang->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(ManageExtraShiftAssignments::class)
            ->assertCanSeeTableRecords([$record]);
    }

    public function test_extra_shift_assignment_permissions_are_registered(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);

        $this->artisan('db:seed', ['--class' => 'CustomPermissionsSeeder']);

        $names = Permission::where('name', 'like', '%:ExtraShiftAssignment')->pluck('name')->all();

        // Bukan exact match - Filament Shield sendiri bisa nambah verb lain (Restore/
        // ForceDelete/dst) begitu Resource kedeteksi, di luar kendali seeder ini. Yang penting
        // 5 verb yang dipakai ExtraShiftAssignmentPolicy dijamin ada.
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $verb) {
            $this->assertContains("{$verb}:ExtraShiftAssignment", $names);
        }
    }
}
