<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\ScheduleExtraShiftTab;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftRule;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tab "Extra Shift" di ScheduleDetailPage - sama alur/validasi dengan ExtraShiftPage/
 * ExtraShiftRequestModal (ResolvesExtraShiftOptions), cuma daftar karyawan & riwayatnya
 * SCOPED ke 1 Schedule tertentu.
 */
class ScheduleExtraShiftTabTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPengelola(string $companyId): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:ExtraShiftAssignment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:ExtraShiftAssignment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:ExtraShiftAssignment', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Schedule Extra Shift Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:ExtraShiftAssignment', 'Create:ExtraShiftAssignment', 'Delete:ExtraShiftAssignment']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $viewer = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($viewer);

        Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $schedule])->assertForbidden();
    }

    public function test_extra_shifts_list_and_employees_are_scoped_to_this_schedule_only(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);

        $scheduleA = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $pagiA = ScheduleShiftType::create(['schedule_id' => $scheduleA->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00', 'end_time' => '14:00']);
        $siangA = ScheduleShiftType::create(['schedule_id' => $scheduleA->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang', 'start_time' => '14:00', 'end_time' => '21:00']);

        $scheduleB = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal B', 'schedule_type' => 'shift']);
        $siangB = ScheduleShiftType::create(['schedule_id' => $scheduleB->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang', 'start_time' => '14:00', 'end_time' => '21:00']);

        $employeeA = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat A']);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $employeeA->id, 'schedule_id' => $scheduleA->id, 'effective_from' => now()->subYear()->toDateString()]);

        $employeeB = User::factory()->create(['company_id' => $company->id, 'name' => 'Perawat B']);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $employeeB->id, 'schedule_id' => $scheduleB->id, 'effective_from' => now()->subYear()->toDateString()]);

        // Extra shift existing punya Schedule A vs B - list harus cuma nampilin milik A.
        $extraShiftA = ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $employeeA->id, 'shift_type_id' => $siangA->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $admin->id]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $employeeB->id, 'shift_type_id' => $siangB->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $admin->id]);

        $this->actingAs($admin);

        $component = Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $scheduleA]);

        $extraShiftIds = $component->get('extraShifts')->pluck('id')->all();
        $this->assertSame([$extraShiftA->id], $extraShiftIds);

        $employeeNames = $component->get('employees')->pluck('name')->all();
        $this->assertContains('Perawat A', $employeeNames);
        $this->assertNotContains('Perawat B', $employeeNames);
    }

    public function test_assigning_extra_shift_scoped_to_schedule_creates_approved_row(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00', 'end_time' => '14:00']);
        $siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang', 'start_time' => '14:00', 'end_time' => '21:00']);

        $employee = User::factory()->create(['company_id' => $company->id]);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $employee->id, 'schedule_id' => $schedule->id, 'effective_from' => now()->subYear()->toDateString()]);

        $date = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $employee->id, 'shift_type_id' => $pagi->id, 'date' => $date, 'off_day' => false]);
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'pagi', 'to_shift_type' => 'siang']);

        $this->actingAs($admin);

        Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $schedule])
            ->call('openAddModal')
            ->set('user_id', $employee->id)
            ->set('date', $date)
            ->set('shift_type_id', $siang->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('extra_shift_assignments', [
            'user_id' => $employee->id,
            'shift_type_id' => $siang->id,
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $admin->id,
        ]);
    }

    /** Karyawan yang SUDAH punya extra shift lain di tanggal itu tidak boleh ditambahkan lagi -
     * lihat ResolvesExtraShiftOptions::resolveAllowedExtraShiftTypes(). */
    public function test_cannot_assign_extra_shift_when_employee_already_has_one_that_day(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00', 'end_time' => '14:00']);
        $siang = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang', 'start_time' => '14:00', 'end_time' => '21:00']);
        $malam = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'MALAM', 'name' => 'Malam', 'shift_type' => 'malam', 'start_time' => '21:00', 'end_time' => '07:00']);

        $employee = User::factory()->create(['company_id' => $company->id]);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $employee->id, 'schedule_id' => $schedule->id, 'effective_from' => now()->subYear()->toDateString()]);

        $date = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $employee->id, 'shift_type_id' => $pagi->id, 'date' => $date, 'off_day' => false]);
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'pagi', 'to_shift_type' => 'siang']);
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'pagi', 'to_shift_type' => 'malam']);

        \App\Models\Shift\ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $siang->id,
            'date' => $date,
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $schedule])
            ->call('openAddModal')
            ->set('user_id', $employee->id)
            ->set('date', $date);

        $this->assertTrue($component->get('alreadyHasExtraShift'));
        $this->assertCount(0, $component->get('shiftTypeOptions'));

        $component->set('shift_type_id', $malam->id)
            ->call('assign');

        $this->assertSame(1, \App\Models\Shift\ExtraShiftAssignment::where('user_id', $employee->id)->whereDate('date', $date)->count());
    }

    /** Middle (shift_type=null sejak enum dihapus, instruksi user 2026-09-14) TIDAK BOLEH ikut
     * Extra Shift sama sekali - baik jadi basis `from` (karyawan yang shift normalnya Middle
     * tidak bisa ditawari extra shift apa pun) maupun `to` (tidak akan pernah muncul di opsi,
     * dicek terpisah di ExtraShiftOncallOverwritesRosterTest karena butuh ExtraShiftRulePage). */
    public function test_employee_on_middle_shift_gets_no_extra_shift_options(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $middle = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'MID', 'name' => 'Middle', 'shift_type' => null, 'start_time' => '10:00', 'end_time' => '18:00']);
        ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang', 'start_time' => '14:00', 'end_time' => '21:00']);

        $employee = User::factory()->create(['company_id' => $company->id]);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $employee->id, 'schedule_id' => $schedule->id, 'effective_from' => now()->subYear()->toDateString()]);

        $date = now()->addDay()->toDateString();
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $employee->id, 'shift_type_id' => $middle->id, 'date' => $date, 'off_day' => false]);
        // Aturan longgar (dari APAPUN ke siang) sengaja dibuat - buat pastikan Middle memang
        // tersaring karena from_shift_type-nya null, bukan cuma kebetulan tidak ada aturan.
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'pagi', 'to_shift_type' => 'siang']);
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'siang', 'to_shift_type' => 'malam']);
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'libur', 'to_shift_type' => 'siang']);

        $this->actingAs($admin);

        $component = Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $schedule])
            ->call('openAddModal')
            ->set('user_id', $employee->id)
            ->set('date', $date);

        $this->assertCount(0, $component->get('shiftTypeOptions'));
    }

    /** Baris hasil eksekusi Tukar Shift (source=shift_swap) TIDAK BOLEH dihapus siapa pun lewat
     * jalur pengelolaan Extra Shift biasa - termasuk yang punya permission Delete penuh - lihat
     * ExtraShiftAssignmentPolicy::delete(). */
    public function test_shift_swap_sourced_row_cannot_be_deleted(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00', 'end_time' => '14:00']);

        $employee = User::factory()->create(['company_id' => $company->id]);

        $extraShift = \App\Models\Shift\ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $pagi->id,
            'date' => now()->addDay()->toDateString(),
            'status' => 'approved',
            'source' => 'shift_swap',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $schedule])
            ->call('delete', $extraShift->id)
            ->assertForbidden();

        $this->assertModelExists($extraShift);
    }

    /** Tanggal yang sudah lewat cuma bisa dihapus lewat halaman Presensi Absensi
     * (ExtraShiftPage) - dari tab Detail Jadwal ini diblok walau permission Delete penuh. */
    public function test_past_dated_row_cannot_be_deleted_from_this_tab(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $admin = $this->actingAsPengelola($company->id);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal A', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00', 'end_time' => '14:00']);

        $employee = User::factory()->create(['company_id' => $company->id]);

        $extraShift = ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $pagi->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ScheduleExtraShiftTab::class, ['schedule' => $schedule])
            ->call('delete', $extraShift->id)
            ->assertForbidden();

        $this->assertModelExists($extraShift);
    }
}
