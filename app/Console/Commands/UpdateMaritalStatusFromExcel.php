<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Sementara: hapus setelah dipakai.
class UpdateMaritalStatusFromExcel extends Command
{
    protected $signature = 'marital:update-from-excel {file : Path file xlsx} {--apply : Tulis ke database. Tanpa ini hanya dry-run.} {--report=marital-report.xlsx : Path file xlsx laporan}';

    protected $description = 'Update user_profiles.marital_status dari excel (kolom B = NIP, C = NIK, D = status perkawinan) + laporan excel selisih';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $this->info($apply ? 'MODE: APPLY' : 'MODE: DRY-RUN (pakai --apply untuk eksekusi)');

        $rows = IOFactory::load($this->argument('file'))->getActiveSheet()->toArray(null, true, false, true);
        $updated = 0;
        $notFound = $nikDiff = $maritalDiff = [];

        foreach ($rows as $i => $row) {
            $rawNip = $row['B'] ?? '';
            // angka mentah besar jangan jadi notasi ilmiah (6.3E+15)
            $nip = trim(is_numeric($rawNip) ? sprintf('%.0f', $rawNip) : (string) $rawNip);
            // petik di depan = penahan nol depan di excel, bukan bagian NIP
            $nip = ltrim($nip, "'’`");
            $status = trim((string) ($row['D'] ?? ''));

            if ($nip === '' || $status === '') {
                continue;
            }

            $name = trim((string) ($row['A'] ?? ''));

            // ponytail: NIP numerik di excel kehilangan nol depan; coba pad ke 11 digit
            $user = User::whereNip($nip)->first()
                ?? (ctype_digit($nip) ? User::whereNip(str_pad($nip, 11, '0', STR_PAD_LEFT))->first() : null);

            if (!$user) {
                $this->warn("[SKIP] baris {$i}: NIP {$nip} tidak ditemukan.");
                $notFound[] = [$i, $name, $nip];
                continue;
            }

            $this->line("{$user->nip} {$user->name}: {$status}");

            // kolom C = NIK, hanya dicek (tidak diupdate)
            $rawNik = $row['C'] ?? '';
            $nik = ltrim(trim(is_numeric($rawNik) ? sprintf('%.0f', $rawNik) : (string) $rawNik), "'’`");
            $dbNik = trim((string) $user->profile?->no_nik);
            if ($nik !== '' && $dbNik !== $nik) {
                $this->warn("  [NIK BEDA] baris {$i}: excel {$nik}, DB " . ($dbNik ?: '(kosong)'));
                $nikDiff[] = [$i, $user->nip, $user->name, $nik, $dbNik];
            }

            $dbStatus = trim((string) $user->profile?->marital_status);
            if (strcasecmp($dbStatus, $status) !== 0) {
                $maritalDiff[] = [$i, $user->nip, $user->name, $status, $dbStatus];
            }

            if ($apply) {
                $user->profile()->updateOrCreate([], ['marital_status' => $status]);
            }
            $updated++;
        }

        $this->writeReport($notFound, $nikDiff, $maritalDiff);

        $this->info(sprintf(
            'Selesai. Cocok: %d, NIP tidak ditemukan: %d, NIK beda: %d, status beda: %d. Laporan: %s',
            $updated, count($notFound), count($nikDiff), count($maritalDiff), $this->option('report'),
        ));

        return self::SUCCESS;
    }

    private function writeReport(array $notFound, array $nikDiff, array $maritalDiff): void
    {
        $book = new Spreadsheet();
        $sheets = [
            'NIP tidak ditemukan' => [['Baris', 'Nama', 'NIP'], $notFound],
            'NIK beda' => [['Baris', 'NIP', 'Nama', 'NIK Excel', 'NIK DB'], $nikDiff],
            'Status beda' => [['Baris', 'NIP', 'Nama', 'Status Excel', 'Status DB'], $maritalDiff],
        ];

        $n = 0;
        foreach ($sheets as $title => [$head, $data]) {
            $ws = $n++ === 0 ? $book->getActiveSheet() : $book->createSheet();
            $ws->setTitle($title);
            // eksplisit string supaya NIP/NIK panjang tidak jadi notasi ilmiah
            foreach ([$head, ...$data] as $r => $line) {
                foreach ($line as $c => $val) {
                    $ws->setCellValueExplicit([$c + 1, $r + 1], (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }

        (new Xlsx($book))->save($this->option('report'));
    }
}
