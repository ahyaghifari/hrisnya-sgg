<?php

namespace Tests\Feature;

use App\Livewire\EmployeeAttendanceStatistics;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAttendanceStatisticsTest extends TestCase
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

    private function createLeaveRequest(User $user, string $status = 'approved'): AttendanceRequests
    {
        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => $status,
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Tahunan',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
        ]);

        return $request;
    }

    public function test_manager_can_view_subordinate_statistics(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'approved');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSet('status', 'approved')
            ->assertSee($staff->name)
            ->assertSee('Cuti Tahunan');
    }

    public function test_status_filter_excludes_non_matching_requests(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'in_review');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSet('stats', ['leave' => 0, 'permission' => 0, 'overtime' => 0]);
    }

    public function test_stranger_cannot_view_unrelated_employee_statistics(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1]);

        $stranger = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($stranger, $staffPosition);

        $other = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($other, $staffPosition);

        $this->actingAs($stranger);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $other])
            ->assertForbidden();
    }

    public function test_export_returns_download_response(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'approved');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('export')
            ->assertFileDownloaded();
    }
}
