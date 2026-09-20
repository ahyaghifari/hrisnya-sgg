<?php

namespace Tests\Feature\Presensi;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Gerbang halaman Detail Jadwal: Fixed/Flexible cuma View:Schedule, Shift + karyawan yang dipakaikan. */
class ScheduleDetailAccessTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
    }

    private function schedule(string $type): Schedule
    {
        return Schedule::create(['company_id' => $this->company->id, 'name' => "Jadwal $type", 'schedule_type' => $type]);
    }

    private function user(bool $withViewPermission = false): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        if ($withViewPermission) {
            Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
            $user->givePermissionTo('View:Schedule');
        }

        return $user;
    }

    private function assignToShift(User $user, Schedule $schedule): void
    {
        $type = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        ScheduleShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $user->id,
            'shift_type_id' => $type->id, 'date' => now()->toDateString(), 'off_day' => false,
        ]);
    }

    public function test_view_schedule_permission_opens_any_type(): void
    {
        $user = $this->user(true);

        foreach (['fixed', 'flexible', 'shift'] as $type) {
            $this->assertTrue($user->can('view', $this->schedule($type)), $type);
        }
    }

    public function test_fixed_and_flexible_are_closed_without_permission_even_if_assigned(): void
    {
        foreach (['fixed', 'flexible'] as $type) {
            $schedule = $this->schedule($type);
            $user = $this->user();
            ScheduleAssignment::create([
                'company_id' => $this->company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id,
                'effective_from' => now()->toDateString(),
            ]);

            $this->assertFalse($user->can('view', $schedule), $type);
        }
    }

    public function test_shift_is_open_to_designated_head_but_head_of_other_types_is_not(): void
    {
        $head = $this->user();
        $shift = $this->schedule('shift');
        $shift->update(['head_user_id' => $head->id]);
        $fixed = $this->schedule('fixed');
        $fixed->update(['head_user_id' => $head->id]);

        $this->assertTrue($head->can('view', $shift));
        $this->assertFalse($head->can('view', $fixed));
        $this->assertFalse($this->user()->can('view', $shift));
    }

    public function test_shift_is_open_to_assigned_employee_only(): void
    {
        $schedule = $this->schedule('shift');
        $assigned = $this->user();
        $outsider = $this->user();
        $this->assignToShift($assigned, $schedule);

        $this->assertTrue($assigned->can('view', $schedule));
        $this->assertFalse($outsider->can('view', $schedule));
    }
}
