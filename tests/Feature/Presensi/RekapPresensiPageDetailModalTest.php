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
 * Klik baris di mode Periode (bulanan) buka modal rincian absensi harian - SATU baris per
 * tanggal kalender dalam window date_from/date_to (bukan cuma tanggal yang kebetulan punya
 * baris attendance_records), karena approve Cuti/Izin TIDAK otomatis bikin baris
 * attendance_records - kalau modal cuma nampilin baris yang ADA, Cuti multi-hari yang
 * karyawannya tidak pernah scan sepanjang cuti itu (kasus umum) malah hilang dari tabel.
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

    public function test_shows_one_row_per_calendar_date_in_period_with_correct_record_data(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        $otherEmployee = User::factory()->create(['company_id' => $company->id, 'name' => 'Lainnya']);

        // Day 1 & 3 punya baris attendance_records, day 2 sengaja TIDAK punya sama sekali.
        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-05', 'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::PRESENT->value]);
        AttendanceRecords::create(['user_id' => $employee->id, 'nip' => $employee->nip, 'date' => '2026-07-07', 'check_in_time' => '08:15:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::LATE->value, 'late_minutes' => 15]);
        // Karyawan lain - tidak boleh mempengaruhi baris employee ini.
        AttendanceRecords::create(['user_id' => $otherEmployee->id, 'nip' => $otherEmployee->nip, 'date' => '2026-07-06', 'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00', 'status' => AttendanceStatus::PRESENT->value]);

        $this->actingAs($viewer);

        $component = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-05')
            ->set('date_to', '2026-07-07')
            ->call('openDetail', $employee->id)
            ->assertSet('selectedUserId', $employee->id)
            ->assertSee('Budi Santoso');

        $rows = $component->instance()->selectedUserDetailRows;

        $this->assertCount(3, $rows);
        $this->assertSame(['2026-07-05', '2026-07-06', '2026-07-07'], $rows->pluck('date')->all());

        $day1 = $rows->firstWhere('date', '2026-07-05');
        $day2 = $rows->firstWhere('date', '2026-07-06');
        $day3 = $rows->firstWhere('date', '2026-07-07');

        $this->assertTrue($day1->has_record);
        $this->assertSame(AttendanceStatus::PRESENT->value, $day1->status);

        $this->assertFalse($day2->has_record);
        $this->assertNull($day2->status);

        $this->assertTrue($day3->has_record);
        $this->assertSame(AttendanceStatus::LATE->value, $day3->status);
    }

    /**
     * Reproduksi laporan bug: Cuti 3 hari (range) yang karyawannya sama sekali tidak scan
     * sepanjang cuti itu (tidak ada baris attendance_records) - sebelumnya hilang total dari
     * modal karena modal cuma nampilin baris yang ADA di attendance_records. Sekarang harus
     * tetap muncul (via full-calendar), dan ke-cocokkan ke pengajuan Cuti-nya lewat
     * selectedUserLeaveOrPermissionDates untuk SEMUA tanggal di rentang cuti itu, bukan cuma
     * tanggal awal/akhirnya.
     */
    public function test_multi_day_leave_range_with_zero_attendance_records_is_still_matched(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        // TIDAK ada baris attendance_records sama sekali untuk 2026-07-10 s/d 2026-07-12.
        $request = AttendanceRequests::create(['user_id' => $employee->id, 'type' => 'leave', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestLeaveDetails::create(['request_id' => $request->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-12', 'total_days' => 3]);

        $this->actingAs($viewer);

        $map = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->instance()
            ->selectedUserLeaveOrPermissionDates;

        foreach (['2026-07-10', '2026-07-11', '2026-07-12'] as $date) {
            $this->assertArrayHasKey($date, $map, "Tanggal {$date} (di tengah rentang cuti) harusnya ikut ke-cocokkan.");
            $this->assertSame('leave', $map[$date]['type']);
        }
    }

    /** Cuti yang MULAI sebelum periode terpilih dan berakhir DI DALAM periode - tetap harus
     * ke-cocokkan untuk bagian yang overlap dengan periode. */
    public function test_leave_range_starting_before_selected_period_is_clamped_and_matched(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $request = AttendanceRequests::create(['user_id' => $employee->id, 'type' => 'leave', 'status' => AttendanceReqStatus::APPROVED->value]);
        AttendanceRequestLeaveDetails::create(['request_id' => $request->id, 'start_date' => '2026-06-28', 'end_date' => '2026-07-03', 'total_days' => 6]);

        $this->actingAs($viewer);

        $map = Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('openDetail', $employee->id)
            ->instance()
            ->selectedUserLeaveOrPermissionDates;

        $this->assertArrayNotHasKey('2026-06-28', $map); // di luar periode, tidak usah muncul di map
        $this->assertArrayHasKey('2026-07-01', $map);
        $this->assertArrayHasKey('2026-07-03', $map);
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
