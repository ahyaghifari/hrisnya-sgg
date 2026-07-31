<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\ExtraShiftPage;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Versi pertama Extra Shift - cuma jalur `assigned` (atasan input langsung, auto approved),
 * lihat planning/2026-07-31-fitur-extra-shift.md.
 */
class ExtraShiftPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPengelola(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Extra Shift Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, CustomPermissionEnum::KELOLA_JADWAL_SHIFT->value]);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeShiftEmployee(Companies $company): array
    {
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00',
            'end_time' => '14:00',
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '14:00',
            'end_time' => '21:00',
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        ScheduleAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'effective_from' => now()->subYear()->toDateString(),
        ]);

        return compact('schedule', 'pagi', 'siang', 'employee');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(ExtraShiftPage::class)->assertForbidden();
    }

    public function test_shift_types_are_scoped_to_covering_employees_own_schedule(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);
        ['employee' => $employee] = $this->makeShiftEmployee($company);
        ['pagi' => $pagi, 'siang' => $siang, 'employee' => $colleague] = $this->makeShiftEmployee($company);

        $this->actingAs($admin);

        $component = Livewire::test(ExtraShiftPage::class)
            ->set('user_id', $employee->id)
            ->set('covering_user_id', $colleague->id);

        $shiftTypeIds = $component->get('shiftTypes')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pagi->id, $siang->id], $shiftTypeIds);
    }

    public function test_assigning_extra_shift_creates_approved_row_immediately(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);
        ['employee' => $employee] = $this->makeShiftEmployee($company);
        ['siang' => $siang, 'employee' => $colleague] = $this->makeShiftEmployee($company);

        $this->actingAs($admin);

        Livewire::test(ExtraShiftPage::class)
            ->set('user_id', $employee->id)
            ->set('covering_user_id', $colleague->id)
            ->set('shift_type_id', $siang->id)
            ->set('date', '2026-08-10')
            ->set('reason', 'Rekan shift siang izin')
            ->call('assign')
            ->assertHasNoErrors();

        $row = ExtraShiftAssignment::where('user_id', $employee->id)->first();

        $this->assertNotNull($row);
        $this->assertSame('approved', $row->status);
        $this->assertSame('assigned', $row->source);
        $this->assertSame($colleague->id, $row->covering_user_id);
        $this->assertSame($admin->id, $row->created_by);
    }

    public function test_user_without_kelola_jadwal_shift_cannot_assign(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Read Only Absensi', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->assignRole($role);

        ['siang' => $siang, 'employee' => $employee] = $this->makeShiftEmployee($company);

        $this->actingAs($viewer);

        Livewire::test(ExtraShiftPage::class)
            ->set('user_id', $employee->id)
            ->set('shift_type_id', $siang->id)
            ->set('date', '2026-08-10')
            ->call('assign')
            ->assertForbidden();
    }

    public function test_extra_shift_list_is_scoped_per_company(): void
    {
        $companyA = Companies::create(['code' => 'AAA', 'name' => 'Company A', 'location' => 'Test', 'is_active' => true]);
        $companyB = Companies::create(['code' => 'BBB', 'name' => 'Company B', 'location' => 'Test', 'is_active' => true]);

        $adminA = $this->actingAsPengelola($companyA->id);
        ['siang' => $siangA, 'employee' => $employeeA] = $this->makeShiftEmployee($companyA);
        ['siang' => $siangB, 'employee' => $employeeB] = $this->makeShiftEmployee($companyB);

        ExtraShiftAssignment::create([
            'company_id' => $companyA->id,
            'user_id' => $employeeA->id,
            'shift_type_id' => $siangA->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $adminA->id,
        ]);
        ExtraShiftAssignment::create([
            'company_id' => $companyB->id,
            'user_id' => $employeeB->id,
            'shift_type_id' => $siangB->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $adminA->id,
        ]);

        $this->actingAs($adminA);

        $rows = Livewire::test(ExtraShiftPage::class)
            ->set('companyId', $companyA->id)
            ->get('extraShifts');

        $this->assertCount(1, $rows);
        $this->assertSame($employeeA->id, $rows->first()->user_id);
    }
}
