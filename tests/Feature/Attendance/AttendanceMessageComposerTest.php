<?php

namespace Tests\Feature\Attendance;

use App\Services\Attendance\AttendanceMessageComposer as Composer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pesan absensi masuk/pulang: tiap keadaan punya judul/isi sendiri (tepat waktu, terlambat,
 * lebih awal, pulang lebih awal dengan/tanpa izin, fleksibel, shift extra/double shift).
 */
class AttendanceMessageComposerTest extends TestCase
{
    private const BASE = ['date' => '2026-10-09', 'kind' => 'fixed'];

    public static function checkIns(): array
    {
        return [
            'tepat waktu' => [[], '07:58:00', 'Hadir Tepat Waktu 👍', 'hadir tepat waktu'],
            'sedikit lebih awal (20 menit) dianggap tepat waktu' => [['early_arrival_minutes' => 20], '07:40:00', 'Hadir Tepat Waktu 👍', 'hadir tepat waktu'],
            'lebih awal 30 menit' => [['early_arrival_minutes' => 30], '07:30:00', 'Semangat Pagi! Datang Lebih Awal ☀️', '30 menit sebelum jadwal'],
            'lebih awal 45 menit' => [['early_arrival_minutes' => 45], '07:15:00', 'Semangat Pagi! Datang Lebih Awal ☀️', '45 menit sebelum jadwal'],
            'jauh lebih awal 60 menit' => [['early_arrival_minutes' => 60], '07:00:00', 'Luar Biasa, Datang Jauh Lebih Awal! 🌟', 'datang 1 jam lebih awal dari jadwal'],
            'jauh lebih awal 80 menit' => [['early_arrival_minutes' => 80], '06:40:00', 'Luar Biasa, Datang Jauh Lebih Awal! 🌟', '1 jam 20 menit lebih awal'],
            'terlambat tanpa izin' => [['late_minutes' => 20, 'permitted_late' => false], '08:30:00', 'Sedikit Terlambat Hari Ini ⏰', 'terlambat 20 menit dari jadwal. Tidak apa-apa'],
            'terlambat dengan izin' => [['late_minutes' => 75, 'permitted_late' => true], '09:25:00', 'Terlambat, Izin Sudah Tercatat 📝', 'terlambat 1 jam 15 menit dari jadwal. Keterlambatan ini sudah memiliki izin'],
            'senin holding tepat waktu' => [['corporate_value_reminder' => true], '07:58:00', 'Hadir Tepat Waktu 👍', 'Jangan lupa Corporate Value'],
            'senin holding lebih awal' => [['corporate_value_reminder' => true, 'early_arrival_minutes' => 45], '07:15:00', 'Semangat Pagi! Datang Lebih Awal ☀️', 'Jangan lupa Corporate Value'],
            'fleksibel' => [['kind' => 'flexible'], '08:40:00', 'Selamat Bekerja! 😊', 'Semoga harinya lancar dan menyenangkan'],
            'fleksibel melewati batas' => [['kind' => 'flexible', 'late_minutes' => 30], '09:30:00', 'Sedikit Melewati Batas Jam Masuk ⏰', 'melewati batas jam masuk selama 30 menit'],
            'shift normal' => [['kind' => 'shift', 'shift_label' => 'Pagi'], '07:58:00', 'Hadir Tepat Waktu 👍', 'shift Pagi Anda'],
            'extra shift' => [['kind' => 'extra', 'shift_label' => 'Siang', 'category' => 'extra_shift'], '14:00:00', 'Terima Kasih Sudah Menambah Shift! 💪', 'shift extra Siang'],
            'double shift' => [['kind' => 'extra', 'shift_label' => 'Siang', 'category' => 'double_shift'], '14:00:00', 'Shift Lanjutan Dimulai, Semangat! 💪', 'shift lanjutan (double shift) Siang'],
        ];
    }

    #[DataProvider('checkIns')]
    public function test_check_in_message(array $context, string $time, string $title, string $bodyContains): void
    {
        [$gotTitle, $body] = Composer::checkIn($time, $context + self::BASE);

        $this->assertSame($title, $gotTitle);
        $this->assertStringContainsString($bodyContains, $body);
        $this->assertStringContainsString('pukul ' . substr($time, 0, 5), $body);
    }

    public function test_corporate_value_reminder_is_not_added_when_late_or_absent(): void
    {
        [, $late] = Composer::checkIn('08:30:00', ['corporate_value_reminder' => true, 'late_minutes' => 20] + self::BASE);
        [, $plain] = Composer::checkIn('07:58:00', self::BASE);

        $this->assertStringNotContainsString('Corporate Value', $late);
        $this->assertStringNotContainsString('Corporate Value', $plain);
    }

    public static function checkOuts(): array
    {
        return [
            'pulang normal' => [[], 480, '16:05:00', 'Kerja Bagus Hari Ini! 🙌', 'Total jam kerja 8 jam'],
            'pulang lebih awal dengan izin' => [['early_leave_minutes' => 50, 'permitted_early_leave' => true], 420, '15:10:00', 'Pulang Lebih Awal, Izin Tercatat 📝', '50 menit lebih awal dari jadwal, sesuai izin yang telah disetujui'],
            'pulang lebih awal tanpa izin' => [['early_leave_minutes' => 50, 'permitted_early_leave' => false], 420, '15:10:00', 'Pulang Lebih Awal, Jangan Lupa Izinnya ya 📝', 'belum ada izin pulang lebih awal yang tercatat'],
            'pulang lebih awal izin tidak diketahui' => [['early_leave_minutes' => 50], 420, '15:10:00', 'Absensi Pulang Tercatat 👋', '50 menit lebih awal dari jadwal. Hati-hati'],
            'fleksibel target tercapai' => [['kind' => 'flexible', 'meets_target' => true], 490, '17:30:00', 'Target Tercapai, Keren! 🎯', 'Target jam kerja Anda hari ini tercapai'],
            'fleksibel belum tercapai' => [['kind' => 'flexible', 'meets_target' => false, 'target_minutes' => 480], 450, '16:30:00', 'Sedikit Lagi, Semangat Besok! 💪', 'belum mencapai target 8 jam'],
            'extra shift' => [['kind' => 'extra', 'shift_label' => 'Siang', 'category' => 'extra_shift'], 398, '20:38:00', 'Shift Tambahan Selesai, Terima Kasih! 🙏', 'shift extra Siang tercatat'],
            'jam kerja tidak diketahui' => [[], null, '16:00:00', 'Kerja Bagus Hari Ini! 🙌', 'tercatat pukul 16:00 pada'],
        ];
    }

    #[DataProvider('checkOuts')]
    public function test_check_out_message(array $context, ?int $working, string $time, string $title, string $bodyContains): void
    {
        [$gotTitle, $body] = Composer::checkOut($time, $working, $context + self::BASE);

        $this->assertSame($title, $gotTitle);
        $this->assertStringContainsString($bodyContains, $body);

        if ($working === null) {
            $this->assertStringNotContainsString('Total jam kerja', $body);
        }
    }
}
