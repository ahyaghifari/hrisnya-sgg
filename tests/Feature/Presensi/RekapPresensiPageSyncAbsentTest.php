<?php

namespace Tests\Feature\Presensi;

use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * syncPeriodAttendance() UPDATE baris attendance_records yang SUDAH ADA di periode terpilih -
 * (1) check_in_time ATAU check_out_time null jadi 'absent', KECUALI baris yang statusnya sudah
 * leave/sick/permit (Cuti/Sakit/Izin disetujui - wajar juga null, jangan ditimpa jadi "mangkir").
 * (2) working_minutes dihitung ULANG dari durasi mentah check_out - check_in (istirahat ikut
 * terhitung, tidak dipotong) untuk SEMUA baris yang punya check-in & check-out, termasuk yang
 * sudah punya nilai working_minutes sebelumnya.
 */
class RekapPresensiPageSyncAbsentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SYNC_DATA_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Sync Absent Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, CustomPermissionEnum::SYNC_DATA_ABSENSI->value]);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function record(User $user, string $date, ?string $checkIn, ?string $checkOut, string $status): AttendanceRecords
    {
        return AttendanceRecords::create([
            'user_id' => $user->id,
            'nip' => $user->nip,
            'date' => $date,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'status' => $status,
        ]);
    }

    public function test_marks_incomplete_records_as_absent_but_excludes_approved_leave(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $noCheckIn = $this->record($employee, '2026-07-05', null, '17:00:00', AttendanceStatus::PRESENT->value);
        $noCheckOut = $this->record($employee, '2026-07-06', '08:00:00', null, AttendanceStatus::PRESENT->value);
        $complete = $this->record($employee, '2026-07-07', '08:00:00', '17:00:00', AttendanceStatus::PRESENT->value);
        $onLeave = $this->record($employee, '2026-07-08', null, null, AttendanceStatus::LEAVE->value);
        $onSick = $this->record($employee, '2026-07-09', null, null, AttendanceStatus::SICK->value);
        $onPermit = $this->record($employee, '2026-07-10', null, null, AttendanceStatus::PERMIT->value);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('syncPeriodAttendance');

        $this->assertSame(AttendanceStatus::ABSENT->value, $noCheckIn->refresh()->status);
        $this->assertSame(AttendanceStatus::ABSENT->value, $noCheckOut->refresh()->status);
        $this->assertSame(AttendanceStatus::PRESENT->value, $complete->refresh()->status);
        $this->assertSame(AttendanceStatus::LEAVE->value, $onLeave->refresh()->status);
        $this->assertSame(AttendanceStatus::SICK->value, $onSick->refresh()->status);
        $this->assertSame(AttendanceStatus::PERMIT->value, $onPermit->refresh()->status);
    }

    public function test_does_not_touch_records_outside_selected_period(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $outsideRange = $this->record($employee, '2026-08-01', null, '17:00:00', AttendanceStatus::PRESENT->value);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('syncPeriodAttendance');

        $this->assertSame(AttendanceStatus::PRESENT->value, $outsideRange->refresh()->status);
    }

    public function test_recalculates_working_minutes_as_raw_duration_including_break(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        // 08:00 - 17:00 = 540 menit mentah (9 jam), TIDAK dipotong istirahat sama sekali.
        $record = $this->record($employee, '2026-07-05', '08:00:00', '17:00:00', AttendanceStatus::PRESENT->value);
        AttendanceRecords::where('id', $record->id)->update(['working_minutes' => 480]); // nilai lama (sudah dipotong 1 jam istirahat) - harus ditimpa

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('syncPeriodAttendance');

        $this->assertSame(540, $record->refresh()->working_minutes);
    }

    public function test_recalculates_working_minutes_across_midnight(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        // Shift malam: masuk 22:00, pulang 06:00 (hari berikutnya) = 480 menit.
        $record = $this->record($employee, '2026-07-05', '22:00:00', '06:00:00', AttendanceStatus::PRESENT->value);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('syncPeriodAttendance');

        $this->assertSame(480, $record->refresh()->working_minutes);
    }

    public function test_does_not_recalculate_working_minutes_when_check_out_missing(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $record = $this->record($employee, '2026-07-05', '08:00:00', null, AttendanceStatus::PRESENT->value);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('syncPeriodAttendance');

        $this->assertNull($record->refresh()->working_minutes);
    }

    public function test_user_without_sync_permission_cannot_run_it(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekap Only Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->assignRole($role);

        $this->actingAs($viewer);

        Livewire::test(RekapPresensiPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->call('syncPeriodAttendance')
            ->assertForbidden();
    }
}
