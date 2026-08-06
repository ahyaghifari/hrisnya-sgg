<?php

namespace App\Console\Commands;

use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Illuminate\Console\Command;

/**
 * Proses access_logs -> jadwal (schedules) -> metrik absensi, lewat pipeline Tahap 1-6 (lihat
 * AccessLogScheduleReader untuk Tahap 1-4, Calculators/* untuk Tahap 5). Command ini cuma
 * orkestrasi (Tahap 6) - baca, hitung per tipe jadwal, tampilkan, dan (kalau --save) simpan ke
 * attendance_records. Dijalankan tiap menit lewat scheduler (lihat bootstrap/app.php).
 *
 * Default dry-run: cuma print ke console. --save menulis ke attendance_records
 * (is_auto_calculated=true, source beda per tipe - lihat AttendanceRecordRepository).
 */
class ProcessAccessLogsSchedule extends Command
{
    protected $signature = 'attendance:process-access-logs {from} {to} {--nip=} {--save : Simpan hasil ke attendance_records, bukan cuma print}';

    protected $description = 'Hitung telat/lembur dari access_logs memakai skema Jadwal (schedules)';

    public function handle(
        AccessLogScheduleReader $reader,
        FixedScheduleCalculator $fixedCalculator,
        FlexibleScheduleCalculator $flexibleCalculator,
        ShiftScheduleCalculator $shiftCalculator,
        AttendanceRecordRepository $attendanceRecordRepository
    ): int {
        $from = $this->argument('from');
        $to   = $this->argument('to');
        $nip  = $this->option('nip');
        $save = (bool) $this->option('save');

        $result = $reader->readAndGroup($from, $to, $nip);

        $this->warn('effective_from pada ScheduleAssignment diabaikan (lihat komentar ponytail di AccessLogScheduleReader).');
        $this->newLine();

        $fixedResults = $fixedCalculator->calculate($result['buckets']['fixed']);
        foreach ($fixedResults as $r) {
            $this->printFixed($r);
            if ($save) {
                $this->save($attendanceRecordRepository, $r, 'access_logs_fixed');
            }
        }

        $this->newLine();

        $flexibleResults = $flexibleCalculator->calculate($result['buckets']['flexible']);
        foreach ($flexibleResults as $r) {
            $this->printFlexible($r);
            if ($save) {
                $this->save($attendanceRecordRepository, $r, 'access_logs_flexible');
            }
        }

        $shiftResults = $shiftCalculator->calculate($result['buckets']['shift']);
        foreach ($shiftResults as $r) {
            $this->printShift($r);
            if ($save) {
                $this->save($attendanceRecordRepository, $r, 'access_logs_shift');
            }
        }

        $this->newLine();
        $this->info('Ringkasan:');
        $this->line('  Fixed dihitung   : ' . count($fixedResults) . ($save ? ' (disimpan)' : ' (dry-run, pakai --save)'));
        $this->line('  Flexible dihitung: ' . count($flexibleResults) . ($save ? ' (disimpan)' : ' (dry-run, pakai --save)'));
        $this->line('  Shift dihitung   : ' . count($shiftResults) . ($save ? ' (disimpan)' : ' (dry-run, pakai --save)'));
        $this->line('  Libur            : ' . count($result['buckets']['off']));
        $this->line('  Jadwal tidak ditemukan: ' . count($result['unresolved_schedule']));
        $this->line('  Device tidak dikenal (diblokir): ' . count($result['rejected_device']));
        $this->line('  User tidak ditemukan (employee_id unik): ' . count($result['unmatched_users']));

        return Command::SUCCESS;
    }

    private function printFixed(array $r): void
    {
        $label = match (true) {
            $r['late_minutes'] > 0 && $r['early_leave_minutes'] > 0 => 'telat & pulang cepat',
            $r['late_minutes'] > 0 => 'telat',
            $r['early_leave_minutes'] > 0 => 'pulang cepat',
            default => 'tepat waktu',
        };

        $this->line(sprintf(
            '<fg=cyan>%s</> [Fixed] %s (%s) — jadwal %s-%s | telat=%dmenit | pulang_cepat=%dmenit | jam_kerja=%s | lembur=%s | hari_kerja=%s | %s (status disimpan: %s)%s',
            $r['date'],
            $r['user']->name,
            $r['user']->nip,
            $r['config']->start_time,
            $r['config']->end_time,
            $r['late_minutes'],
            $r['early_leave_minutes'],
            $r['working_minutes'] === null ? '-' : "{$r['working_minutes']}menit",
            $r['overtime_minutes'] === null ? '-' : "{$r['overtime_minutes']}menit",
            $r['work_day_weight'] ?? '-',
            $label,
            $r['status'],
            ($r['out_of_window'] ? ' [scan masuk/pulang di luar jendela valid]' : '')
                . (! empty($r['checkout_rejected']) ? ' [checkout ditolak: durasi kerja di bawah minimum]' : '')
        ));
    }

    private function printFlexible(array $r): void
    {
        $label = match (true) {
            $r['meets_target'] === null => 'belum check-out',
            $r['meets_target'] => 'memenuhi target',
            default => "kurang dari target ({$r['config']->min_work_minutes}menit)",
        };

        $this->line(sprintf(
            '<fg=magenta>%s</> [Flexible] %s (%s) — jadwal %s | jam_kerja=%s | lembur=%s | hari_kerja=%s | %s (status disimpan: %s)%s',
            $r['date'],
            $r['user']->name,
            $r['user']->nip,
            $r['schedule']->name,
            $r['working_minutes'] === null ? '-' : "{$r['working_minutes']}menit",
            $r['overtime_minutes'] === null ? '-' : "{$r['overtime_minutes']}menit",
            $r['work_day_weight'] ?? '-',
            $label,
            $r['status'],
            $r['out_of_window'] ? ' [scan masuk di luar jendela check-in]' : ''
        ));
    }

    private function printShift(array $r): void
    {
        $label = match (true) {
            $r['late_minutes'] > 0 && $r['early_leave_minutes'] > 0 => 'telat & pulang cepat',
            $r['late_minutes'] > 0 => 'telat',
            $r['early_leave_minutes'] > 0 => 'pulang cepat',
            default => 'tepat waktu',
        };

        $this->line(sprintf(
            '<fg=yellow>%s</> [Shift] %s (%s) — %s %s-%s | telat=%dmenit | pulang_cepat=%dmenit | jam_kerja=%s | lembur=%s | hari_kerja=%s | %s (status disimpan: %s)%s',
            $r['date'],
            $r['user']->name,
            $r['user']->nip,
            $r['config']->name,
            $r['config']->start_time,
            $r['config']->end_time,
            $r['late_minutes'],
            $r['early_leave_minutes'],
            $r['working_minutes'] === null ? '-' : "{$r['working_minutes']}menit",
            $r['overtime_minutes'] === null ? '-' : "{$r['overtime_minutes']}menit",
            $r['work_day_weight'] ?? '-',
            $label,
            $r['status'],
            $r['out_of_window'] ? ' [scan masuk/pulang di luar jendela valid]' : ''
        ));
    }

    private function save(AttendanceRecordRepository $repo, array $r, string $source): void
    {
        $repo->saveCalculatedResult($r, $source);
    }
}
