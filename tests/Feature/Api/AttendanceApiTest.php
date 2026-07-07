<?php

namespace Tests\Feature\Api;

use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Attendance\LeaveUserBalances;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_attendance_endpoints(): void
    {
        $this->getJson('/api/attendance/requests')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_own_attendance_requests(): void
    {
        $companyId = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id;
        $user = User::factory()->create(['company_id' => $companyId]);
        $leaveType = LeaveTypes::create([
            'company_id' => $companyId,
            'name' => 'Cuti Tahunan',
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);

        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'submitted',
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type_id' => $leaveType->id,
            'leave_type' => $leaveType->name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/attendance/requests');

        $response->assertOk();
        $response->assertJsonPath('data.0.type', 'leave');
        $response->assertJsonPath('data.0.status', 'submitted');
        $response->assertJsonPath('data.0.title', 'Cuti Tahunan');
    }

    public function test_list_returns_404_when_user_has_no_requests(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/attendance/requests');

        $response->assertStatus(404);
    }

    public function test_leave_balance_reflects_quota_and_usage(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveTypes::create([
            'company_id' => null,
            'name' => 'Cuti Tahunan',
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);

        LeaveUserBalances::create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'quota' => 12,
            'used' => 4,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/attendance/leave/balance/{$leaveType->id}");

        $response->assertOk();
        $response->assertJsonPath('data.quota', 12);
        $response->assertJsonPath('data.used', 4);
        $response->assertJsonPath('data.available', 8);
    }

    public function test_master_request_types_returns_leave_types(): void
    {
        $companyId = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id;
        $user = User::factory()->create(['company_id' => $companyId]);
        LeaveTypes::create([
            'company_id' => $companyId,
            'name' => 'Cuti Tahunan',
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/master/attendance/request-types?type=leave');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Cuti Tahunan']);
    }
}
