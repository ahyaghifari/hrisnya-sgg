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
 * Pola realistis "cuma 2 scan" (masuk awal, pulang akhir - TANPA scan di titik transisi antar
 * shift) - normal+extra hari sama dipecah pakai jam KONFIGURASI sebagai batas sintetis, bukan
 * jam scan asli (tidak ada bukti scan pas transisi). Lihat
 * ShiftAttendanceReader::splitTwoScans()/splitIncrementalTwoScans() (instruksi user 2026-09-13:
 * "kayaknya cukup 2 scan aja"). Cover jalur batch DAN incremental, dua arah urutan shift.
 */
class TwoScanExtraShiftSplitTest extends TestCase
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

    private function makeUser(string $nip): array
    {
        $company = Companies::create(['code' => 'TS' . substr($nip, -3), 'name' => 'Test Co', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '07:00:00', 'end_time' => '15:00:00', 'is_overnight' => false,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang',
            'start_time' => '15:00:00', 'end_time' => '23:00:00', 'is_overnight' => false,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);

        return [$user, $company, $pagi, $siang];
    }

    public function test_batch_two_scans_normal_earlier_than_extra(): void
    {
        [$user, $company, $pagi, $siang] = $this->makeUser('TWO001');

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 23:00:00');

        $result = app(\App\Services\Attendance\AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-10');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertCount(1, $result['buckets']['extra_shift']);

        $normalEntry = $result['buckets']['shift'][0];
        $this->assertSame('07:00:00', $normalEntry['check_in']);
        $this->assertSame('15:00:00', $normalEntry['check_out']);

        $extraEntry = $result['buckets']['extra_shift'][0];
        $this->assertSame('15:00:00', $extraEntry['check_in']);
        $this->assertSame('23:00:00', $extraEntry['check_out']);
    }

    public function test_batch_two_scans_extra_earlier_than_normal(): void
    {
        [$user, $company, $pagi, $siang] = $this->makeUser('TWO002');

        // Extra (Pagi) LEBIH AWAL dari normal (Siang) hari itu.
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-10', 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 23:00:00');

        $result = app(\App\Services\Attendance\AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-10');

        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertCount(1, $result['buckets']['extra_shift']);

        $normalEntry = $result['buckets']['shift'][0];
        $this->assertSame('15:00:00', $normalEntry['check_in']);
        $this->assertSame('23:00:00', $normalEntry['check_out']);

        $extraEntry = $result['buckets']['extra_shift'][0];
        $this->assertSame('07:00:00', $extraEntry['check_in']);
        $this->assertSame('15:00:00', $extraEntry['check_out']);
    }

    public function test_incremental_two_scans_arriving_in_one_tick(): void
    {
        [$user, $company, $pagi, $siang] = $this->makeUser('TWO003');

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-10 23:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'TWO003')->where('date', '2026-08-10')->first();
        $extra = ExtraShiftAttendanceRecord::whereHas('extraShiftAssignment', fn ($q) => $q->where('user_id', $user->id))->first();

        $this->assertNotNull($normal);
        $this->assertSame('07:00:00', $normal->check_in_time);
        $this->assertSame('15:00:00', $normal->check_out_time);

        $this->assertNotNull($extra);
        $this->assertSame('15:00:00', $extra->check_in_time);
        $this->assertSame('23:00:00', $extra->check_out_time);
    }

    /** Pola nyata: cron jalan PER TICK - scan pertama (masuk) di-sync duluan sendirian, baru
     * scan kedua (pulang) di-sync di tick berikutnya (bukan 2 scan sekaligus 1 tick). */
    public function test_incremental_two_scans_arriving_in_separate_ticks(): void
    {
        [$user, $company, $pagi, $siang] = $this->makeUser('TWO004');

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $siang->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $afterFirstTick = AttendanceRecords::where('nip', 'TWO004')->where('date', '2026-08-10')->first();
        $this->assertNotNull($afterFirstTick);
        $this->assertSame('07:00:00', $afterFirstTick->check_in_time);
        $this->assertNull($afterFirstTick->check_out_time);

        $this->insertScan($user, '2026-08-10 23:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'TWO004')->where('date', '2026-08-10')->first();
        $extra = ExtraShiftAttendanceRecord::whereHas('extraShiftAssignment', fn ($q) => $q->where('user_id', $user->id))->first();

        $this->assertNotNull($normal);
        $this->assertSame('07:00:00', $normal->check_in_time);
        $this->assertSame('15:00:00', $normal->check_out_time, 'Checkout normal harus jam SELESAI config (sintetis), bukan jam scan asli (23:00).');

        $this->assertNotNull($extra, 'Extra shift harus tercatat walau tidak ada scan pas transisi.');
        $this->assertSame('15:00:00', $extra->check_in_time);
        $this->assertSame('23:00:00', $extra->check_out_time);
    }
}
