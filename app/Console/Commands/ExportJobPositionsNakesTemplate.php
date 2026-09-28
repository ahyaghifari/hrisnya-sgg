<?php

namespace App\Console\Commands;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export template XLSX (id, nama, nakes) untuk 1 company - kolom `nakes` SENGAJA dikosongkan
 * (bukan diisi status is_nakes saat ini), dipakai user isi manual lalu di-import balik lewat
 * jabatan:import-nakes. Pasangan command ini, tidak berdiri sendiri. Pakai PhpSpreadsheet
 * langsung (dependency maatwebsite/excel yang sudah ada) - bukan Laravel Excel
 * Export/Import class, supaya --out bebas nunjuk path filesystem apa saja (bukan cuma disk
 * storage/app default Laravel Excel).
 */
class ExportJobPositionsNakesTemplate extends Command
{
    protected $signature = 'jabatan:export-nakes {company : ID atau kode Company} {--out=jabatan-nakes.xlsx : Path file XLSX output}';

    protected $description = 'Export daftar jabatan 1 company jadi template XLSX (id, nama, nakes) untuk diisi manual dan di-import balik';

    public function handle(): int
    {
        $company = Companies::where('id', $this->argument('company'))
            ->orWhere('code', $this->argument('company'))
            ->first();

        if (! $company) {
            $this->error('Company tidak ditemukan (cari berdasarkan ID atau kode).');

            return self::FAILURE;
        }

        $jobPositions = JobPositions::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['id', 'nama', 'nakes'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $row = 2;
        foreach ($jobPositions as $jp) {
            $sheet->fromArray([$jp->id, $jp->name, ''], null, "A{$row}");
            $row++;
        }

        foreach (['A' => 28, 'B' => 40, 'C' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $path = $this->option('out');
        (new Xlsx($spreadsheet))->save($path);

        $this->info("Selesai: {$jobPositions->count()} jabatan ({$company->name}) ditulis ke {$path}.");
        $this->line('Isi kolom "nakes" (bebas, mis. "Ya"/"1") untuk jabatan yang mau ditandai nakes, biarkan kosong untuk yang lain, lalu jalankan jabatan:import-nakes.');

        return self::SUCCESS;
    }
}
