<?php

namespace Tests\Feature\Api;

use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
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
        $leaveType = LeaveTypeCategoryEnum::TAHUNAN;

        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'submitted',
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'category' => $leaveType->value,
            'leave_type' => $leaveType->label(),
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
        $leaveType = LeaveTypeCategoryEnum::TAHUNAN;

        // "used" dihitung live dari request APPROVED dalam periode (tabel leave_user_balances
        // sudah dihapus) — lihat LeaveReqRepository::getLeaveUserBalance().
        $approvedRequest = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'approved',
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $approvedRequest->id,
            'category' => $leaveType->value,
            'leave_type' => $leaveType->label(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_days' => 4,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/attendance/leave/balance/{$leaveType->value}");

        $response->assertOk();
        $response->assertJsonPath('data.quota', 12);
        $response->assertJsonPath('data.used', 4);
        $response->assertJsonPath('data.available', 8);
    }

    public function test_master_request_types_returns_leave_types(): void
    {
        $companyId = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id;
        $user = User::factory()->create(['company_id' => $companyId]);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/master/attendance/request-types?type=leave');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'tahunan', 'name' => 'Cuti Tahunan']);
        $response->assertJsonCount(4, 'data');
    }

    public function test_guest_can_access_holiday_endpoints(): void
    {
        $this->getJson('/api/attendance/holidays')->assertOk();
    }

    public function test_authenticated_user_can_list_holidays_and_collective_leaves(): void
    {
        $user = User::factory()->create();
        Holidays::create([
            'date' => '2026-07-20',
            'name' => 'Hari Raya Idul Adha',
            'is_collective_leave' => true,
        ]);
        Holidays::create([
            'date' => '2026-07-21',
            'name' => 'Hari Libur Nasional',
            'is_collective_leave' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/attendance/holidays?from=2026-07-01&to=2026-07-31');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'Hari Raya Idul Adha', 'is_collective_leave' => true]);
        $response->assertJsonFragment(['name' => 'Hari Libur Nasional', 'is_collective_leave' => false]);
    }

    public function test_independence_day_api_returns_hari_kemerdekaan(): void
    {
        Holidays::create([
            'date' => '2026-08-17',
            'name' => 'Hari Kemerdekaan',
            'is_collective_leave' => false,
        ]);

        $response = $this->getJson('/api/attendance/holidays/kemerdekaan');

        $response->assertOk();
        $response->assertJsonPath('data.date', '2026-08-17');
        $response->assertJsonPath('data.name', 'Hari Kemerdekaan');
        $response->assertJsonPath('data.is_collective_leave', false);
    }
}
