<?php

namespace App\Services\Attendance;

use Carbon\Carbon;

/**
 * Menyusun judul + isi notifikasi absensi masuk/pulang: formal tapi ramah, berbeda menurut
 * keadaannya (tepat waktu, terlambat, lebih awal 30/60 menit, pulang lebih awal dengan/tanpa
 * izin, jadwal fleksibel, shift extra/double shift).
 *
 * Konteks ($c) disusun pemanggil dari hasil perhitungan (lihat SyncAccessLogsAttendance):
 *   kind                   fixed | flexible | shift | extra
 *   date                   Y-m-d
 *   shift_label            nama shift (shift & extra), mis. "Siang"
 *   category               extra_shift | double_shift (hanya kind extra)
 *   late_minutes           menit terlambat
 *   early_arrival_minutes  menit datang sebelum jadwal mulai (fixed & shift)
 *   early_leave_minutes    menit pulang sebelum jadwal selesai
 *   permitted_late         izin terlambat disetujui? true/false, null = tidak diketahui
 *   permitted_early_leave  izin pulang lebih awal disetujui? true/false, null = tidak diketahui
 *   meets_target           fleksibel: tercapai/tidak, null = tidak diketahui
 *   target_minutes         fleksibel: target jam kerja
 *   corporate_value_reminder  true = tambahkan pengingat Corporate Value (holding, hari Senin; hanya
 *                          untuk masuk yang tidak terlambat)
 */
class AttendanceMessageComposer
{
    /** Datang sekian menit (atau lebih) sebelum jadwal mulai disebut "lebih awal". */
    public const EARLY_ARRIVAL_MINUTES = 30;

    /** Datang sekian menit (atau lebih) sebelum jadwal mulai disebut "jauh lebih awal". */
    public const VERY_EARLY_ARRIVAL_MINUTES = 60;

    /** @return array{0: string, 1: string} [judul, isi] */
    public static function checkIn(string $time, array $c): array
    {
        $when = self::when($time, $c['date']);
        $kind = $c['kind'] ?? 'fixed';
        $late = (int) ($c['late_minutes'] ?? 0);
        $early = (int) ($c['early_arrival_minutes'] ?? 0);

        // Pengingat khusus (mis. holding, hari Senin) - hanya untuk masuk yang TIDAK terlambat.
        $reminder = ! empty($c['corporate_value_reminder']) && $late === 0 ? ' Jangan lupa Corporate Value ya! ✨' : '';

        if ($kind === 'extra') {
            $double = ($c['category'] ?? 'extra_shift') === 'double_shift';

            return [
                $double ? 'Shift Lanjutan Dimulai, Semangat! 💪' : 'Terima Kasih Sudah Menambah Shift! 💪',
                'Absensi masuk ' . self::extraLabel($c) . " tercatat {$when}. Terima kasih atas kesediaan Anda mendukung tim, selamat bertugas! 🙏",
            ];
        }

        $subject = ! empty($c['shift_label']) ? "Absensi masuk shift {$c['shift_label']} Anda" : 'Absensi masuk Anda';

        if ($kind === 'flexible') {
            return $late > 0
                ? ['Sedikit Melewati Batas Jam Masuk ⏰', "{$subject} tercatat {$when}, melewati batas jam masuk selama " . self::duration($late) . '. Mohon diperhatikan ke depannya, ya. Selamat bekerja! 💪']
                : ['Selamat Bekerja! 😊', "{$subject} tercatat {$when}. Semoga harinya lancar dan menyenangkan, selamat bekerja!{$reminder}"];
        }

        if ($late > 0) {
            $permitted = $c['permitted_late'] ?? null;
            $tail = match ($permitted) {
                true => 'Keterlambatan ini sudah memiliki izin yang disetujui, jadi tenang saja.',
                false => 'Tidak apa-apa, semoga besok bisa lebih awal, ya. Mohon perhatikan waktu kedatangan Anda.',
                default => 'Terima kasih atas perhatiannya.',
            };

            return [
                $permitted === true ? 'Terlambat, Izin Sudah Tercatat 📝' : 'Sedikit Terlambat Hari Ini ⏰',
                "{$subject} tercatat {$when}, terlambat " . self::duration($late) . " dari jadwal. {$tail} Selamat bekerja! 💪",
            ];
        }

        if ($early >= self::VERY_EARLY_ARRIVAL_MINUTES) {
            return [
                'Luar Biasa, Datang Jauh Lebih Awal! 🌟',
                'Wow, Anda datang ' . self::duration($early) . " lebih awal dari jadwal! {$subject} tercatat {$when}. Terima kasih atas dedikasi dan semangat Anda, selamat bekerja! 🚀{$reminder}",
            ];
        }

        if ($early >= self::EARLY_ARRIVAL_MINUTES) {
            return [
                'Semangat Pagi! Datang Lebih Awal ☀️',
                "Terima kasih telah datang lebih awal. {$subject} tercatat {$when}, " . self::duration($early) . ' sebelum jadwal. Selamat bekerja! 😊' . $reminder,
            ];
        }

        return ['Hadir Tepat Waktu 👍', "Terima kasih telah hadir tepat waktu. {$subject} tercatat {$when}. Selamat bekerja, semoga harinya menyenangkan! 😊{$reminder}"];
    }

