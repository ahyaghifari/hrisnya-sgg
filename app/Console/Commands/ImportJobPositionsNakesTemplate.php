<?php

namespace App\Console\Commands;

use App\Models\Master\JobPositions;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import balik XLSX hasil jabatan:export-nakes (id, nama, nakes) - HANYA menandai `is_nakes=true`
 * untuk baris yang kolom `nakes`-nya TIDAK kosong (isi bebas, dicek trim() != ''). Baris yang
 * kosong DIBIARKAN apa adanya (tidak dipakai untuk unmark) - kolom `nama` cuma buat referensi
 * visual saat isi manual, tidak dicocokkan/divalidasi, `id` yang jadi kunci match.
 */
class ImportJobPositionsNakesTemplate extends Command
{
    protected $signature = 'jabatan:import-nakes {path : Path file XLSX (kolom: id, nama, nakes)} {--apply : Actually update the database. Without this flag, only prints the planned changes.}';

    protected $description = 'Import XLSX (id, nama, nakes) dan tandai is_nakes=true untuk jabatan yang kolom nakes-nya diisi';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $apply = $this->option('apply');
        $this->info($apply ? 'MODE: APPLY (akan mengubah database)' : 'MODE: DRY-RUN (tidak mengubah apapun, pakai --apply untuk eksekusi)');
        $this->newLine();

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $header = array_map(fn ($h) => trim((string) $h), $rows[0] ?? []);
        $idCol = array_search('id', $header, true);
        $namaCol = array_search('nama', $header, true);
        $nakesCol = array_search('nakes', $header, true);

        if ($idCol === false || $nakesCol === false) {
            $this->error('Header XLSX wajib punya kolom "id" dan "nakes".');

            return self::FAILURE;
        }

        $marked = 0;
        $notFound = 0;
        $skipped = 0;

        foreach (array_slice($rows, 1) as $row) {
            $id = trim((string) ($row[$idCol] ?? ''));
            $nama = trim((string) ($row[$namaCol] ?? '')) ?: '(tanpa nama)';
            $nakes = trim((string) ($row[$nakesCol] ?? ''));

            if ($id === '') {
                continue;
            }

            if ($nakes === '') {
                $skipped++;
                continue;
            }

            $jobPosition = JobPositions::find($id);

            if (! $jobPosition) {
                $this->warn("[SKIP] ID {$id} ({$nama}) tidak ditemukan.");
                $notFound++;
                continue;
            }

            $this->line("{$jobPosition->name} ({$id}): ditandai nakes");

            if ($apply) {
                $jobPosition->update(['is_nakes' => true]);
            }

            $marked++;
        }

        $this->newLine();
        $this->info("Selesai: {$marked} jabatan ditandai nakes, {$notFound} ID tidak ditemukan, {$skipped} baris dilewati (kolom nakes kosong).");

        return self::SUCCESS;
    }
}
