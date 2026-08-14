<?php

namespace Tests\Feature\Attendance;

use App\Enum\AttendanceStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Simulasi ujung-ke-ujung untuk jadwal tipe Shift: 1 schedule baru dengan 4 tipe shift
 * (Pagi/Siang/Malam overnight/Pagi Ketat), beberapa karyawan diroster ke shift berbeda lewat
 * ScheduleShiftAssignment, scan mentah ditulis ke access_logs, lalu `attendance:sync-access-logs`
 * dijalankan (pipeline asli: AccessLogScheduleReader -> ShiftScheduleCalculator ->
 * AttendanceRecordRepository, sama seperti production). Tiap test mengisolasi satu aspek
 * perhitungan; test "full_day_simulation" menjalankan semuanya sekaligus dalam beberapa sync run
 * untuk simulasi "hari kerja penuh" lintas shift & karyawan.
 *
 * Cakupan kolom `schedule_shift_types` (kasus yang realistis sering terjadi di lapangan):
 * - Pagi/Siang: telat, pulang cepat, lembur-sesudah dasar, batas toleransi persis (boundary).
 * - Malam (is_overnight): pairing check-in/check-out lintas tanggal, lembur-sebelum, 2 batch
 *   sync (simulasi 2 tick cron - overnight tidak selesai dalam 1 batch, lihat catatan `ponytail:`
 *   di AccessLogScheduleReader).
 * - Pagi Ketat: buffer checkin/checkout sempit -> out_of_window, overtime_after_break (potongan
 *   lembur, beda dari overtime_after_min), max_work_minutes (cap jam kerja), half_day_threshold_
 *   minutes & absent_threshold_minutes (bobot hari kerja 0.5/0), lupa scan pulang (1 scan saja
 *   sepanjang hari - working_minutes & work_day_weight tetap null, bukan 0).
 * - Roster off_day (Libur eksplisit): scan yang tetap masuk pada hari libur tidak menghasilkan
 *   attendance_records sama sekali (bucket 'off' tidak diproses SyncAccessLogsAttendance).
 */
class ShiftScheduleSimulationTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;
    private ScheduleShiftType $malam;
    private ScheduleShiftType $pagiKetat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create([
            'code' => 'SIM',
            'name' => 'PT Simulasi Shift',
            'location' => 'Banjarbaru',
            'is_active' => true,
        ]);

        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Jadwal Shift Produksi',
            'schedule_type' => 'shift',
        ]);

        // Pagi: dipakai buat kasus telat & lembur-sesudah (overtime_after aktif, before dimatikan).
        $this->pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_overnight' => false,
            'break_minutes' => 60,
            'checkin_buffer_minutes' => 120,
            'checkout_buffer_minutes' => 120,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 0,
            'overtime_before_min' => 0,
            'overtime_before_break' => 0,
            'overtime_before_max' => 0,
            'overtime_after_min' => 15,
            'overtime_after_break' => 0,
            'overtime_after_max' => 180,
            'counted_work_days' => 1,
        ]);

        // Siang: dipakai buat kasus pulang cepat (early_leave_tolerance_minutes=30, overtime dimatikan).
        $this->siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
            'is_overnight' => false,
            'break_minutes' => 60,
            'checkin_buffer_minutes' => 120,
            'checkout_buffer_minutes' => 120,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 30,
            'overtime_before_min' => 0,
            'overtime_before_break' => 0,
            'overtime_before_max' => 0,
            'overtime_after_min' => 0,
            'overtime_after_break' => 0,
            'overtime_after_max' => 0,
            'counted_work_days' => 1,
        ]);

        // Malam: overnight (23:00-07:00), dipakai buat kasus lembur-sebelum + pairing check-in/
        // check-out lintas tanggal (overtime_after dimatikan supaya fokus 1 aspek per test).
        $this->malam = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'MALAM',
            'name' => 'Malam',
            'start_time' => '23:00:00',
            'end_time' => '07:00:00',
            'is_overnight' => true,
            'break_minutes' => 60,
            'checkin_buffer_minutes' => 120,
            'checkout_buffer_minutes' => 120,
            'late_tolerance_minutes' => 15,
            'early_leave_tolerance_minutes' => 0,
            'overtime_before_min' => 15,
            'overtime_before_break' => 0,
            'overtime_before_max' => 90,
            'overtime_after_min' => 0,
            'overtime_after_break' => 0,
            'overtime_after_max' => 0,
            'counted_work_days' => 1,
        ]);

        // Pagi Ketat: buffer checkin/checkout sempit (30 menit, gampang kena out_of_window),
        // overtime_after pakai break-deduction (30 menit), max_work_minutes (cap 600 - 10 jam),
        // half_day/absent threshold aktif - kolom-kolom yang sengaja dimatikan (0/null) di
        // Pagi/Siang/Malam supaya test lain fokus 1 aspek.
        $this->pagiKetat = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGIKETAT',
            'name' => 'Pagi Ketat',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_overnight' => false,
            'break_minutes' => 60,
            'checkin_buffer_minutes' => 30,
            'checkout_buffer_minutes' => 30,
            'late_tolerance_minutes' => 10,
            'early_leave_tolerance_minutes' => 15,
            'overtime_before_min' => 0,
            'overtime_before_break' => 0,
            'overtime_before_max' => 0,
            'overtime_after_min' => 0,
            'overtime_after_break' => 30,
            'overtime_after_max' => 240,
            'max_work_minutes' => 600,
            'half_day_threshold_minutes' => 240,
            'absent_threshold_minutes' => 60,
            'counted_work_days' => 1,
        ]);
    }

    private function makeEmployee(string $nip, string $name): User
    {
        return User::factory()->create([
            'nip' => $nip,
            'name' => $name,
            'company_id' => $this->company->id,
        ]);
    }

    private function assignShift(User $user, ScheduleShiftType $shiftType, string $date): void
    {
        ScheduleShiftAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_type_id' => $shiftType->id,
            'date' => $date,
            'off_day' => false,
        ]);
    }

    private function assignOffDay(User $user, string $date): void
    {
        ScheduleShiftAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_type_id' => null,
            'date' => $date,
            'off_day' => true,
        ]);
    }

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

    public function test_pagi_shift_on_time_has_no_late_or_overtime(): void
    {
        $andi = $this->makeEmployee('PAGI001', 'Andi');
        $this->assignShift($andi, $this->pagi, '2026-08-10');
        $this->insertScan($andi, '2026-08-10 07:05:00');
        $this->insertScan($andi, '2026-08-10 15:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'PAGI001')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame('07:05:00', $record->check_in_time);
        $this->assertSame('15:00:00', $record->check_out_time);
        $this->assertSame(0.0, $record->late_minutes);
        $this->assertSame(0.0, $record->early_leave_minutes);
        $this->assertSame(415, $record->working_minutes);
        $this->assertNull($record->overtime_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
        $this->assertFalse((bool) $record->out_of_window);
    }

    public function test_pagi_shift_late_checkin_is_flagged_late_with_correct_minutes(): void
    {
        $budi = $this->makeEmployee('PAGI002', 'Budi');
        $this->assignShift($budi, $this->pagi, '2026-08-10');
        // Toleransi telat 15 menit (07:00-07:15) - checkin 07:25 -> telat 10 menit dari batas toleransi.
        $this->insertScan($budi, '2026-08-10 07:25:00');
        $this->insertScan($budi, '2026-08-10 15:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'PAGI002')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(10.0, $record->late_minutes);
        $this->assertSame(395, $record->working_minutes);
        $this->assertSame(AttendanceStatus::LATE->value, $record->status);
    }

    public function test_pagi_shift_checkout_after_tolerance_window_counts_as_overtime(): void
    {
        $citra = $this->makeEmployee('PAGI003', 'Citra');
        $this->assignShift($citra, $this->pagi, '2026-08-10');
        // Pulang 16:00, 1 jam lewat jam pulang (15:00). Jendela lembur mulai 15 menit setelah
        // jam pulang (15:15) - lembur riil = 16:00 - 15:15 = 45 menit.
        $this->insertScan($citra, '2026-08-10 07:00:00');
        $this->insertScan($citra, '2026-08-10 16:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'PAGI003')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(0.0, $record->late_minutes);
        $this->assertSame(480, $record->working_minutes);
        $this->assertSame(45.0, $record->overtime_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
    }

    public function test_siang_shift_checkout_before_tolerance_counts_as_early_leave(): void
    {
        $dedi = $this->makeEmployee('SIANG001', 'Dedi');
        $this->assignShift($dedi, $this->siang, '2026-08-10');
        // Toleransi pulang cepat 30 menit (batas 22:30) - pulang 22:00 -> cepat 30 menit dari batas.
        $this->insertScan($dedi, '2026-08-10 15:00:00');
        $this->insertScan($dedi, '2026-08-10 22:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'SIANG001')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(0.0, $record->late_minutes);
        $this->assertSame(30.0, $record->early_leave_minutes);
        $this->assertSame(360, $record->working_minutes);
        $this->assertNull($record->overtime_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
    }

    /**
     * Overnight butuh 2x sync ("2 tick cron") - check-in & check-out yang SAMA-SAMA baru dalam 1
     * batch (belum ada attendance_records buat hari check-in-nya sama sekali) tidak otomatis
     * kepasangkan dalam pipeline incremental (lihat catatan `ponytail:` di
     * AccessLogScheduleReader::readAndGroupScansIncremental() - kasus "cron mati pas ada shift
     * overnight"). Simulasi realistis: sync setelah check-in dulu (record kebentuk cuma
     * check_in), baru sync lagi setelah check-out (lookback ke record kemarin melengkapinya).
     */
    public function test_malam_overnight_shift_pairs_next_day_checkout_and_counts_overtime_before(): void
    {
        $eka = $this->makeEmployee('MALAM001', 'Eka');
        $this->assignShift($eka, $this->malam, '2026-08-10');

        // Checkin 22:30 (30 menit sebelum shift mulai 23:00), masuk jendela lembur-sebelum
        // (15-90 menit sebelum mulai) -> lembur = 22:45 - 22:30 = 15 menit.
        $this->insertScan($eka, '2026-08-10 22:30:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $afterCheckIn = AttendanceRecords::where('nip', 'MALAM001')->where('date', '2026-08-10')->firstOrFail();
        $this->assertSame('22:30:00', $afterCheckIn->check_in_time);
        $this->assertNull($afterCheckIn->check_out_time);

        // Checkout 07:00 hari berikutnya (persis jam pulang, tidak ada lembur-sesudah karena
        // dimatikan di config) - batch sync kedua.
        $this->insertScan($eka, '2026-08-11 07:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        // Record tetap di tanggal MULAI shift (bukan tanggal checkout).
        $record = AttendanceRecords::where('nip', 'MALAM001')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame('22:30:00', $record->check_in_time);
        $this->assertSame('07:00:00', $record->check_out_time);
        $this->assertSame(0.0, $record->late_minutes);
        $this->assertSame(0.0, $record->early_leave_minutes);
        $this->assertSame(450, $record->working_minutes);
        $this->assertSame(15.0, $record->overtime_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
    }

    /**
     * Simulasi "hari kerja penuh": 5 karyawan, 3 shift berbeda (Pagi/Siang/Malam), diroster ke 1
     * schedule yang sama, discan & disync bareng - membuktikan roster shift majemuk beserta
     * orang-orangnya diproses benar sekaligus, bukan cuma waktu diisolasi satu-satu per test.
     * Eka (Malam/overnight) sengaja 2 batch sync (checkin dulu, checkout menyusul) - itu pola
     * cron nyata buat shift yang melewati tengah malam (lihat catatan di
     * test_malam_overnight_shift_pairs_next_day_checkout_and_counts_overtime_before di atas),
     * sedangkan yang lain (shift 1-hari) tuntas dalam 1 batch sync yang sama.
     */
    public function test_full_day_simulation_across_all_shifts_and_employees(): void
    {
        $andi  = $this->makeEmployee('PAGI001', 'Andi');   // Pagi, tepat waktu
        $budi  = $this->makeEmployee('PAGI002', 'Budi');   // Pagi, telat
        $citra = $this->makeEmployee('PAGI003', 'Citra');  // Pagi, lembur
        $dedi  = $this->makeEmployee('SIANG001', 'Dedi');  // Siang, pulang cepat
        $eka   = $this->makeEmployee('MALAM001', 'Eka');   // Malam, overnight + lembur-sebelum

        $this->assignShift($andi, $this->pagi, '2026-08-10');
        $this->assignShift($budi, $this->pagi, '2026-08-10');
        $this->assignShift($citra, $this->pagi, '2026-08-10');
        $this->assignShift($dedi, $this->siang, '2026-08-10');
        $this->assignShift($eka, $this->malam, '2026-08-10');

        // Batch sync 1: seluruh scan hari ini, termasuk checkin Eka (checkout-nya baru besok).
        $this->insertScan($andi, '2026-08-10 07:05:00');
        $this->insertScan($andi, '2026-08-10 15:00:00');

        $this->insertScan($budi, '2026-08-10 07:25:00');
        $this->insertScan($budi, '2026-08-10 15:00:00');

        $this->insertScan($citra, '2026-08-10 07:00:00');
        $this->insertScan($citra, '2026-08-10 16:00:00');

        $this->insertScan($dedi, '2026-08-10 15:00:00');
        $this->insertScan($dedi, '2026-08-10 22:00:00');

        $this->insertScan($eka, '2026-08-10 22:30:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        // Batch sync 2: checkout Eka baru masuk besok paginya (cron tick berikutnya).
        $this->insertScan($eka, '2026-08-11 07:00:00');
        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $records = AttendanceRecords::where('date', '2026-08-10')
            ->whereIn('nip', ['PAGI001', 'PAGI002', 'PAGI003', 'SIANG001', 'MALAM001'])
            ->get()
            ->keyBy('nip');

        $this->assertCount(5, $records, 'Kelima karyawan lintas 3 shift harus tersinkron.');

        $this->assertSame(0.0, $records['PAGI001']->late_minutes);
        $this->assertNull($records['PAGI001']->overtime_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $records['PAGI001']->status);

        $this->assertSame(10.0, $records['PAGI002']->late_minutes);
        $this->assertSame(AttendanceStatus::LATE->value, $records['PAGI002']->status);

        $this->assertSame(45.0, $records['PAGI003']->overtime_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $records['PAGI003']->status);

        $this->assertSame(30.0, $records['SIANG001']->early_leave_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $records['SIANG001']->status);

        $this->assertSame('07:00:00', $records['MALAM001']->check_out_time);
        $this->assertSame(15.0, $records['MALAM001']->overtime_minutes);
        $this->assertSame(450, $records['MALAM001']->working_minutes);

        // Semua work_day_weight = 1.0 (counted_work_days=1, half_day/absent threshold dimatikan).
        foreach ($records as $record) {
            $this->assertSame('1.0', $record->work_day_weight);
        }
    }

    public function test_pagi_shift_checkin_exactly_at_tolerance_boundary_is_not_late(): void
    {
        // late_tolerance_minutes=15 -> batas telat 07:15. Checkin PERSIS di batas (bukan
        // lewat) - kondisi kalkulator pakai `gt` (strict), jadi 07:15 belum dianggap telat.
        $fajar = $this->makeEmployee('PAGI004', 'Fajar');
        $this->assignShift($fajar, $this->pagi, '2026-08-10');
        $this->insertScan($fajar, '2026-08-10 07:15:00');
        $this->insertScan($fajar, '2026-08-10 15:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'PAGI004')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(0.0, $record->late_minutes);
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
    }

    /**
     * Kasus umum di lapangan: karyawan lupa/lewat scan pulang. Cuma 1 scan (check-in) sepanjang
     * hari itu - tidak ada checkout sama sekali, bukan cuma "checkout belum lengkap".
     */
    public function test_pagi_ketat_forgotten_checkout_leaves_working_minutes_and_weight_null(): void
    {
        $gilang = $this->makeEmployee('KETAT001', 'Gilang');
        $this->assignShift($gilang, $this->pagiKetat, '2026-08-10');
        $this->insertScan($gilang, '2026-08-10 08:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'KETAT001')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame('08:00:00', $record->check_in_time);
        $this->assertNull($record->check_out_time);
        $this->assertNull($record->working_minutes);
        $this->assertNull($record->overtime_minutes);
        $this->assertSame(0.0, $record->early_leave_minutes);
        $this->assertSame(0.0, $record->late_minutes);
        $this->assertNull($record->work_day_weight, 'workDayWeight tidak bisa dihitung tanpa working_minutes.');
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
    }

    /**
     * Checkin jauh sebelum buffer masuk (checkin_buffer_minutes=30, earliest=07:30) - ditandai
     * out_of_window, TAPI tetap dihitung penuh sebagai jam kerja mentah (out_of_window cuma
     * penanda, bukan penolakan) - dipotong oleh max_work_minutes (600) kalau totalnya kelewatan.
     */
    public function test_pagi_ketat_checkin_far_before_buffer_flags_out_of_window(): void
    {
        $hana = $this->makeEmployee('KETAT002', 'Hana');
        $this->assignShift($hana, $this->pagiKetat, '2026-08-10');
        // Checkin 2 jam sebelum shift mulai (06:00), jauh di luar buffer 30 menit.
        $this->insertScan($hana, '2026-08-10 06:00:00');
        $this->insertScan($hana, '2026-08-10 16:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'KETAT002')->where('date', '2026-08-10')->firstOrFail();

        $this->assertTrue((bool) $record->out_of_window);
        $this->assertSame(0.0, $record->late_minutes);
        // Mentah: (16:00-06:00) - 60 istirahat = 540 menit, di bawah cap 600 - tidak terpotong.
        $this->assertSame(540, $record->working_minutes);
    }

    /**
     * overtime_after_break (30 menit) dipotong dari durasi lembur mentah - beda dari
     * overtime_after_min yang menggeser AWAL jendela hitung, break murni pengurang hasil akhir.
     */
    public function test_pagi_ketat_overtime_after_deducts_configured_break_minutes(): void
    {
        $indra = $this->makeEmployee('KETAT003', 'Indra');
        $this->assignShift($indra, $this->pagiKetat, '2026-08-10');
        // Pulang 17:00, 1 jam lewat jam pulang (16:00) - lembur mentah 60 menit, dipotong break
        // 30 menit -> lembur riil 30 menit.
        $this->insertScan($indra, '2026-08-10 08:00:00');
        $this->insertScan($indra, '2026-08-10 17:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'KETAT003')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(30.0, $record->overtime_minutes);
        // Mentah (17:00-08:00)-60=480, di bawah cap 600 - tidak terpotong.
        $this->assertSame(480, $record->working_minutes);
    }

    /**
     * Lembur ekstrem: total jam kerja mentah kelewat max_work_minutes (600) - working_minutes
     * dipotong ke batas cap, TAPI overtime_minutes tetap dihitung dari durasi checkout ASLI
     * (overtimeAfter tidak dipotong oleh max_work_minutes, cuma working_minutes).
     */
    public function test_pagi_ketat_excessive_hours_capped_at_max_work_minutes(): void
    {
        $joko = $this->makeEmployee('KETAT004', 'Joko');
        $this->assignShift($joko, $this->pagiKetat, '2026-08-10');
        // Pulang jam 20:00 - mentah (20:00-08:00)-60=660 menit, kelewat cap 600.
        $this->insertScan($joko, '2026-08-10 08:00:00');
        $this->insertScan($joko, '2026-08-10 20:00:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'KETAT004')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(600, $record->working_minutes, 'Terpotong ke max_work_minutes, bukan 660 mentah.');
        // overtime_after: jendela 16:00-20:00 (240 menit), co=20:00 pas di batas countedTo ->
        // effectiveCo=countedTo=20:00. diff(16:00,20:00)=240, dipotong break 30 -> 210.
        $this->assertSame(210.0, $record->overtime_minutes);
        $this->assertTrue((bool) $record->out_of_window);
    }

    /**
     * working_minutes di bawah half_day_threshold_minutes (240) tapi masih >= absent_threshold
     * (60) -> work_day_weight setengah hari (0.5), bukan 0 atau 1 penuh.
     */
    public function test_pagi_ketat_short_day_below_half_day_threshold_gets_half_weight(): void
    {
        $kiki = $this->makeEmployee('KETAT005', 'Kiki');
        $this->assignShift($kiki, $this->pagiKetat, '2026-08-10');
        // Pulang 11:30 - mentah (11:30-08:00)-60=150 menit (di bawah 240, di atas 60).
        $this->insertScan($kiki, '2026-08-10 08:00:00');
        $this->insertScan($kiki, '2026-08-10 11:30:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'KETAT005')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(150, $record->working_minutes);
        $this->assertSame('0.5', $record->work_day_weight);
    }

    /**
     * working_minutes di bawah absent_threshold_minutes (60) -> work_day_weight 0 (dianggap
     * tidak masuk secara efektif) walaupun ada check-in & check-out yang sah.
     */
    public function test_pagi_ketat_very_short_day_below_absent_threshold_gets_zero_weight(): void
    {
        $lia = $this->makeEmployee('KETAT006', 'Lia');
        $this->assignShift($lia, $this->pagiKetat, '2026-08-10');
        // Pulang 08:45 - mentah (08:45-08:00)-60 = -15 -> dibulatkan ke 0 (floor di calculator).
        $this->insertScan($lia, '2026-08-10 08:00:00');
        $this->insertScan($lia, '2026-08-10 08:45:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $record = AttendanceRecords::where('nip', 'KETAT006')->where('date', '2026-08-10')->firstOrFail();

        $this->assertSame(0, $record->working_minutes);
        $this->assertSame('0.0', $record->work_day_weight);
    }

    /**
     * Roster eksplisit "Libur" (off_day=true, tanpa shift_type) - scan yang tetap masuk (mis.
     * karyawan iseng/salah tap kartu pas hari liburnya) masuk bucket 'off' di reader, TAPI
     * SyncAccessLogsAttendance cuma memproses bucket fixed/flexible/shift - bucket 'off' tidak
     * pernah di-loop sama sekali. Hasilnya: TIDAK ADA attendance_records yang terbentuk buat
     * tanggal itu, walau device mencatat scan-nya. Ini perilaku actual pipeline saat ini,
     * didokumentasikan lewat test supaya kalau berubah (sengaja atau tidak) ketahuan.
     */
    public function test_shift_roster_off_day_scan_produces_no_attendance_record(): void
    {
        $mira = $this->makeEmployee('OFF001', 'Mira');
        $this->assignOffDay($mira, '2026-08-10');
        $this->insertScan($mira, '2026-08-10 08:05:00');

        $this->artisan('attendance:sync-access-logs')->assertSuccessful();

        $this->assertNull(AttendanceRecords::where('nip', 'OFF001')->where('date', '2026-08-10')->first());
    }
}
