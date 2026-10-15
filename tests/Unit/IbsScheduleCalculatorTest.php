<?php

namespace Tests\Unit;

use App\Services\Attendance\Calculators\IbsScheduleCalculator;
use PHPUnit\Framework\TestCase;

/** Pairing scan Jadwal IBS: genap, ganjil (pasangan terbuka), lewat tengah malam, lupa scan pulang. */
class IbsScheduleCalculatorTest extends TestCase
{
    public function test_even_scans_are_paired_and_minutes_summed(): void
    {
        $sessions = IbsScheduleCalculator::pair([
            '2026-10-16 08:00:00', '2026-10-16 09:30:00',
            '2026-10-16 16:00:00', '2026-10-16 21:00:00',
        ]);

        $this->assertSame([90, 300], array_column($sessions, 'minutes'));

        $r = (new IbsScheduleCalculator())->calculate([['date' => '2026-10-16', 'check_in' => null, 'check_out' => null, 'sessions' => $sessions]])[0];

        $this->assertSame(390, $r['working_minutes']);
        $this->assertSame('08:00:00', $r['check_in']);
        $this->assertSame('21:00:00', $r['check_out']);
        $this->assertSame('present', $r['status']);
        $this->assertSame(0, $r['late_minutes']);
    }

    public function test_odd_scan_is_open_pair_with_zero_minutes(): void
    {
        $sessions = IbsScheduleCalculator::pair(['2026-10-16 08:00:00', '2026-10-16 09:00:00', '2026-10-16 16:00:00']);

        $this->assertSame([60, 0], array_column($sessions, 'minutes'));
        $this->assertNull($sessions[1]['out']);

        $r = (new IbsScheduleCalculator())->calculate([['date' => '2026-10-16', 'check_in' => null, 'check_out' => null, 'sessions' => $sessions]])[0];

        // Pulang terakhir yang tercatat = 09:00, pasangan terbuka tidak dihitung.
        $this->assertSame(60, $r['working_minutes']);
        $this->assertSame('09:00:00', $r['check_out']);
    }

    public function test_pair_crossing_midnight_belongs_to_check_in_date(): void
    {
        $sessions = IbsScheduleCalculator::pair(['2026-10-16 22:00:00', '2026-10-17 01:30:00']);

        $this->assertCount(1, $sessions);
        $this->assertSame('2026-10-16', $sessions[0]['date']);
        $this->assertSame(210, $sessions[0]['minutes']);
    }

    public function test_forgotten_checkout_does_not_flip_following_days(): void
    {
        // Masuk 16 Okt 08:00 lupa pulang; 17 Okt 07:00 (23 jam kemudian) adalah MASUK baru, bukan pulang.
        $sessions = IbsScheduleCalculator::pair([
            '2026-10-16 08:00:00',
            '2026-10-17 07:00:00', '2026-10-17 15:00:00',
        ]);

        $this->assertSame(['2026-10-16', '2026-10-17'], array_column($sessions, 'date'));
        $this->assertSame([0, 480], array_column($sessions, 'minutes'));
    }
}
