<?php

namespace App\Console\Commands;

use App\Enum\AttendanceStatus;
use App\Models\User;
use App\Repositories\Attendance\AttendanceRecordRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * One-off: import "Laporan Detail Presensi" pihak ketiga (blok per karyawan: Nama Karyawan/ID-NIK
 * lalu tabel tanggal, angka jam sudah dihitung di sumbernya) ke attendance_records. Beda total
 * dari AttendanceImporter (Filament)/AttendanceImportService yang baca flat scan log per baris.
 *
 * ID/NIK di file TIDAK bisa dipercaya sebagai nip (pernah ketemu angka yang sama sekali beda dari
 * nip asli user-nya di database). Resolusi user cuma lewat nip yang cocok (persis atau kurang satu
 * digit nol di depan) atau nama yang cocok persis (sebelum koma, tanpa gelar) dan unik. Gagal
 * dua-duanya → baris dilewati dan dilaporkan di akhir, bukan ditebak.
 */
class ImportAttendanceReportDetail extends Command
{
    protected $signature = 'attendance:import-report-detail
        {path : Path ke file .xlsx}
        {--dry-run : Cuma tampilkan hasil parsing & resolusi user, tidak menulis apa pun}';

    protected $description = 'Import laporan detail presensi per-karyawan (format pivot, bukan flat scan log) ke attendance_records';

    private const KETERANGAN_STATUS_MAP = [
        'Alpha' => AttendanceStatus::ABSENT->value,
        'Cuti'  => AttendanceStatus::LEAVE->value,
        'Sakit' => AttendanceStatus::SICK->value,
        'Izin'  => AttendanceStatus::PERMIT->value,
    ];

    public function handle(AttendanceRecordRepository $repository): int
    {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = array_values(IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true));
        $total = count($rows);

        $records = [];
        $conflicts = [];
        $unresolved = [];
        $i = 0;

        while ($i < $total) {
            if (($rows[$i]['A'] ?? null) !== 'Nama Karyawan') {
                $i++;
                continue;
            }

            $reportName = trim((string) ($rows[$i]['B'] ?? ''));
            $rawNik = trim((string) ($rows[$i + 1]['B'] ?? ''));
            $user = $this->resolveUser($rawNik, $reportName);

            $i += 2;
            while ($i < $total && (($rows[$i]['A'] ?? null) !== 'Tanggal' || ($rows[$i]['B'] ?? null) !== 'Jam Kerja')) {
                $i++;
            }
            $i += 2; // lewati baris header ("Tanggal"/"Jam Kerja") + sub-header ("Masuk"/"Pulang")

            while ($i < $total && ($rows[$i]['A'] ?? null) !== null && ($rows[$i]['A'] ?? null) !== 'TOTAL') {
                $dayRow = $rows[$i];
                $i++;

                $date = $this->parseDate($dayRow['A'] ?? null);
                if (! $date) {
                    continue;
                }

                $keterangan = trim((string) ($dayRow['O'] ?? '-'));
                if ($keterangan === 'Libur') {
                    continue; // bukan hari kerja, tidak perlu record
                }

                if (! $user) {
                    $unresolved[$reportName] = $rawNik;

                    continue;
                }

                $lateMinutes = $this->hmToMinutes($dayRow['G'] ?? null);
                $overtimeMinutes = $this->hmToMinutes($dayRow['M'] ?? null);

                $data = [
                    'nip' => $user->nip,
                    'user_id' => $user->id,
                    'date' => $date,
                    'check_in_time' => $this->parseTime($dayRow['E'] ?? null),
                    'check_out_time' => $this->parseTime($dayRow['F'] ?? null),
                    'late_minutes' => $lateMinutes,
                    'early_leave_minutes' => $this->hmToMinutes($dayRow['H'] ?? null),
                    'working_minutes' => $this->hmToMinutes($dayRow['I'] ?? null),
                    'overtime_minutes' => $overtimeMinutes,
                    'status' => $this->resolveStatus($keterangan, $lateMinutes, $overtimeMinutes),
                    'source' => 'import',
                ];

                $key = $user->nip . '|' . $date;

                if (! isset($records[$key])) {
                    $records[$key] = $data;
                } elseif ($data['check_in_time'] && $records[$key]['check_in_time'] && $data['check_in_time'] !== $records[$key]['check_in_time']) {
                    // dua blok karyawan di file sama-sama punya data asli tapi beda — kemungkinan
                    // duplikat enrollment mesin fingerprint, jangan tebak mana yang benar.
                    $conflicts[] = "{$user->name} ({$user->nip}) {$date}: {$records[$key]['check_in_time']} vs {$data['check_in_time']}";
                } elseif ($data['check_in_time'] && ! $records[$key]['check_in_time']) {
                    // blok sebelumnya kosong ("-" semua) sementara blok ini ada scan asli — pakai yang ada datanya.
                    $records[$key] = $data;
                }
                // else: blok baru kosong sementara yang tersimpan sudah ada data asli -> biarkan, jangan ditimpa data kosong.
            }

            while ($i < $total && ($rows[$i]['A'] ?? null) !== 'Nama Karyawan') {
                $i++;
            }
        }

