<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Models\User;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi (production RS, 2026-09-22): karyawan Flexible scan 2x berjarak beberapa DETIK (jari
 * kepencet 2x di device) - scan kedua langsung dianggap jam pulang, working_minutes ~0, padahal
 * jadwalnya minta minimal 300 menit. Flexible sekarang ikut menolak checkout di bawah
 * min_work_minutes (pola sama FixedScheduleCalculator), bukan cuma menandai meets_target=false.
 */
class FlexibleMinWorkCheckoutTest extends TestCase
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

    /** Flexible dengan min_work_minutes 300 menit, hari kerja 2026-08-06 (Kamis). */
    private function flexibleUser(string $nip): User
    {
        $user = User::factory()->create(['nip' => $nip, 'company_id' => $this->company->id]);
        $schedule = Schedule::create([
            'company_id' => $this->company->id, 'name' => 'Fleksibel', 'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00', 'flexible_checkin_until' => '10:00:00',
        ]);
        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'break_minutes' => 0,
            'min_work_minutes' => 300, 'max_work_minutes' => 480, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        return $user;
    }

    /**
     * Scan kedua yang JELAS bukan pantulan device (2 jam sesudah masuk - tap ganda sudah dibuang
     * lebih dulu oleh AccessLogScheduleReader, lihat RepeatedTapScanTest) tapi durasinya masih di
     * bawah minimum 300 menit: checkout ditolak, jam masuk tetap tercatat.
     */
    public function test_checkout_below_the_minimum_duration_is_rejected(): void
    {
        $user = $this->flexibleUser('FLXDBL');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 10:00:00'); // 120 menit < 300

        $entry = app(AccessLogScheduleReader::class)->readAndGroupIncremental(null)['buckets']['flexible'][0];
        $result = app(FlexibleScheduleCalculator::class)->calculate([$entry])[0];

        $this->assertTrue($result['checkout_rejected']);
        $this->assertNull($result['check_out'], 'Jam pulang tidak boleh terisi kalau durasinya di bawah minimum.');
        $this->assertNull($result['working_minutes']);
        $this->assertSame('08:00:00', $result['check_in'], 'Jam masuk tetap tercatat.');
    }

    public function test_checkout_is_accepted_once_duration_meets_the_minimum(): void
    {
        $user = $this->flexibleUser('FLXOK');
        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 14:00:00'); // 360 menit >= 300

        $entry = app(AccessLogScheduleReader::class)->readAndGroupIncremental(null)['buckets']['flexible'][0];
        $result = app(FlexibleScheduleCalculator::class)->calculate([$entry])[0];

        $this->assertFalse($result['checkout_rejected']);
        $this->assertSame('14:00:00', $result['check_out']);
        $this->assertSame(360, $result['working_minutes']);
    }

    /**
     * Target = max_work_minutes (480), min_work_minutes (300) cuma pagar pulang duluan: pulang di
     * antara keduanya diterima & tersimpan tapi belum capai target; capai 480 baru meets_target.
     */
    public function test_meets_target_compares_to_max_not_min(): void
    {
        $early = $this->flexibleUser('FLXEARLY');
        $this->scan($early, '2026-08-06 08:00:00');
        $this->scan($early, '2026-08-06 14:00:00'); // 360: >= min 300, < target 480

        $full = $this->flexibleUser('FLXFULL');
        $this->scan($full, '2026-08-06 08:00:00');
        $this->scan($full, '2026-08-06 16:00:00'); // 480 = target

        $buckets = app(AccessLogScheduleReader::class)->readAndGroupIncremental(null)['buckets']['flexible'];
        $results = collect(app(FlexibleScheduleCalculator::class)->calculate($buckets))
            ->keyBy(fn ($r) => $r['user']->nip);

        $this->assertFalse($results['FLXEARLY']['checkout_rejected']);
        $this->assertSame('14:00:00', $results['FLXEARLY']['check_out']);
        $this->assertFalse($results['FLXEARLY']['meets_target']);
        $this->assertTrue($results['FLXFULL']['meets_target']);
    }

    /** min_work_minutes 0 = pagar dimatikan, perilaku lama (checkout selalu diterima). */
    public function test_no_minimum_configured_keeps_accepting_any_checkout(): void
    {
        $user = User::factory()->create(['nip' => 'FLXOFF', 'company_id' => $this->company->id]);
        $schedule = Schedule::create([
            'company_id' => $this->company->id, 'name' => 'Fleksibel Bebas', 'schedule_type' => 'flexible',
            'flexible_checkin_from' => '06:00:00', 'flexible_checkin_until' => '10:00:00',
        ]);
        ScheduleFlexibleDay::create([
            'schedule_id' => $schedule->id, 'day_of_week' => 4, 'is_off_day' => false, 'break_minutes' => 0,
            'min_work_minutes' => 0, 'max_work_minutes' => 480, 'counted_work_days' => 1,
        ]);
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2020-01-01']);

        $this->scan($user, '2026-08-06 08:00:00');
        $this->scan($user, '2026-08-06 09:00:00'); // 60 menit, tetap diterima karena minimum dimatikan

        $entry = app(AccessLogScheduleReader::class)->readAndGroupIncremental(null)['buckets']['flexible'][0];
        $result = app(FlexibleScheduleCalculator::class)->calculate([$entry])[0];

        $this->assertFalse($result['checkout_rejected']);
        $this->assertSame('09:00:00', $result['check_out']);
    }
}