    /** @return array{0: string, 1: string} [judul, isi] */
    public static function checkOut(string $time, ?int $workingMinutes, array $c): array
    {
        $when = self::when($time, $c['date']);
        $kind = $c['kind'] ?? 'fixed';
        $early = (int) ($c['early_leave_minutes'] ?? 0);
        $worked = $workingMinutes !== null ? ' Total jam kerja ' . self::duration($workingMinutes) . '.' : '';

        if ($kind === 'extra') {
            return [
                'Shift Tambahan Selesai, Terima Kasih! 🙏',
                'Absensi pulang ' . self::extraLabel($c) . " tercatat {$when}.{$worked} Terima kasih atas dedikasi dan bantuan Anda untuk tim. Selamat beristirahat! 🌙",
            ];
        }

        $subject = ! empty($c['shift_label']) ? "Absensi pulang shift {$c['shift_label']} Anda" : 'Absensi pulang Anda';

        if ($kind === 'flexible') {
            $meets = $c['meets_target'] ?? null;
            $target = ! empty($c['target_minutes']) ? self::duration((int) $c['target_minutes']) : null;

            if ($meets === true) {
                return ['Target Tercapai, Keren! 🎯', "{$subject} tercatat {$when}.{$worked} Target jam kerja Anda hari ini tercapai. Terima kasih atas kerja kerasnya! 🙌"];
            }

            if ($meets === false && $target !== null) {
                return ['Sedikit Lagi, Semangat Besok! 💪', "{$subject} tercatat {$when}.{$worked} Jam kerja belum mencapai target {$target}. Semoga besok bisa lebih baik, ya. 😊"];
            }

            return ['Absensi Pulang Tercatat 👋', "{$subject} tercatat {$when}.{$worked} Terima kasih atas kerja kerasnya!"];
        }

        if ($early > 0) {
            return match ($c['permitted_early_leave'] ?? null) {
                true => [
                    'Pulang Lebih Awal, Izin Tercatat 📝',
                    "{$subject} tercatat {$when}, " . self::duration($early) . ' lebih awal dari jadwal, sesuai izin yang telah disetujui. Terima kasih sudah mengajukan izin terlebih dahulu. Hati-hati di jalan! 🚗',
                ],
                false => [
                    'Pulang Lebih Awal, Jangan Lupa Izinnya ya 📝',
                    "{$subject} tercatat {$when}, " . self::duration($early) . ' lebih awal dari jadwal, dan belum ada izin pulang lebih awal yang tercatat. Bila ada keperluan, mohon ajukan izin melalui aplikasi agar tercatat dengan baik. 🙏',
                ],
                default => [
                    'Absensi Pulang Tercatat 👋',
                    "{$subject} tercatat {$when}, " . self::duration($early) . ' lebih awal dari jadwal. Hati-hati di jalan! 🚗',
                ],
            };
        }

        return ['Kerja Bagus Hari Ini! 🙌', "{$subject} tercatat {$when}.{$worked} Terima kasih atas kerja kerasnya, selamat beristirahat! 🌙"];
    }

    private static function when(string $time, string $date): string
    {
        return 'pukul ' . substr($time, 0, 5) . ' pada ' . Carbon::parse($date)->translatedFormat('d M Y');
    }

    private static function extraLabel(array $c): string
    {
        $base = ($c['category'] ?? 'extra_shift') === 'double_shift' ? 'shift lanjutan (double shift)' : 'shift extra';

        return ! empty($c['shift_label']) ? "{$base} {$c['shift_label']}" : $base;
    }

    private static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours > 0 && $rest > 0 => "{$hours} jam {$rest} menit",
            $hours > 0 => "{$hours} jam",
            default => "{$rest} menit",
        };
    }
}
