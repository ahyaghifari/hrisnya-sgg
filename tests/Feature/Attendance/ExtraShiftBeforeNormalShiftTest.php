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
 * Extra shift boleh jamnya LEBIH AWAL dari shift normal hari yang sama (mis. jadwal Siang,
 * extra shift Pagi) - sebelumnya (sebelum fix AccessLogScheduleReader::splitNormalAndExtraScans())
 * semua scan hari itu kegabung jadi 1 pasangan salah, shift normalnya hilang total. Lihat juga
 * OvernightExtraShiftBatchSplitTest untuk kasus extra overnight SESUDAH normal (arah sebaliknya).
 */
class ExtraShiftBeforeNormalShiftTest extends TestCase
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

    public function test_extra_shift_pagi_before_normal_siang_same_day(): void
    {
        $company = Companies::create(['code' => 'PB1', 'name' => 'Probe Co', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['nip' => 'PROBE001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'is_overnight' => false,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30,
            'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang',
            'start_time' => '14:00:00', 'end_time' => '21:00:00', 'is_overnight' => false,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30,
            'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);

        // Jadwal NORMAL hari itu = Siang.
        ScheduleShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'shift_type_id' => $siang->id, 'date' => '2026-08-10', 'off_day' => false,
        ]);

        // Extra shift = Pagi (LEBIH AWAL dari normal), approved.
        ExtraShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'shift_type_id' => $pagi->id, 'date' => '2026-08-10',
            'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id,
        ]);

        // 4 scan: masuk Pagi, pulang Pagi, masuk Siang, pulang Siang. Shift menyambung -> cuma scan
        // pertama & terakhir yang dipakai, batas antar shift sintetis (14:00), bukan 14:05.
        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 14:00:00');
        $this->insertScan($user, '2026-08-10 14:05:00');
        $this->insertScan($user, '2026-08-10 21:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'PROBE001')->where('date', '2026-08-10')->first();
        $extra = ExtraShiftAttendanceRecord::whereHas('extraShiftAssignment', fn ($q) => $q->where('user_id', $user->id))->first();

        // Shift normal (Siang) tetap tercatat, TIDAK hilang.
        $this->assertNotNull($normal);
        $this->assertSame('14:00:00', $normal->check_in_time);
        $this->assertSame('21:00:00', $normal->check_out_time);

        // Extra shift (Pagi) dihitung terpisah dengan pasangan scan-nya sendiri.
        $this->assertNotNull($extra);
        $this->assertSame('07:00:00', $extra->check_in_time);
        $this->assertSame('14:00:00', $extra->check_out_time);
        $this->assertSame(420, $extra->working_minutes);
    }
}
