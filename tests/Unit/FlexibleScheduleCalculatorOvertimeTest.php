<?php

namespace Tests\Unit;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleFlexibleDay;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use PHPUnit\Framework\TestCase;

/**
 * overtimeCandidate >= overtime_min_minutes (bukan >) - "minimal X menit" berarti PAS di X menit
 * sudah memenuhi syarat. Ditemukan & dibenarkan 2026-08-24 (sebelumnya kerja lembur PAS di
 * ambang minimal tidak terhitung sama sekali).
 */
class FlexibleScheduleCalculatorOvertimeTest extends TestCase
{
    private function makeEntry(array $configOverrides): array
    {
        return [
            'schedule' => new Schedule(['flexible_checkin_from' => null, 'flexible_checkin_until' => null]),
            'config' => new ScheduleFlexibleDay(array_merge([
                'break_minutes' => 0,
                'min_work_minutes' => 0,
                'max_work_minutes' => 480, // 8 jam target
                'overtime_break_minutes' => 0,
                'overtime_min_minutes' => 60,
                'overtime_max_minutes' => 180,
                'late_checkin_deadline' => null,
                'counted_work_days' => 1,
                'half_day_threshold_minutes' => 0,
            ], $configOverrides)),
            'check_in' => '08:00:00',
            'check_out' => null,
        ];
    }

    public function test_overtime_exactly_at_minimum_threshold_is_counted(): void
    {
        // Kerja 9 jam (540 menit) - target 480 = kandidat lembur 60 menit, PAS di overtime_min_minutes.
        $entry = $this->makeEntry([]);
        $entry['check_out'] = '17:00:00'; // 08:00 - 17:00 = 540 menit

        $result = (new FlexibleScheduleCalculator())->calculate([$entry])[0];

        $this->assertSame(60, $result['overtime_minutes']);
    }

    public function test_overtime_below_minimum_threshold_is_not_counted(): void
    {
        // Kandidat lembur 59 menit - di bawah overtime_min_minutes (60), tidak dihitung.
        $entry = $this->makeEntry([]);
        $entry['check_out'] = '16:59:00'; // 08:00 - 16:59 = 539 menit

        $result = (new FlexibleScheduleCalculator())->calculate([$entry])[0];

        $this->assertNull($result['overtime_minutes']);
    }
}
