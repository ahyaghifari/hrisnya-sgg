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
 * Jalur PRODUKSI beneran (SyncAccessLogsAttendance, cron tiap menit -> readAndGroupIncremental)
 * - beda kode dari readAndGroup() batch punya pairScansForEmployee(). Sebelum fix ini, scan
 * check-in extra shift overnight yang datang belakangan (tick terpisah) NIMPA check_out shift
 * normal yang sudah benar tersimpan, dan scan checkout extra shift besoknya hilang total (lihat
 * resolveIncrementalSlot() & lookback extra shift di readAndGroupScansIncremental). Simulasikan
 * 4 tick cron terpisah (bukan 1 batch) - itu yang bikin bug beda dari kasus batch biasa.
 */
class OvernightExtraShiftIncrementalTest extends TestCase
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

    public function test_incremental_cron_ticks_keep_normal_and_extra_shift_overnight_pairing_separate(): void
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

        // Tick 1: masuk normal.
        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->artisan('attendance:sync-access-logs');
        $normal = AttendanceRecords::where('nip', 'EMP001')->where('date', '2026-08-10')->first();
        $this->assertSame('07:00:00', $normal->check_in_time);
        $this->assertNull($normal->check_out_time);

        // Tick 2: pulang normal.
        $this->insertScan($user, '2026-08-10 14:00:00');
        $this->artisan('attendance:sync-access-logs');
        $normal = $normal->fresh();
        $this->assertSame('14:00:00', $normal->check_out_time);

        // Tick 3: masuk extra shift (overnight) - TIDAK BOLEH menimpa check_out normal di atas.
        $this->insertScan($user, '2026-08-10 22:10:00');
        $this->artisan('attendance:sync-access-logs');
        $this->assertSame('14:00:00', $normal->fresh()->check_out_time, 'Check-out shift normal tidak boleh ketiban scan extra shift.');
        $extraRecord = ExtraShiftAttendanceRecord::where('extra_shift_assignment_id', $extraShift->id)->first();
        $this->assertNotNull($extraRecord);
        $this->assertSame('22:10:00', $extraRecord->check_in_time);
        $this->assertNull($extraRecord->check_out_time);

        // Tick 4: pulang extra shift, besoknya - TIDAK BOLEH hilang/nyasar ke bucket off.
        $this->insertScan($user, '2026-08-11 05:50:00');
        $this->artisan('attendance:sync-access-logs');
        $this->assertSame('05:50:00', $extraRecord->fresh()->check_out_time);
        $this->assertSame('14:00:00', $normal->fresh()->check_out_time);
        $this->assertSame(0, AttendanceRecords::where('nip', 'EMP001')->where('date', '2026-08-11')->count());
    }
}
