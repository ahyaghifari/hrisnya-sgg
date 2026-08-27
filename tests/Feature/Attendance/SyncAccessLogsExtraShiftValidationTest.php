<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SyncAccessLogsAttendance::isGenuineExtraShiftScan() - bucket 'extra_shift' dari reader belum
 * tentu beneran waktu extra shift (reader cuma tau ADA ExtraShiftAssignment hari itu, bukan
 * apakah check-in yang kepasangkan itu beneran jam extra shift-nya). Kalau ternyata check-in-nya
 * masih jam shift NORMAL (gejala 4-scan yang kepangkas jadi 1 pasang oleh pairScansForEmployee),
 * command harus fallback proses pakai jadwal normal, bukan dipaksa jadi extra shift.
 */
class SyncAccessLogsExtraShiftValidationTest extends TestCase
{
    use RefreshDatabase;

    private function insertScan(User $user, string $datetime): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $user->nip,
            'access_datetime' => $datetime,
            'access_date' => substr($datetime, 0, 10),
            'access_time' => substr($datetime, 11),
            'first_name' => $user->name,
            'last_name' => '',
            'person_name' => $user->name,
            'person_group' => 'default',
        ]);
    }

    private function makeUserWithNormalAndExtraShift(string $nip): array
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $company->id]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '14:00:00',
            'late_tolerance_minutes' => 15,
            'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '14:00:00',
            'end_time' => '21:00:00',
            'late_tolerance_minutes' => 15,
            'counted_work_days' => 1,
        ]);

        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $pagi->id,
            'date' => '2026-08-10',
            'off_day' => false,
        ]);

        $extraShift = ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $siang->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $user->id,
        ]);

        return [$user, $extraShift];
    }

    public function test_checkin_still_within_normal_shift_falls_back_to_normal_attendance_record(): void
    {
        [$user] = $this->makeUserWithNormalAndExtraShift('EMP001');

        // 4 scan sungguhan, tapi pairScansForEmployee cuma nyisain scan pertama (07:00, masuk
        // normal) & terakhir (21:00, pulang extra) - check_in yang kebawa BUKAN jam extra shift.
        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 14:00:00');
        $this->insertScan($user, '2026-08-10 14:05:00');
        $this->insertScan($user, '2026-08-10 21:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $attendance = AttendanceRecords::where('nip', 'EMP001')->first();
        $this->assertNotNull($attendance, 'Harus fallback tersimpan ke attendance_records sebagai jadwal normal.');
        $this->assertSame('07:00:00', $attendance->check_in_time);

        $this->assertSame(0, ExtraShiftAttendanceRecord::count(), 'Tidak boleh ikut tersimpan sebagai extra shift, check-in-nya bukan jam extra shift.');
    }

    public function test_checkin_at_extra_shift_start_is_saved_as_extra_shift_attendance(): void
    {
        [$user, $extraShift] = $this->makeUserWithNormalAndExtraShift('EMP002');

        // Karyawan cuma scan buat extra shift-nya (tidak scan buat shift normal hari itu).
        $this->insertScan($user, '2026-08-10 14:00:00');
        $this->insertScan($user, '2026-08-10 21:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = ExtraShiftAttendanceRecord::where('extra_shift_assignment_id', $extraShift->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('14:00:00', $record->check_in_time);
        $this->assertSame('access_logs_shift', $record->source);

        $this->assertSame(0, AttendanceRecords::where('nip', 'EMP002')->count());
    }
}
