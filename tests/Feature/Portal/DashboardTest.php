<?php

namespace Tests\Feature\Portal;

use App\Enum\LsmCourseStatus;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Attendance\LeaveUserBalances;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_own_name_on_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Budi Santoso');
    }

    public function test_dashboard_shows_static_placeholder_for_attendance_card(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Belum terintegrasi otomatis');
    }

    public function test_dashboard_renders_nav_and_footer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Riwayat');
        $response->assertSee('Keluar');
        $response->assertSee('Seluruh hak dilindungi');
    }

    public function test_admin_panel_link_hidden_for_regular_employee(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('Admin Panel');
    }

    public function test_admin_panel_link_visible_for_admin_role(): void
    {
        \Spatie\Permission\Models\Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Admin Panel');
    }

    public function test_leave_balance_renders_server_side_without_javascript(): void
    {
        $companyId = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'Test', 'is_active' => true])->id;
        $user = User::factory()->create(['company_id' => $companyId]);
        $leaveType = LeaveTypes::create([
            'company_id' => $companyId,
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

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        // 12 - 4 = 8 hari tersisa, muncul di HTML awal tanpa perlu JS jalan.
        $response->assertSee('8');
        $response->assertSee('/ 12 hari', false);
    }

    public function test_recent_request_renders_server_side_without_javascript(): void
    {
        $companyId = Companies::create(['code' => 'TST2', 'name' => 'Test Co 2', 'location' => 'Test', 'is_active' => true])->id;
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

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Cuti Tahunan');
        $response->assertSee('Submitted');
    }

    public function test_my_trainings_shows_in_progress_and_member_but_not_completed(): void
    {
        $user = User::factory()->create();

        $ongoing = LmsCourses::create(['code' => 'D01', 'title' => 'Pelatihan Berjalan', 'status' => LsmCourseStatus::Published->value]);
        $member = LmsCourses::create(['code' => 'D02', 'title' => 'Pelatihan Belum Mulai', 'status' => LsmCourseStatus::Published->value]);
        $done = LmsCourses::create(['code' => 'D03', 'title' => 'Pelatihan Selesai', 'status' => LsmCourseStatus::Published->value]);

        LmsCourseUsers::create(['user_id' => $user->id, 'course_id' => $ongoing->id, 'status' => 'in_progress', 'progress' => 40, 'score' => 0, 'enrolled_at' => now()]);
        LmsCourseUsers::create(['user_id' => $user->id, 'course_id' => $member->id, 'status' => 'approved', 'progress' => 0, 'score' => 0, 'enrolled_at' => now()]);
        LmsCourseUsers::create(['user_id' => $user->id, 'course_id' => $done->id, 'status' => 'completed', 'progress' => 100, 'score' => 90, 'enrolled_at' => now()]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Pelatihan Saya');
        $response->assertSee('Pelatihan Berjalan');
        $response->assertSee('Pelatihan Belum Mulai');
        $response->assertDontSee('Pelatihan Selesai');
    }
}
