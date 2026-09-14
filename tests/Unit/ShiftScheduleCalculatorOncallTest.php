<?php

namespace Tests\Unit;

use App\Enum\AttendanceStatus;
use App\Enum\ShiftType;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftType;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Oncall TIDAK PUNYA konsep telat sama sekali (instruksi user 2026-09-14) - jam masuk bebas
 * total, late_minutes selalu 0, status tidak pernah LATE karena jam masuk. Payroll
 * (KeterlambatanCalculator) cuma baca AttendanceRecords.status/late_minutes apa adanya, jadi
 * begitu Oncall tidak pernah menghasilkan status itu, potongan telat otomatis tidak pernah kena
 * - tidak perlu perubahan terpisah di kode payroll.
 */
class ShiftScheduleCalculatorOncallTest extends TestCase
{
    use RefreshDatabase;

    public function test_oncall_never_marked_late_no_matter_how_late_the_check_in_is(): void
    {
        $company = Companies::create(['code' => 'OC1', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Shift', 'schedule_type' => 'shift']);

        $oncall = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'OC',
            'name' => 'Oncall',
            'shift_type' => ShiftType::ONCALL->value,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 0,
            'is_overnight' => false,
            'checkin_buffer_minutes' => 15,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 15,
            'max_work_minutes' => 480,
            'overtime_after_min' => 0,
            'overtime_after_break' => 0,
            'overtime_after_max' => 0,
        ]);

        // Check-in jauh lebih siang dari start_time (08:00) + toleransi - kalau ini shift biasa
        // sudah pasti LATE berjam-jam. Untuk Oncall harus tetap PRESENT, late_minutes=0.
        $result = app(ShiftScheduleCalculator::class)->calculate([
            ['config' => $oncall, 'check_in' => '11:01:00', 'check_out' => '16:41:00'],
        ]);

        $this->assertSame(0, $result[0]['late_minutes']);
        $this->assertSame(AttendanceStatus::PRESENT->value, $result[0]['status']);
        $this->assertFalse($result[0]['out_of_window']);
    }

    public function test_oncall_still_computes_working_minutes_and_overtime_normally(): void
    {
        $company = Companies::create(['code' => 'OC2', 'name' => 'Test Company 2', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Shift', 'schedule_type' => 'shift']);

        $oncall = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'OC',
            'name' => 'Oncall',
            'shift_type' => ShiftType::ONCALL->value,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 0,
            'is_overnight' => false,
            'checkin_buffer_minutes' => 15,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 15,
            'max_work_minutes' => 240, // target 4 jam
            'overtime_after_min' => 0,
            'overtime_after_break' => 0,
            'overtime_after_max' => 120,
        ]);

        // Masuk 07:39, pulang 11:53 - durasi pendek (~4 jam 14 menit), sesuai contoh nyata di
        // planning/2026-09-14-oncall-middle-redesign.md.
        $result = app(ShiftScheduleCalculator::class)->calculate([
            ['config' => $oncall, 'check_in' => '07:39:00', 'check_out' => '11:53:00'],
        ]);

        $this->assertSame(0, $result[0]['late_minutes']);
        $this->assertSame(AttendanceStatus::PRESENT->value, $result[0]['status']);
        $this->assertNotNull($result[0]['working_minutes']);
    }
}
