<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\ScheduleShiftRosterTab;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Refund kuota Cuti Tahunan/Haid kalau tanggal yang sudah kepotong ternyata BELAKANGAN jadi Libur
 * lewat roster biasa (manual/Isi Cepat/Import/Generate) - bukan lewat approval Cuti/Izin sendiri.
 * Instruksi user 2026-09-24: karyawan sudah punya hak libur tanggal itu, jangan sampai kepotong
 * cuti juga. Lihat ScheduleShiftRosterTab::refundLeaveDayIfBecameOffDay().
 */
class ScheduleShiftRosterLeaveQuotaRefundTest extends TestCase
{
    use RefreshDatabase;

    /** Punya Update:Schedule juga (bukan cuma Update:ShiftScheduleAssignments) - sejak 2026-09-28
     * yang tidak punya Update:Schedule diajukan ke approval dulu lewat saveRoster() (lihat
     * ScheduleRosterChangeApprovalTest), refund kuota baru terjadi begitu disetujui. Test refund
     * di file ini soal RosterBatchWriter, bukan soal gerbang approval, jadi tetap pakai user yang
     * langsung tersimpan. */
    private function actingAsAbsensi(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Roster Refund Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule', 'Update:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeManualShiftSchedule(): array
    {
        $company = Companies::create(['code' => 'RFND', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Manual',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
        ]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);

        return [$schedule, $shiftType, $employee];
    }

    /** Pengajuan Cuti Tahunan APPROVED yang sudah "kepotong" $totalDays hari kerja, mencakup $date. */
    private function makeApprovedCutiTahunan(User $employee, string $date, string $endDate, int $totalDays): AttendanceRequestLeaveDetails
    {
        $request = AttendanceRequests::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'type' => 'leave',
            'status' => 'approved',
            'approved_by' => $employee->id,
            'approved_at' => now(),
        ]);

        return AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Tahunan',
            'category' => 'tahunan',
            'start_date' => $date,
            'end_date' => $endDate,
            'total_days' => $totalDays,
        ]);
    }

    public function test_set_cell_off_refunds_matching_approved_cuti_tahunan(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $detail = $this->makeApprovedCutiTahunan($employee, $date, $date, 5);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, 'off');

        $this->assertSame(4, $detail->fresh()->total_days);
    }

    public function test_set_cell_off_does_not_refund_when_cell_was_already_off(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();
        $detail = $this->makeApprovedCutiTahunan($employee, $date, $date, 5);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, 'off')
            ->call('setCell', $employee->id, $date, 'off');

        // Panggilan kedua: before sudah 'off' -> tidak refund lagi (cuma sekali).
        $this->assertSame(4, $detail->fresh()->total_days);
    }

    public function test_set_cell_off_does_not_refund_calendar_day_category(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        $date = now()->startOfMonth()->addDays(4)->toDateString();

        $request = AttendanceRequests::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'type' => 'leave',
            'status' => 'approved',
            'approved_by' => $employee->id,
            'approved_at' => now(),
        ]);

        $detail = AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Melahirkan',
            'category' => 'melahirkan',
            'start_date' => $date,
            'end_date' => now()->startOfMonth()->addDays(93)->toDateString(),
            'total_days' => 90,
        ]);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('setCell', $employee->id, $date, 'off');

        $this->assertSame(90, $detail->fresh()->total_days);
    }

    public function test_save_roster_refunds_for_each_cell_turned_off_via_isi_cepat(): void
    {
        [$schedule, $shiftType, $employee] = $this->makeManualShiftSchedule();
        // Harus tanggal MASA DEPAN (bukan startOfMonth+N, bisa jatuh di masa lalu tergantung
        // tanggal jalan test) - saveRoster() (beda dari setCell()) mengunci tanggal lampau buat
        // yang cuma punya Update:ShiftScheduleAssignments (lihat lockBeforeDate()).
        $date1 = now()->addDays(4)->toDateString();
        $date2 = now()->addDays(5)->toDateString();
        $detail = $this->makeApprovedCutiTahunan($employee, $date1, $date2, 10);

        Livewire::actingAs($this->actingAsAbsensi())
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date1}", 'off')
            ->set("draft.{$employee->id}.{$date2}", 'off')
            ->call('saveRoster');

        $this->assertSame(8, $detail->fresh()->total_days);
    }
}
