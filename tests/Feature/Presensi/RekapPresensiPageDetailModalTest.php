<?php

namespace Tests\Feature\Presensi;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Klik baris di mode Periode (bulanan) buka modal rincian absensi harian - cuma baris
 * attendance_records karyawan itu, dalam window date_from/date_to yang lagi ditampilkan.
 */
class RekapPresensiPageDetailModalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekap Presensi Detail Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_clicking_row_shows_only_that_users_records_within_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        $otherEmployee = User::factory()->create(['company_id' => $company->id, 'name' => 'Lainnya']);

        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-05', 'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::PRESENT->value]);
        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-06', 'check_in_time' => '08:15:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::LATE->value, 'late_minutes' => 15]);
        // Di luar periode - tidak boleh ikut muncul.
        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-08-01', 'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::PRESENT->value]);
        // Karyawan lain - tidak boleh ikut muncul.
        AttendanceRecords::create(['user_id' => $otherEmployee->id, 'nip' => $otherEmployee->nip, 'date' => '2026-07-05', 'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::PRESENT->value]);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->assertSet('selectedUserId', $employee->id)
            ->assertSee('Budi Santoso');

        $component = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id);

        $rows = $component->instance()->selectedUserDetailRows;

        $this->assertCount(2, $rows);
        $this->assertSame(['2026-07-05', '2026-07-06'], $rows->pluck('date')->map(fn ($d) => (string) $d)->all());
    }

    public function test_absent_day_with_approved_leave_is_flagged_as_possible_leave(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-10', 'status' => AttendanceStatus::ABSENT->value]);

        $request = AttendanceRequests::create(['user_id' => $employee->id, 'type' => 'leave', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestLeaveDetails::create(['request_id' => $request->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-10', 'total_days' => 1]);

        $this->actingAs($viewer);

        $map = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->instance()
            ->selectedUserLeaveOrPermissionDates;

        $this->assertArrayHasKey('2026-07-10', $map);
        $this->assertSame('leave', $map['2026-07-10']['type']);
    }

    public function test_absent_day_with_pending_permission_is_flagged_as_possible(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-12', 'status' => AttendanceStatus::ABSENT->value]);

        $request = AttendanceRequests::create(['user_id' => $employee->id, 'type' => 'permission', 'status' => AttendanceReqStatus::SUBMITTED->value]);
        AttendanceRequestPermissionDetails::create(['request_id' => $request->id, 'date' => '2026-07-12']);

        $this->actingAs($viewer);

        $map = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->instance()
            ->selectedUserLeaveOrPermissionDates;

        $this->assertArrayHasKey('2026-07-12', $map);
        $this->assertSame('permission', $map['2026-07-12']['type']);
    }

    public function test_absent_day_with_rejected_leave_is_not_flagged(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-15', 'status' => AttendanceStatus::ABSENT->value]);

        $request = AttendanceRequests::create(['user_id' => $employee->id, 'type' => 'leave', 'status' => AttendanceReqStatus::REJECTED->value]);
        AttendanceRequestLeaveDetails::create(['request_id' => $request->id, 'start_date' => '2026-07-15', 'end_date' => '2026-07-15', 'total_days' => 1]);

        $this->actingAs($viewer);

        $map = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->instance()
            ->selectedUserLeaveOrPermissionDates;

        $this->assertArrayNotHasKey('2026-07-15', $map);
    }

    public function test_absent_day_without_any_request_is_not_flagged(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-20', 'status' => AttendanceStatus::ABSENT->value]);

        $this->actingAs($viewer);

        $map = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->instance()
            ->selectedUserLeaveOrPermissionDates;

        $this->assertSame([], $map);
    }

    public function test_close_detail_clears_selected_user(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->call('openDetail', $employee->id)
            ->assertSet('selectedUserId', $employee->id)
            ->call('closeDetail')
            ->assertSet('selectedUserId', null);
    }
}
