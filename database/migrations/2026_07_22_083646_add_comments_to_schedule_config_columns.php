<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cuma nambah COMMENT MySQL ke kolom-kolom "Pengaturan Lanjutan" (schedules,
     * schedule_fixed_days, schedule_flexible_days, schedule_shift_types) - TIDAK mengubah
     * tipe/nullable/default kolom manapun (setiap MODIFY COLUMN di bawah restate definisi
     * asli persis, dicek langsung dari `SHOW FULL COLUMNS` sebelum ditulis).
     *
     * Kenapa: pola nama & perilaku kolom-kolom ini niru fingerspot.io, bukan didesain dari nol -
     * programmer berikutnya yang buka tabel ini langsung (tanpa baca planning docs) gampang
     * salah tebak makna kolom (terutama bedanya max_work_minutes di Fixed/Shift vs Flexible).
     * Referensi visual asli: knowledge/absensi/*.png (screenshot fingerspot.io yang jadi acuan
     * desain), dan planning/2026-07-18-restrukturisasi-jadwal-schedules.md untuk keputusan
     * desainnya.
     */
    public function up(): void
    {
        // ponytail: COMMENT MySQL murni metadata dokumentasi, tidak ada di SQLite (dipakai
        // test suite via phpunit.xml) - skip di driver lain, tidak mengubah schema produksi.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columnDefinitions() as $table => $columns) {
            foreach ($columns as $column => [$definition, $comment]) {
                DB::statement(sprintf(
                    "ALTER TABLE `%s` MODIFY COLUMN `%s` %s COMMENT %s",
                    $table,
                    $column,
                    $definition,
                    DB::getPdo()->quote($comment)
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columnDefinitions() as $table => $columns) {
            foreach ($columns as $column => [$definition]) {
                DB::statement(sprintf(
                    "ALTER TABLE `%s` MODIFY COLUMN `%s` %s",
                    $table,
                    $column,
                    $definition
                ));
            }
        }
    }

    /**
     * @return array<string, array<string, array{0: string, 1: string}>>
     *     table => [column => [tipe SQL asli (tanpa comment), teks comment]]
     */
    private function columnDefinitions(): array
    {
        $fingerspot  = 'Pola & nama kolom niru fingerspot.io.';
        $refFixed    = "Referensi: knowledge/absensi/KONFIGURASI JADWAL FIXED.png, CONTOH JADWAL FIXED.png.";
        $refFlexible = "Referensi: knowledge/absensi/KONFIGURASI JADWAL FLEXIBLE.png, CONTOH JADWAL FLEXIBEL.png.";
        $refShift    = "Referensi: knowledge/absensi/KONFIGURASI JADWAL SHIFT.png, CONTOH JADWAL SHIFT.png.";

        // Kolom "Pengaturan Lanjutan" yang sama persis di fixed_days/flexible_days/shift_types.
        $advancedCommon = fn (string $ref) => [
            'checkin_buffer_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                "Batas paling awal boleh scan masuk = jam_mulai - buffer ini (menit), scan sebelum itu dianggap di luar jendela valid. {$ref}"],
            'checkout_buffer_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                "Batas paling akhir boleh scan pulang = jam_selesai + buffer ini (menit). {$ref}"],
            'late_tolerance_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                "Toleransi keterlambatan (menit) sebelum check-in dianggap telat. {$ref}"],
            'early_leave_tolerance_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                "Toleransi pulang cepat (menit) sebelum check-out dianggap pulang cepat. {$ref}"],
            'counted_work_days' => ["tinyint unsigned NOT NULL DEFAULT '1'",
                "Bobot hari ini dihitung sebagai berapa hari kerja untuk rekap (biasanya 1). Belum dipakai calculator manapun per 2026-07-22."],
            'half_day_threshold_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                "Ambang jam kerja (menit) - di bawah ini dianggap setengah hari kalau >0. BELUM dipakai calculator manapun per 2026-07-22 (AttendanceStatus enum belum ada case setengah-hari)."],
            'absent_threshold_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                "Ambang jam kerja (menit) - di bawah ini dianggap alpha meski sudah scan, kalau >0. BELUM dipakai calculator manapun per 2026-07-22."],
        ];

        return [
            'schedules' => [
                'schedule_type' => ["varchar(255) NOT NULL",
                    "fixed|flexible|shift (App\\Enum\\ScheduleType) - menentukan tabel anak mana yang relevan: schedule_fixed_days/schedule_flexible_days/schedule_shift_types. {$fingerspot}"],
                'use_template' => ["tinyint(1) NOT NULL DEFAULT '0'",
                    "Saklar tipe Shift: kalau true, roster (schedule_shift_assignments) diisi via generate dari Template Shift (schedule_shift_rotations), bukan manual murni."],
                'flexible_checkin_from' => ["time NULL",
                    "Jendela jam boleh check-in untuk jadwal Flexible - 1 nilai per schedule (BUKAN per hari, beda dari schedule_flexible_days). {$refFlexible}"],
                'flexible_checkin_until' => ["time NULL",
                    "Batas akhir jendela check-in Flexible, lihat flexible_checkin_from. {$refFlexible}"],
            ],
            'schedule_fixed_days' => [
                'day_of_week' => ["tinyint unsigned NOT NULL",
                    "0=Minggu..6=Sabtu (Carbon::dayOfWeek) - 1 baris per hari per schedule_id."],
                'is_off_day' => ["tinyint(1) NOT NULL DEFAULT '0'",
                    "Hari libur - kalau true, seluruh kolom jam/toleransi di baris ini diabaikan (tidak dihitung telat/lembur). {$refFixed}"],
                'start_time' => ["time NULL", "Jam mulai kerja hari ini. {$refFixed}"],
                'end_time'   => ["time NULL", "Jam selesai kerja hari ini. {$refFixed}"],
                'break_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Durasi istirahat (menit), dikurangkan dari total jam kerja."],
                'overtime_before_min' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur SEBELUM jam masuk: minimal menit check-in lebih awal supaya dianggap lembur. {$refFixed}"],
                'overtime_before_break' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur sebelum jam masuk: jeda/gap (menit) sebelum mulai dihitung lembur."],
                'overtime_before_max' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur sebelum jam masuk: batas maksimal menit yang dihitung."],
                'overtime_after_min' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur SETELAH jam pulang: minimal menit check-out lebih lambat supaya dianggap lembur. {$refFixed}"],
                'overtime_after_break' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur setelah jam pulang: jeda/gap (menit) sebelum mulai dihitung lembur."],
                'overtime_after_max' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur setelah jam pulang: batas maksimal menit yang dihitung."],
                'max_work_minutes' => ["smallint unsigned NULL",
                    "Batas maksimal jam kerja yang dihitung (menit) - lebih dari ini dipotong, tidak nambah working_minutes."],
                ...$advancedCommon($refFixed),
            ],
            'schedule_flexible_days' => [
                'day_of_week' => ["tinyint unsigned NOT NULL",
                    "0=Minggu..6=Sabtu (Carbon::dayOfWeek) - 1 baris per hari per schedule_id."],
                'is_off_day' => ["tinyint(1) NOT NULL DEFAULT '0'",
                    "Hari libur - kalau true, seluruh kolom target/toleransi di baris ini diabaikan. {$refFlexible}"],
                'break_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Durasi istirahat (menit), dikurangkan dari total jam kerja."],
                'min_work_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Target MINIMAL jam kerja (menit) per hari - di bawah ini dianggap kurang dari target. {$refFlexible}"],
                'max_work_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "PENTING beda makna dari Fixed/Shift: ini target jam kerja NORMAL (dipasangkan dengan min_work_minutes), BUKAN batas mutlak. Batas mutlaknya kolom max_work_minutes_cap. {$refFlexible}"],
                'max_work_minutes_cap' => ["smallint unsigned NULL",
                    "Batas maksimal MUTLAK jam kerja yang dihitung (menit) - setara peran max_work_minutes di Fixed/Shift. {$refFlexible}"],
                'overtime_break_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur Flexible: jeda/gap (menit) sebelum mulai dihitung lembur (kerja di atas max_work_minutes target)."],
                'overtime_min_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur Flexible: minimal menit kerja di atas target supaya dianggap lembur."],
                'overtime_max_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur Flexible: batas maksimal menit lembur yang dihitung."],
                ...$advancedCommon($refFlexible),
            ],
            'schedule_shift_types' => [
                'shift_type' => ["varchar(255) NULL",
                    "PAGI|SIANG|MALAM (App\\Enum\\ShiftType) - opsional, cuma label kategori tampilan, tidak mempengaruhi perhitungan."],
                'start_time' => ["time NOT NULL", "Jam mulai shift ini. {$refShift}"],
                'end_time'   => ["time NOT NULL", "Jam selesai shift ini. {$refShift}"],
                'break_minutes' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Durasi istirahat (menit), dikurangkan dari total jam kerja."],
                'is_overnight' => ["tinyint(1) NOT NULL DEFAULT '0'",
                    "Shift melewati tengah malam (end_time < start_time secara jam) - kalau true, end_time dianggap +1 hari saat hitung durasi."],
                'overtime_before_min' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur SEBELUM jam masuk: minimal menit check-in lebih awal supaya dianggap lembur. {$refShift}"],
                'overtime_before_break' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur sebelum jam masuk: jeda/gap (menit) sebelum mulai dihitung lembur."],
                'overtime_before_max' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur sebelum jam masuk: batas maksimal menit yang dihitung."],
                'overtime_after_min' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur SETELAH jam pulang: minimal menit check-out lebih lambat supaya dianggap lembur. {$refShift}"],
                'overtime_after_break' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur setelah jam pulang: jeda/gap (menit) sebelum mulai dihitung lembur."],
                'overtime_after_max' => ["smallint unsigned NOT NULL DEFAULT '0'",
                    "Lembur setelah jam pulang: batas maksimal menit yang dihitung."],
                'max_work_minutes' => ["smallint unsigned NULL",
                    "Batas maksimal jam kerja yang dihitung (menit) - lebih dari ini dipotong, tidak nambah working_minutes."],
                'color' => ["varchar(255) NULL",
                    "Warna background solid buat tampilan kode shift ini di UI - dipakai APA ADANYA, tidak diadaptasi tema gelap/terang."],
                'font_color' => ["varchar(255) NULL",
                    "Warna teks solid pasangan color, dipilih manual supaya kontras - juga tidak diadaptasi tema."],
                ...$advancedCommon($refShift),
            ],
        ];
    }
};
