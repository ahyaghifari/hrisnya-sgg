<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Insentif RSU — dipakai kalau setting `insentif_metode` company = "import" (lihat
 * IncentiveCalculator, yang skip total buat metode ini). Beda dari EmployeeImport: tidak
 * membuat data baru, cuma MENEMPEL PayrollItem ke Payroll yang SUDAH digenerate untuk periode
 * ini — baris yang NIP-nya tidak match user, atau user itu belum punya Payroll di periode ini
 * (belum digenerate/typo NIP), di-skip & dilaporkan sebagai error, tidak menghentikan baris lain.
 *
 * Idempotent per (payroll, NIP): upload ulang untuk NIP yang sama MENGGANTI nominal
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

                    $existing = PayrollItem::query()
                        ->where('payroll_id', $payroll->id)
                        ->where('source_type', PayrollComponentEnum::INSENTIF->value)
                        ->first();

                    $delta = $amount - (float) ($existing?->amount ?? 0);

                    PayrollItem::updateOrCreate(
                        ['payroll_id' => $payroll->id, 'source_type' => PayrollComponentEnum::INSENTIF->value],
                        [
                            'name' => PayrollComponentEnum::INSENTIF->label(),
                            'type' => PayrollComponentEnum::INSENTIF->type()->value,
                            'quantity' => 1,
                            'amount' => $amount,
                            'note' => 'Import Excel (RSU)',
                        ]
                    );

                    $payroll->increment('gross_salary', $delta);
                    $payroll->increment('net_salary', $delta);
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
