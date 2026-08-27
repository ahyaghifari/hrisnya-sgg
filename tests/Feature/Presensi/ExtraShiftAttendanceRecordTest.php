<?php

namespace Tests\Feature\Presensi;

use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storage layer buat jam kerja aktual extra shift (planning/2026-07-31-fitur-extra-shift.md §5,
 * gap "integrasi ke absensi" - versi ini input manual, hitungannya reuse ShiftScheduleCalculator
 * yang sama dipakai pipeline access_logs biasa). Test lewat model+calculator langsung (bukan
 * Livewire::test) karena harness Livewire di environment ini lagi bermasalah untuk semua
 * komponen portal, termasuk yang sudah ada sebelum perubahan ini (lihat ExtraShiftPageTest).
 */
class ExtraShiftAttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_attendance_computes_late_and_working_minutes_via_shift_calculator(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '14:00:00',
            'end_time' => '21:00:00',
        ]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $extraShift = ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_type_id' => $siang->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $employee->id,
        ]);

        $calculated = (new ShiftScheduleCalculator)->calculate([[
            'config' => $siang,
            'check_in' => '14:20:00',
            'check_out' => '21:00:00',
        ]])[0];

        $record = ExtraShiftAttendanceRecord::create([
            'extra_shift_assignment_id' => $extraShift->id,
            'check_in_time' => $calculated['check_in'],
            'check_out_time' => $calculated['check_out'],
            'working_minutes' => $calculated['working_minutes'],
            'late_minutes' => $calculated['late_minutes'],
            'status' => $calculated['status'],
        ]);

        $this->assertSame(20, $record->late_minutes);
        $this->assertSame('late', $record->status);
        $this->assertEquals(400, $record->working_minutes);
        $this->assertTrue($extraShift->fresh()->attendanceRecord->is($record));
    }
}
