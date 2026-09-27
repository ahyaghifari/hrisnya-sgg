<?php

namespace Tests\Feature\Portal;

use App\Enum\CustomPermissionEnum;
use App\Enum\LsmCourseStatus;
use App\Enum\WidgetPermissionEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    // Card "Pengajuan Absensi Saya"/"Pelatihan Saya" di home-cards sekarang digerbangi
    // permission ini (lihat App\Livewire\Dashboard::getCanPengajuanAbsensiProperty()/
    // getCanPelatihanSayaProperty()) - user factory mentah tidak otomatis punya permission apa
    // pun, jadi test yang mengasumsikan card itu tampil harus grant eksplisit di sini.
    private function grantPermission(User $user, CustomPermissionEnum|WidgetPermissionEnum $permission): void
    {
        Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Dashboard Test ' . $permission->value, 'guard_name' => 'web']);
        $role->givePermissionTo($permission->value);
        $user->assignRole($role);
    }

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

    public function test_pengajuan_card_hidden_without_permission_and_absensi_spans_full_width(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('bento-status-pengajuan', false);
        $response->assertSee('no-pengajuan', false);
    }

    public function test_pengajuan_card_visible_with_permission(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('bento-status-pengajuan', false);
        $response->assertDontSee('no-pengajuan', false);
    }

    public function test_pelatihan_card_hidden_without_permission_and_tugas_spans_full_width(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('Pelatihan Saya');
        $response->assertSee('no-pelatihan', false);
    }

    /** Widget "Jadwal Shift Tim" (TeamShiftScheduleCard) digerbangi WidgetPermissionEnum
     *  (2026-08-19) - lihat Dashboard::getCanJadwalShiftTimProperty(). */
    public function test_jadwal_shift_tim_widget_hidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('Jadwal Shift Tim');
    }

    public function test_jadwal_shift_tim_widget_visible_with_permission(): void
    {
        // Widget-nya sendiri (lihat team-shift-schedule-card.blade.php) MEMBUNGKUS SELURUH
        // isinya (termasuk judul "Jadwal Shift Tim") dalam `@if ($this->teamMembers->isNotEmpty())`
        // - user tanpa bawahan sama sekali tidak akan pernah melihat judulnya walau permission-nya
        // sudah di-grant, jadi butuh minimal 1 bawahan di sini supaya assertion ini valid.
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $user = User::factory()->create(['company_id' => $company->id]);
        UserJobPositions::create(['user_id' => $user->id, 'job_position_id' => $managerPosition->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        $staff = User::factory()->create(['company_id' => $company->id]);
        UserJobPositions::create(['user_id' => $staff->id, 'job_position_id' => $staffPosition->id, 'start_date' => now()->subYear(), 'status' => 'active']);

        $this->grantPermission($user, WidgetPermissionEnum::JADWAL_SHIFT_TIM);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Jadwal Shift Tim');
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
        $leaveType = LeaveTypeCategoryEnum::TAHUNAN;
        // "used" dihitung live dari request APPROVED dalam periode (tabel leave_user_balances
        // sudah dihapus). Lihat LeaveReqRepository::getLeaveUserBalance().
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

        $this->grantPermission($user, CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA);

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

        $this->grantPermission($user, CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA);

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

        $this->grantPermission($user, CustomPermissionEnum::PELATIHAN_SAYA);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Pelatihan Saya');
        $response->assertSee('Pelatihan Berjalan');
        $response->assertSee('Pelatihan Belum Mulai');
        $response->assertDontSee('Pelatihan Selesai');
    }

    // Widget "Jadwal Shift Saya" cuma buat karyawan yang jadwalnya beneran tipe Shift - dicek
    // dari data jadwal langsung, bukan permission JADWAL_SHIFT_SAYA lagi (2026-08-15, permission
    // itu tidak pernah di-assign ke role manapun), lihat Dashboard::getCanJadwalShiftSayaProperty().
    public function test_jadwal_shift_widget_visible_for_shift_schedule_employee(): void
    {
        $company = Companies::create(['code' => 'SFT', 'name' => 'RS Shift', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false, 'color' => '#22c55e']);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => now()->toDateString(), 'off_day' => false]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Jadwal Shift Saya');
    }

    public function test_jadwal_shift_widget_hidden_for_fixed_schedule_employee(): void
    {
        $company = Companies::create(['code' => 'FXD', 'name' => 'Kantor Fixed', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Kantor', 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => now()->dayOfWeek, 'is_off_day' => false, 'start_time' => '08:00:00', 'end_time' => '17:00:00']);
        ScheduleAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => now()->subYear()->toDateString()]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('Jadwal Shift Saya');
    }

    public function test_jadwal_shift_widget_hidden_without_any_schedule_assigned(): void
    {
        $company = Companies::create(['code' => 'NON', 'name' => 'Belum Ada Jadwal', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('Jadwal Shift Saya');
    }
}
