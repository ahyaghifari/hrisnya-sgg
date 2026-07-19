<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Insentif RSU — dipakai kalau setting `insentif_metode` company = "import" (lihat
 * IncentiveCalculator, yang baca payroll_manual_lines untuk metode ini). Beda dari
 * EmployeeImport: tidak membuat data baru, cuma menulis baris manual (component=insentif) yang
 * NIP-nya cocok dengan Payroll yang SUDAH digenerate untuk periode ini — baris yang NIP-nya
 * tidak match user, atau user itu belum punya Payroll di periode ini (belum digenerate/typo
 * NIP), di-skip & dilaporkan sebagai error, tidak menghentikan baris lain.
 *
 * TIDAK langsung mengubah payroll_items/total Payroll — baru ke-apply setelah Generate/
 * Regenerate dijalankan ulang untuk periode ini (sama pola Koperasi/Bon).
 *
 * Idempotent per (periode, user): upload ulang untuk NIP yang sama MENGGANTI nominal
 * sebelumnya (bukan menambah baris baru) — supaya revisi file tidak dobel-hitung.
 *
 * Mode `preview: true` cuma memvalidasi tiap baris (termasuk NIP tidak ditemukan) tanpa
 * menulis apa pun ke database — dipakai buat tampilkan pratinjau sebelum user klik "Simpan"
 * (lihat PayrollsPage::previewImport()/confirmImport()).
 */
class IncentiveImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $successCount = 0;
    private array $previewRows = [];

    public function __construct(
        protected string $payrollPeriodId,
        protected bool $preview = false,
    ) {}

    public function collection(Collection $rows): void
    {
        $companyId = PayrollPeriod::find($this->payrollPeriodId)?->company_id;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $nip = trim((string) ($row['nip'] ?? ''));
            $namaImport = trim((string) ($row['nama'] ?? ''));
            $amount = (float) ($row['nominal'] ?? $row['jumlah'] ?? 0);

            if ($nip === '') {
                continue; // baris kosong, bukan error
            }

            $user = User::where('nip', $nip)->first();
            $error = $this->validateRow($user, $amount, $nip, $companyId);

            if ($this->preview) {
                $this->previewRows[] = [
                    'row' => $rowNum,
                    'nip' => $nip,
                    'nama_import' => $namaImport,
                    'nama_sistem' => $user?->name,
                    'nominal' => $amount,
                    'error' => $error,
                ];

                continue;
            }

            if ($error) {
                $this->errors[] = "Baris {$rowNum}: {$error}";

                continue;
            }

            DB::transaction(function () use ($user, $amount): void {
                PayrollManualLine::updateOrCreate(
                    [
                        'payroll_period_id' => $this->payrollPeriodId,
                        'user_id' => $user->id,
                        'component' => PayrollComponentEnum::INSENTIF->value,
                    ],
                    [
                        'amount' => $amount,
                        'note' => 'Import Excel (RSU)',
                    ]
                );
            });

            $this->successCount++;
        }
    }

    private function validateRow(?User $user, float $amount, string $nip, ?string $companyId): ?string
    {
        if (! $user) {
            return "NIP {$nip} tidak ditemukan.";
        }

        $payroll = Payroll::query()
            ->where('user_id', $user->id)
            ->where('payroll_period_id', $this->payrollPeriodId)
            ->first();

        if (! $payroll) {
            return "NIP {$nip}: belum ada payroll di periode ini — generate payroll dulu sebelum import.";
        }

        if ($payroll->is_paid) {
            return "NIP {$nip}: payroll sudah dibayar (is_paid), tidak bisa diubah.";
        }

        if ($amount <= 0) {
            return "NIP {$nip}: nominal harus lebih dari 0.";
        }

        if (! PayrollComponentRecipient::isEligible($companyId, PayrollComponentEnum::INSENTIF->value, $user->profile?->employee_type_id)) {
            return "NIP {$nip}: tipe karyawan ini belum terdaftar sebagai penerima Insentif di halaman Penerima Komponen Gaji — nominal tidak akan muncul di payroll meski diimport.";
        }

        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getPreview(): array
    {
        return $this->previewRows;
    }
}
