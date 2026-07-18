<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollManualLine;
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
 */
class IncentiveImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $successCount = 0;

    public function __construct(
        protected string $payrollPeriodId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $nip = trim((string) ($row['nip'] ?? ''));
            $amount = (float) ($row['nominal'] ?? $row['jumlah'] ?? 0);

            if ($nip === '') {
                continue; // baris kosong, bukan error
            }

            try {
                DB::transaction(function () use ($nip, $amount, $rowNum): void {
                    $user = User::where('nip', $nip)->first();

                    if (! $user) {
                        throw new \RuntimeException("NIP {$nip} tidak ditemukan.");
                    }

                    $payroll = Payroll::query()
                        ->where('user_id', $user->id)
                        ->where('payroll_period_id', $this->payrollPeriodId)
                        ->first();

                    if (! $payroll) {
                        throw new \RuntimeException("NIP {$nip}: belum ada payroll di periode ini — generate payroll dulu sebelum import.");
                    }

                    if ($payroll->is_paid) {
                        throw new \RuntimeException("NIP {$nip}: payroll sudah dibayar (is_paid), tidak bisa diubah.");
                    }

                    if ($amount <= 0) {
                        throw new \RuntimeException("NIP {$nip}: nominal harus lebih dari 0.");
                    }

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
            } catch (\Throwable $e) {
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}
