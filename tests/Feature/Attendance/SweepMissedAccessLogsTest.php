<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi (production, 2026-09-23): baris yang masuk ke access_logs BELAKANGAN tapi jamnya lebih
 * tua dari watermark sync incremental tidak pernah terbaca lagi - presensinya hilang diam-diam.
 * Gejala di lapangan: sejak RS ikut, sebagian karyawan holding (yang data masternya tidak berubah
 * sama sekali) mendadak tidak masuk attendance_records. attendance:sweep-missed-scans menyapu
 * kasus itu tanpa menyentuh watermark.
 */
class SweepMissedAccessLogsTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
    }

    private function scan(User $u, string $dt): void
    {
        DB::table('access_logs')->insert([
            'employee_id' => $u->nip, 'access_datetime' => $dt, 'access_date' => substr($dt, 0, 10), 'access_time' => substr($dt, 11),
            'first_name' => $u->name, 'last_name' => '', 'person_name' => $u->name, 'person_group' => 'default',
        ]);
    }

    /** Fixed 08:00-16:00, hari kerja 2026-08-06 (Kamis). */
    private function fixedUser(string $nip): User
    {
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $this->company->id]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Kantor '.$nip, 'schedule_type' => 'fixed']);
        ScheduleFixedDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'start_time' => '08:00:00', 'break_minutes' => 0,
            'min_work_minutes' => 0, 'end_time' => '16:00:00', 'checkin_buffer_minutes' => 60, 'checkout_buffer_minutes' => 60,
            'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 0, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        return $user;
    }

    private function sweep(string $date = '2026-08-06'): void
    {
        $this->artisan('attendance:sweep-missed-scans', ['--date' => $date])->assertSuccessful();
    }

    /** Kasus utama: punya scan, tapi tidak pernah ada barisnya di attendance_records. */
    public function test_scans_with_no_attendance_record_at_all_are_recovered(): void
    {
        $user = $this->fixedUser('LEWAT');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 16:05:00');

        $this->assertDatabaseCount('attendance_records', 0);

        $this->sweep();

        $record = AttendanceRecords::where('nip', 'LEWAT')->where('date', '2026-08-06')->first();
        $this->assertNotNull($record, 'Scan yang terlewat watermark harus tertolong sapuan.');
        $this->assertSame('08:00:00', $record->check_in_time);
        $this->assertSame('16:05:00', $record->check_out_time);
    }

    /** Scan pulang yang masuk belakangan dengan jam lebih tua dari watermark: barisnya dilengkapi. */
    public function test_a_late_arriving_checkout_scan_completes_an_existing_record(): void
    {
        $user = $this->fixedUser('SETENGAH');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->sweep();

        $this->assertNull(AttendanceRecords::where('nip', 'SETENGAH')->first()->check_out_time);

        // Datang menyusul, jamnya jauh di belakang watermark sync incremental.
        $this->scan($user, '2026-08-06 16:10:00');
        $this->sweep();

        $this->assertSame('16:10:00', AttendanceRecords::where('nip', 'SETENGAH')->first()->check_out_time);
    }

    /** Sapuan berulang tidak boleh mengubah hasil - inilah yang bikin jalur batch aman diulang. */
    public function test_sweeping_repeatedly_keeps_the_same_result(): void
    {
        $user = $this->fixedUser('ULANG');
        $this->scan($user, '2026-08-06 07:55:00');
        $this->scan($user, '2026-08-06 16:30:00');

        $this->sweep();
        $first = AttendanceRecords::where('nip', 'ULANG')->first()->only(['check_in_time', 'check_out_time', 'late_minutes', 'overtime_minutes']);

        $this->sweep();
        $this->sweep();

        $this->assertSame($first, AttendanceRecords::where('nip', 'ULANG')->first()->only(['check_in_time', 'check_out_time', 'late_minutes', 'overtime_minutes']));
        $this->assertDatabaseCount('attendance_records', 1);
    }

    /** Koreksi manual HR tidak boleh ditimpa mesin tiap 10 menit. */
    public function test_manually_corrected_records_are_left_alone(): void
    {
        $user = $this->fixedUser('KOREKSI');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 16:00:00');

        AttendanceRecords::create([
            'nip' => 'KOREKSI', 'user_id' => $user->id, 'date' => '2026-08-06',
            'check_in_time' => '07:00:00', 'check_out_time' => '17:00:00',
            'status' => 'present', 'source' => 'manual', 'is_auto_calculated' => false,
        ]);

        $this->sweep();

        $record = AttendanceRecords::where('nip', 'KOREKSI')->first();
        $this->assertSame('07:00:00', $record->check_in_time, 'Koreksi manual tidak boleh ditimpa sapuan.');
        $this->assertSame('17:00:00', $record->check_out_time);
    }

    /** Orang yang sudah lengkap tidak dianggap janggal - sapuan harus berhenti di 2 query agregat. */
    public function test_a_complete_record_is_not_touched(): void
    {
        $user = $this->fixedUser('AMAN');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 16:00:00');
        $this->sweep();

        $before = AttendanceRecords::where('nip', 'AMAN')->first()->updated_at;

        $this->travel(2)->minutes();
        $this->sweep();

        $this->assertEquals($before, AttendanceRecords::where('nip', 'AMAN')->first()->updated_at, 'Baris yang sudah lengkap tidak boleh ditulis ulang.');
    }

    /** Sudah check-in dan belum scan lagi (mayoritas karyawan siang hari) bukan kasus janggal. */
    public function test_an_employee_still_at_work_is_not_rewritten(): void
    {
        $user = $this->fixedUser('MASIHKERJA');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->sweep();

        $before = AttendanceRecords::where('nip', 'MASIHKERJA')->first()->updated_at;

        $this->travel(2)->minutes();
        $this->sweep();

        $this->assertEquals($before, AttendanceRecords::where('nip', 'MASIHKERJA')->first()->updated_at);
    }
}