        foreach ($records as $data) {
            if ($dryRun) {
                $this->line("[dry-run] {$data['nip']} {$data['date']}: in={$data['check_in_time']} out={$data['check_out_time']} status={$data['status']}");
            } else {
                $repository->updateOrCreate($data);
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . count($records) . ' baris presensi diproses.');

        if ($conflicts) {
            $this->warn(count($conflicts) . ' konflik data (dua blok beda isi untuk orang & tanggal sama, dilewati keduanya kecuali yang pertama):');
            foreach ($conflicts as $conflict) {
                $this->line("  - {$conflict}");
            }
        }

        if ($unresolved) {
            $this->warn(count($unresolved) . ' karyawan tidak bisa dicocokkan ke user (dilewati):');
            foreach ($unresolved as $name => $nik) {
                $this->line("  - {$name} (ID/NIK di file: {$nik})");
            }
        }

        return self::SUCCESS;
    }

    private function resolveUser(string $rawNik, string $reportName): ?User
    {
        $normalizedTarget = $this->normalizeName($reportName);
        $nikCandidate = str_contains($rawNik, '/') ? substr($rawNik, strrpos($rawNik, '/') + 1) : $rawNik;

        if ($nikCandidate !== '') {
            // Match persis ke nip = identifier unik, aman dipercaya langsung (nama di file suka
            // beda format/ejaan dari database walau orangnya benar).
            if ($byNip = User::where('nip', $nikCandidate)->first()) {
                return $byNip;
            }

            // Match "kurang satu nol di depan" cuma heuristik longgar — pernah kebetulan cocok
            // ke nip orang LAIN (beda orang), jadi wajib diverifikasi lewat nama juga.
            $padded = str_pad($nikCandidate, strlen($nikCandidate) + 1, '0', STR_PAD_LEFT);
            $byPaddedNip = User::where('nip', $padded)->first();

            if ($byPaddedNip && $this->normalizeName($byPaddedNip->name) === $normalizedTarget) {
                return $byPaddedNip;
            }
        }

        $matches = User::query()
            ->get(['id', 'name', 'nip'])
            ->filter(fn (User $u) => $this->normalizeName($u->name) === $normalizedTarget);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalizeName(string $name): string
    {
        $base = trim(explode(',', $name)[0]);

        return strtoupper(trim(preg_replace('/[^A-Za-z]+/', ' ', $base)));
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value || ! str_contains($value, ',')) {
            return null;
        }

        try {
            return Carbon::parse(trim(explode(',', $value)[1]))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function hmToMinutes(?string $value): ?int
    {
        $value = trim((string) $value);
        if (! str_contains($value, ':')) {
            return null;
        }

        [$h, $m] = array_map('intval', explode(':', $value));
        $minutes = $h * 60 + $m;

        return $minutes > 0 ? $minutes : null;
    }

    private function resolveStatus(string $keterangan, ?int $lateMinutes, ?int $overtimeMinutes): string
    {
        if (isset(self::KETERANGAN_STATUS_MAP[$keterangan])) {
            return self::KETERANGAN_STATUS_MAP[$keterangan];
        }

        if ($keterangan !== '-' && $keterangan !== '') {
            return strtolower($keterangan); // keterangan tak dikenal, simpan apa adanya
        }

        if ($lateMinutes) {
            return AttendanceStatus::LATE->value;
        }

        if ($overtimeMinutes) {
            return AttendanceStatus::OVERTIME->value;
        }

        return AttendanceStatus::PRESENT->value;
    }
}
