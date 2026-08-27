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
 * readAndGroup() batch (dry-run command attendance:process-access-logs & tombol manual sync di
 * RekapPresensiPage) - pairScansForEmployee() harus misahin pasangan normal dari pasangan extra
 * shift overnight kalau semua 4 scan (masuk normal, pulang normal, masuk extra, pulang extra
 * besoknya) ada dalam 1 batch baca sekaligus, bukan digabung jadi 1 pasang ngaco
 * (check_in=masuk normal, check_out=scan masuk extra).
 */
class OvernightExtraShiftBatchSplitTest extends TestCase
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

    public function test_four_scans_in_one_batch_split_into_normal_and_extra_shift_pairs(): void
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP001', 'company_id' => $company->id]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        $malam = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'MLM', 'name' => 'Malam',
            'start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_overnight' => true, 'counted_work_days' => 1,
        ]);

        ScheduleShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id,
            'date' => '2026-08-10', 'off_day' => false,
        ]);
        ScheduleShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => null,
            'date' => '2026-08-11', 'off_day' => true,
        ]);

        $extraShift = ExtraShiftAssignment::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $malam->id,
            'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id,
        ]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 14:00:00');
        $this->insertScan($user, '2026-08-10 22:10:00');
        $this->insertScan($user, '2026-08-11 05:50:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-11');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertSame('07:00:00', $result['buckets']['shift'][0]['check_in']);
        $this->assertSame('14:00:00', $result['buckets']['shift'][0]['check_out']);

        $this->assertCount(1, $result['buckets']['extra_shift']);
        $this->assertSame('22:10:00', $result['buckets']['extra_shift'][0]['check_in']);
        $this->assertSame('05:50:00', $result['buckets']['extra_shift'][0]['check_out']);
        $this->assertSame($extraShift->id, $result['buckets']['extra_shift'][0]['extra_shift_assignment_id']);

        $this->assertCount(0, $result['buckets']['off']);
        $this->assertCount(0, $result['unresolved_schedule']);
    }
}
