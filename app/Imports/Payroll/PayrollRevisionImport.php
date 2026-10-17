<?php

namespace App\Imports\Payroll;

use App\Exports\Payroll\PayrollSlipRekapExport;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Employee\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Baca ulang file hasil "Export Excel" (PayrollSlipRekapExport) yang sudah direvisi finance.
 * Header dicocokkan PERSIS (case-sensitive, byte-per-byte) ke label kolom export lewat
 * PayrollSlipRekapExport::columnDefinitions() - kalau ada yang tidak cocok (mis. header
 * diketik ulang manual), kolom itu dilewati, bukan ditebak posisinya. Ini yang menjamin
 * "kolom export dan import selalu sinkron" (permintaan user) - satu-satunya sumber kebenaran
 * kolom tetap export, import cuma jadi konsumen definisinya, tidak punya daftar kolom sendiri.
 *
 * HANYA kolom `editable` yang dibaca & dibandingkan (Gaji Pokok, Tunjangan, BPJS, Koperasi,
 * Bon, dst) - kolom hasil hitungan (TOTAL GAJI/Jumlah Terima/dst) dan identitas (NAMA/JABATAN)
 * diabaikan total, cuma kolom "ID" (NIP) yang dipakai buat mencocokkan baris ke Payroll.
 *
 * TIDAK menulis apa pun ke database di sini - collection() cuma menghasilkan daftar
 * perbedaan (getDiffs()) buat dipratinjau dulu. PayrollsPage yang menerapkannya lewat
 * PayrollService::applyManualRevision() per baris, setelah user menekan "Terapkan Perubahan".
 */
class PayrollRevisionImport implements ToCollection
{
    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, array{payroll_id: string, nip: string, nama: string, label: string, component: string, old: float, new: float}> */
    private array $diffs = [];

    public function __construct(protected PayrollPeriod $period) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'File kosong.';

            return;
        }

        $columns = (new PayrollSlipRekapExport(collect(), $this->period->company_id))->columnDefinitions();
        $fileHeadings = $rows->first()->map(fn ($value) => trim((string) $value))->all();

        $idFileIndex = array_search('ID', $fileHeadings, true);
        if ($idFileIndex === false) {
            $this->errors[] = 'Kolom "ID" tidak ditemukan di file - pastikan ini file hasil Export Excel dari sistem, header jangan diubah namanya.';

            return;
        }

        /** @var array<int, array{component: \App\Enum\Payroll\PayrollComponentEnum, label: string}> $editableFileIndex kunci = index kolom di FILE */
        $editableFileIndex = [];
        foreach ($columns as $column) {
            if (! $column['editable']) {
                continue;
            }

            $fileIndex = array_search($column['label'], $fileHeadings, true);
            if ($fileIndex !== false) {
                $editableFileIndex[$fileIndex] = $column;
            }
        }

        if ($editableFileIndex === []) {
            $this->errors[] = 'Tidak ada kolom yang bisa direvisi ditemukan di file ini - header tidak cocok dengan format Export Excel sistem untuk periode/company ini.';

            return;
        }

        foreach ($rows->slice(1) as $index => $row) {
            $rowNum = $index + 2;
            $nip = trim((string) ($row[$idFileIndex] ?? ''));

            if ($nip === '') {
                continue; // baris judul divisi / baris kosong, bukan error
            }

            // Employee tidak punya global scope 'non_karyawan' seperti User, jadi akun eksekutif
            // (PAYROLL_ELIGIBLE_EXECUTIVE_IDS) ikut ketemu tanpa pencarian kedua. Payroll di bawah
            // otomatis kosong kalau barisnya kebetulan milik company lain.
            $employee = Employee::query()->where('nip', $nip)->first();

            if (! $employee) {
                $this->errors[] = "Baris {$rowNum}: NIP {$nip} tidak ditemukan.";

                continue;
            }

            $payroll = Payroll::query()
                ->where('employee_id', $employee->id)
                ->where('payroll_period_id', $this->period->id)
                ->with('items')
                ->first();

            if (! $payroll) {
                $this->errors[] = "Baris {$rowNum}: NIP {$nip} belum punya payroll di periode ini.";

                continue;
            }

            if ($payroll->is_paid) {
                // Payroll yang sudah dibayar dikunci total (konsisten aturan modul Payroll lain)
                // - dilewati diam-diam, bukan error, biar tidak menghalangi revisi baris lain.
                continue;
            }

            foreach ($editableFileIndex as $fileIndex => $column) {
                $rawValue = $row[$fileIndex] ?? null;
                if ($rawValue === null || $rawValue === '') {
                    continue;
                }

                $newAmount = $this->parseNominal($rawValue);
                $oldAmount = (float) ($payroll->items->firstWhere('source_type', $column['component']->value)?->amount ?? 0);

                if (abs($newAmount - $oldAmount) < 0.5) {
                    continue; // tidak berubah
                }

                $this->diffs[] = [
                    'payroll_id' => $payroll->id,
                    'nip' => $nip,
                    'nama' => (string) $employee->nama_lengkap,
                    'label' => $column['label'],
                    'component' => $column['component']->value,
                    'old' => $oldAmount,
                    'new' => $newAmount,
                ];
            }
        }
    }

    /** Terima angka mentah maupun teks berformat "Rp 1.500.000" (kalau finance sempat retype manual). */
    private function parseNominal(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $digits = preg_replace('/[^\d\-]/', '', (string) $value);

        return $digits === '' || $digits === '-' ? 0.0 : (float) $digits;
    }

    /** @return array<int, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<int, array{payroll_id: string, nip: string, nama: string, label: string, component: string, old: float, new: float}> */
    public function getDiffs(): array
    {
        return $this->diffs;
    }
}
