<?php

namespace Tests\Feature\Shift;

use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftRosterLog;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Oncall (beda dari extra shift jenis lain) nimpa schedule_shift_assignments hari itu begitu
 * approved - lihat ExtraShiftAssignment::syncOncallRoster()/revertOncallRoster(). Extra shift
 * biasa (pagi->siang dst) TETAP additive, tidak boleh ikut kesentuh - dicek di test terakhir.
 */
class ExtraShiftOncallOverwritesRosterTest extends TestCase
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

    private function makeSchedule(Companies $company): Schedule
    {
        return Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);
    }

    public function test_assigning_oncall_overwrites_off_day_into_real_roster_shift(): void
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP001', 'company_id' => $company->id]);
        $schedule = $this->makeSchedule($company);
        $oncall = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'oncall',
            'code' => 'OC',
            'name' => 'Oncall',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'counted_work_days' => 1,
        ]);

        // Hari itu eksplisit off_day=true (Libur) di roster.
        ScheduleShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => null,
            'date' => '2026-08-10',
            'off_day' => true,
        ]);

        $extraShift = ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $oncall->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $user->id,
        ]);

        $roster = ScheduleShiftAssignment::where('user_id', $user->id)->whereDate('date', '2026-08-10')->first();
        $this->assertNotNull($roster);
        $this->assertFalse($roster->off_day);
        $this->assertSame($oncall->id, $roster->shift_type_id);

        $this->assertDatabaseHas('schedule_shift_roster_logs', [
            'user_id' => $user->id,
            'source' => 'extra_shift_oncall',
            'new_value' => $oncall->id,
        ]);

        // Scan hari itu sekarang lewat bucket 'shift' NORMAL, bukan 'extra_shift' - tidak perlu
        // validasi genuine-scan/isGenuineExtraShiftScan lagi.
        $this->insertScan($user, '2026-08-10 08:00:00');
        $this->insertScan($user, '2026-08-10 16:00:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-10');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertCount(0, $result['buckets']['extra_shift']);
        $this->assertTrue($result['buckets']['shift'][0]['config']->is($oncall));

        // Batalkan Oncall -> roster balik jadi tanpa baris (= Libur lagi buat resolveNormalShift()).
        $extraShift->delete();

        $this->assertNull(ScheduleShiftAssignment::where('user_id', $user->id)->whereDate('date', '2026-08-10')->first());
        $this->assertDatabaseHas('schedule_shift_roster_logs', [
            'user_id' => $user->id,
            'source' => 'extra_shift_oncall_cancel',
            'old_value' => $oncall->id,
        ]);
    }

    public function test_normal_extra_shift_stays_additive_and_does_not_touch_roster(): void
    {
        $company = Companies::create(['code' => 'C2', 'name' => 'Company 2', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'EMP002', 'company_id' => $company->id]);
        $schedule = $this->makeSchedule($company);
        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'pagi',
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '14:00:00',
            'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'shift_type' => 'siang',
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

        ExtraShiftAssignment::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'shift_type_id' => $siang->id,
            'date' => '2026-08-10',
            'status' => 'approved',
            'source' => 'assigned',
            'created_by' => $user->id,
        ]);

        // Roster tidak berubah - masih Pagi, bukan ketiban Siang.
        $roster = ScheduleShiftAssignment::where('user_id', $user->id)->whereDate('date', '2026-08-10')->first();
        $this->assertSame($pagi->id, $roster->shift_type_id);
        $this->assertDatabaseMissing('schedule_shift_roster_logs', ['user_id' => $user->id]);
    }
}
