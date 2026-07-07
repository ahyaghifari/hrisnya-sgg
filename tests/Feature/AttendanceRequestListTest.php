<?php

namespace Tests\Feature;

use App\Livewire\MyAttendanceRequests;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceRequestListTest extends TestCase
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

    private function createLeaveRequest(User $user, string $leaveType = 'Cuti Tahunan'): AttendanceRequests
    {
        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'in_review',
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => $leaveType,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
        ]);

        return $request;
    }

    public function test_employee_sees_only_own_history(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($employee, $staffPosition);

        $this->createLeaveRequest($employee, 'Cuti Tahunan');

        $this->actingAs($employee);

        Livewire::test(MyAttendanceRequests::class)
            ->assertSee('Cuti')
            ->assertSee('Cuti Tahunan')
            ->assertDontSee('Nama Karyawan');
    }

    public function test_manager_does_not_see_subordinate_requests_on_own_history_page(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'Cuti Sakit');

        $this->actingAs($manager);

        Livewire::test(MyAttendanceRequests::class)
            ->assertDontSee($staff->name)
            ->assertDontSee('Cuti Sakit');
    }
}
