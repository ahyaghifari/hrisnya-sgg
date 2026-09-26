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
use App\Services\Attendance\AccessLogScheduleReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Beda dari "shift nempel" (ShiftAttendanceReader::isAdjacent()==true, cukup 2 scan, lihat
 * TwoScanExtraShiftSplitTest) - kalau normal & extra shift hari yang sama TAPI ADA JEDA (skip 1
 * shift di antaranya, mis. normal Pagi lalu extra langsung Malam tanpa pernah masuk Siang),
 * sistem TIDAK BOLEH menebak batas transisi - wajib scan lengkap 4x (masuk/pulang normal,
 * masuk/pulang extra). Kalau cuma 2 scan, hasilnya harus tetap KOSONG/INCOMPLETE (bukan
 * ditebak salah) supaya kelihatan ada yang kurang, bukan diam-diam salah hitung (instruksi user
 * 2026-09-13: "kalo yang skip mungkin harusnya 4 kali absen").
 */
class SkipShiftRequiresFourScansTest extends TestCase
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

    private function makeUser(): array
    {
        $company = Companies::create(['code' => 'SKP', 'name' => 'Skip Co', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['nip' => 'SKIP001', 'company_id' => $company->id]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'is_overnight' => false,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);
        $malam = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'MALAM', 'name' => 'Malam', 'shift_type' => 'malam',
            'start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_overnight' => true,
            'checkin_buffer_minutes' => 30, 'checkout_buffer_minutes' => 30, 'late_tolerance_minutes' => 10, 'counted_work_days' => 1,
        ]);

        return [$user, $company, $pagi, $malam];
    }

    public function test_batch_two_scans_with_skipped_shift_does_not_get_split(): void
    {
        [$user, $company, $pagi, $malam] = $this->makeUser();

        // Normal = Pagi (07-14). Extra = Malam (22-06), LOMPAT Siang - ADA jeda 14:00-22:00.
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'off_day' => false]);
        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => null, 'date' => '2026-08-11', 'off_day' => true]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $malam->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        // Cuma 2 scan: masuk Pagi, pulang Malam besok - TIDAK cukup buat pola skip.
        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-11 06:00:00');

        $result = app(AccessLogScheduleReader::class)->readAndGroup('2026-08-10', '2026-08-11');

        // TIDAK dipecah jadi normal+extra - scan 07:00 jelas milik Pagi (belum masuk jendela check-in
        // Malam), jadi jadi pasangan NORMAL, check_out tetap kosong (tidak ditebak dari scan
        // besok). Extra Malam tidak tercatat sama sekali. Scan tanggal 11 jadi entri 'off' sendiri
        // (Libur beneran), tidak ke-borrow sama sekali oleh baris tanggal 10.
        $this->assertCount(0, $result['buckets']['extra_shift']);
        $this->assertCount(1, $result['buckets']['shift']);
        $this->assertSame('07:00:00', $result['buckets']['shift'][0]['check_in']);
        $this->assertNull($result['buckets']['shift'][0]['check_out']);

        $this->assertCount(1, $result['buckets']['off']);
        $this->assertSame('2026-08-11', $result['buckets']['off'][0]['date']);
    }

    public function test_incremental_two_ticks_with_skipped_shift_leaves_normal_open_and_extra_untouched(): void
    {
        [$user, $company, $pagi, $malam] = $this->makeUser();

        ScheduleShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $pagi->id, 'date' => '2026-08-10', 'off_day' => false]);
        ExtraShiftAssignment::create(['company_id' => $company->id, 'user_id' => $user->id, 'shift_type_id' => $malam->id, 'date' => '2026-08-10', 'status' => 'approved', 'source' => 'assigned', 'created_by' => $user->id]);

        $this->insertScan($user, '2026-08-10 07:00:00');
        $this->insertScan($user, '2026-08-11 06:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $normal = AttendanceRecords::where('nip', 'SKIP001')->where('date', '2026-08-10')->first();
        $extra = ExtraShiftAttendanceRecord::whereHas('extraShiftAssignment', fn ($q) => $q->where('user_id', $user->id))->first();

        $this->assertNotNull($normal);
        $this->assertSame('07:00:00', $normal->check_in_time);
        $this->assertNull($normal->check_out_time, 'Tidak boleh ditebak/ke-borrow dari scan besok - butuh scan pulang Pagi yang beneran.');

        $this->assertNull($extra, 'Extra shift Malam tidak boleh tercatat dari 1 scan yang salah atribusi.');
    }
}
