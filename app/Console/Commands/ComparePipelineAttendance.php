<?php

namespace App\Console\Commands;

use App\Enum\AttendanceStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Services\Attendance\AccessLogScheduleReader;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

/**
 * Pembanding BACA-SAJA: hitung ulang access_logs lewat pipeline sekarang (reader -> calculator),
 * lalu cetak selisihnya dengan baris yang SUDAH tersimpan. Tidak menulis apa pun. Dipakai
 * sebelum/ sesudah mengubah reader/calculator buat melihat siapa yang berubah hasilnya.
 *
 * Dilewati: baris koreksi manual/impor (is_auto_calculated=false) dan status Cuti/Sakit/Izin -
 * keduanya memang tidak ditimpa mesin. Selisih yang wajar muncul di hari yang baris lamanya
 * dihasilkan versi lama yang salah, atau yang diedit tanpa menandai is_auto_calculated.
 */
class ComparePipelineAttendance extends Command
{
    protected $signature = 'attendance:compare-pipeline
        {--from= : Tanggal awal (Y-m-d), default kemarin}
        {--to= : Tanggal akhir (Y-m-d), default hari ini}
        {--nip= : Batasi 1 NIP}
        {--out= : Simpan semua selisih ke file CSV ini}
        {--show=40 : Jumlah baris selisih yang dicetak}';

    protected $description = 'Bandingkan hasil hitung ulang access_logs dengan attendance_records yang tersimpan (tanpa menulis)';

    private const FIELDS = ['check_in_time' => 'check_in', 'check_out_time' => 'check_out', 'late_minutes' => 'late_minutes', 'working_minutes' => 'working_minutes', 'status' => 'status'];

    public function handle(
        AccessLogScheduleReader $reader,
        FixedScheduleCalculator $fixed,
        FlexibleScheduleCalculator $flexible,
        ShiftScheduleCalculator $shift,
    ): int {
        $from = $this->option('from') ?: now()->subDay()->toDateString();
        $to = $this->option('to') ?: now()->toDateString();
        $nip = $this->option('nip') ?: null;
        $calculators = ['fixed' => $fixed, 'flexible' => $flexible, 'shift' => $shift];
        $locked = [AttendanceStatus::LEAVE->value, AttendanceStatus::SICK->value, AttendanceStatus::PERMIT->value];

        $diffs = [];
        $compared = 0;
        $skippedManual = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            // Kemarin & besok ikut dibaca: scan pagi bisa menutup shift malam kemarin, scan besok
            // bisa jadi pulang shift malam hari ini. Hasil dibatasi ke $date.
            $result = $reader->readAndGroup($day->copy()->subDay()->toDateString(), $day->copy()->addDay()->toDateString(), $nip);

            $stored = AttendanceRecords::query()->where('date', $date)->get()->keyBy('nip');

            foreach ($calculators as $type => $calculator) {
                $entries = array_filter($result['buckets'][$type], fn ($e) => $e['date'] === $date);

                foreach ($calculator->calculate($entries) as $r) {
                    $record = $stored->get($r['user']->nip);

                    if ($record && (! $record->is_auto_calculated || in_array($record->status, $locked, true))) {
                        $skippedManual++;

                        continue;
                    }

                    $compared++;
                    $this->diff($diffs, $date, $r['user']->nip, "normal:{$type}", $record, $r);
                }
            }

            foreach (array_filter($result['buckets']['extra_shift'], fn ($e) => $e['date'] === $date) as $entry) {
                $r = $shift->calculate([$entry])[0];
                $record = ExtraShiftAttendanceRecord::where('extra_shift_assignment_id', $r['extra_shift_assignment_id'])->first();

                $compared++;
                $this->diff($diffs, $date, $r['user']->nip, 'extra', $record, $r);
            }
        }

        $this->info("Dibandingkan {$compared} hasil ({$from} s/d {$to}), dilewati {$skippedManual} (koreksi manual/cuti/sakit/izin). Selisih: " . count($diffs) . ' baris.');

        if ($diffs !== []) {
            $this->table(['tanggal', 'nip', 'jenis', 'kolom', 'tersimpan', 'hasil baru'], array_slice($diffs, 0, (int) $this->option('show')));
        }

        if ($out = $this->option('out')) {
            $handle = fopen($out, 'w');
            fputcsv($handle, ['tanggal', 'nip', 'jenis', 'kolom', 'tersimpan', 'hasil_baru']);
            foreach ($diffs as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            $this->info("Selisih lengkap ditulis ke {$out}");
        }

        return self::SUCCESS;
    }

    /** @param  array<int, array<int, ?string>>  &$diffs */
    private function diff(array &$diffs, string $date, string $nip, string $kind, ?object $record, array $r): void
    {
        if (! $record) {
            $diffs[] = [$date, $nip, $kind, '(baris)', '(tidak ada)', ($r['check_in'] ?? '-') . ' / ' . ($r['check_out'] ?? '-')];

            return;
        }

        foreach (self::FIELDS as $column => $key) {
            $old = $record->{$column};
            $new = $r[$key] ?? null;

            if ((string) $old !== (string) $new) {
                $diffs[] = [$date, $nip, $kind, $column, $old === null ? '-' : (string) $old, $new === null ? '-' : (string) $new];
            }
        }
    }
}
