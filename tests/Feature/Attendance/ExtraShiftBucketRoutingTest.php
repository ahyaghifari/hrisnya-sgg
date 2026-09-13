<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reader nentuin ADA/TIDAKNYA extra shift approved di (user,date), TAPI begitu normal shift-nya
 * JUGA ada hari itu, 2 scan (masuk shift pertama, pulang shift kedua - pola "cuma 2 scan") tetap
 * dipecah jadi 2 entri (normal + extra_shift) pakai batas jam konfigurasi sebagai sintetis,
 * BUKAN digabung mentah ke 1 bucket (instruksi user 2026-09-13, ganti behavior lama yang cuma
 * lempar semuanya ke extra_shift apa adanya). Lihat ShiftAttendanceReader::splitTwoScans().
 * Validasi "beneran waktu extra shift atau bukan" (kasus scan CUMA buat extra, normal tidak
 * pernah kescan sama sekali) ada di sisi konsumen (SyncAccessLogsAttendance::isGenuineExtraShiftScan)
 * - lihat SyncAccessLogsExtraShiftValidationTest.
 */
class ExtraShiftBucketRoutingTest extends TestCase
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

    public function test_two_scans_spanning_normal_and_extra_shift_are_split_into_both_buckets(): void
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP001', 'company_id' => $company->id]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '14:00:00',
            'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '14:00:00',
            'end_time' => '21:00:00',
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

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 21:00:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-10');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertCount(1, $result['buckets']['extra_shift']);

        $normalEntry = $result['buckets']['shift'][0];
        $this->assertSame('07:00:00', $normalEntry['check_in']);
        $this->assertSame('14:00:00', $normalEntry['check_out']);

        $entry = $result['buckets']['extra_shift'][0];
        $this->assertSame($extraShift->id, $entry['extra_shift_assignment_id']);
        $this->assertTrue($entry['config']->is($siang));
        $this->assertSame('14:00:00', $entry['check_in']);
        $this->assertSame('21:00:00', $entry['check_out']);
    }

    public function test_day_without_extra_shift_still_goes_to_normal_shift_bucket(): void
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP002', 'company_id' => $company->id]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '14:00:00',
            'counted_work_days' => 1,
        ]);

        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $pagi->id,
            'date' => '2026-08-10',
            'off_day' => false,
        ]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 14:00:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-10');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertCount(0, $result['buckets']['extra_shift']);
    }
}
