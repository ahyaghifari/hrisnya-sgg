<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bon mode "Detail" — sibling dari BonImport (bukan extends, validasi & guard-nya KEBALIKAN),
 * satu baris Excel = satu rincian potongan bernama (`nama_potongan` jadi `payroll_manual_lines.label`,
 * dibaca AbstractManualLineCalculator sebagai nama baris di slip, bukan "Bon" generik). Beda dari
 * BonImport yang upsert per (periode, user) — di sini kunci upsert ikut `label`, supaya:
 * - Upload ulang NIP + nama potongan yang SAMA -> nominal ditimpa (bukan dobel baris).
 * - Nama potongan BEDA untuk NIP yang sama -> tetap baris terpisah (rincian satu-satu).
 *
 * Interface publik SAMA persis BonImport (getErrors/getSuccessCount/getSuccessUserIds/
 * getPreview) supaya PayrollsPage::makeImport() bisa pilih salah satu tanpa ubah kode pemanggil.
 */
class BonDetailImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $successCount = 0;
    private array $previewRows = [];
    /** @var array<int, string> user_id yang baris-nya berhasil diimport - dipakai
     * PayrollsPage::confirmImport() buat regenerate payroll otomatis. */
    private array $successUserIds = [];

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
            $namaPotongan = trim((string) ($row['nama_potongan'] ?? ''));
            $amount = (float) ($row['nominal'] ?? $row['jumlah'] ?? 0);

            if ($nip === '') {
                continue; // baris kosong, bukan error
            }

            $user = User::whereNip($nip)->first();
            $error = $this->validateRow($user, $amount, $namaPotongan, $nip, $companyId);

            if ($this->preview) {
                $this->previewRows[] = [
                    'row' => $rowNum,
                    'nip' => $nip,
                    'nama_sistem' => $user?->name,
                    'nama_potongan' => $namaPotongan,
                    'nominal' => $amount,
                    'error' => $error,
                ];

                continue;
            }

            if ($error) {
                $this->errors[] = "Baris {$rowNum}: {$error}";

                continue;
            }

            DB::transaction(function () use ($user, $amount, $namaPotongan): void {
                PayrollManualLine::updateOrCreate(
                    [
                        'payroll_period_id' => $this->payrollPeriodId,
                        'user_id' => $user->id,
                        'component' => PayrollComponentEnum::BON->value,
                        'label' => $namaPotongan,
                    ],
                    [
                        'amount' => $amount,
                        'note' => 'Import Excel',
                    ]
                );
            });

            $this->successCount++;
            $this->successUserIds[] = $user->id;
        }
    }

    private function validateRow(?User $user, float $amount, string $namaPotongan, string $nip, ?string $companyId): ?string
    {
        if (Setting::resolve('bon_metode', $companyId, default: 'simple') !== 'detail') {
            return "NIP {$nip}: company ini pakai mode Bon \"Simple\" - import rincian tidak berlaku, gunakan Import Bon biasa (nip/nama/nominal).";
        }

        if (! $user) {
            return "NIP {$nip} tidak ditemukan.";
        }

        if ($namaPotongan === '') {
            return "NIP {$nip}: kolom nama_potongan wajib diisi (jadi nama baris di slip, bukan \"Bon\" generik).";
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

        if (! PayrollComponentRecipient::isEligible($companyId, PayrollComponentEnum::BON->value, $user->profile?->employee_type_id)) {
            return "NIP {$nip}: tipe karyawan ini belum terdaftar sebagai penerima Bon di halaman Penerima Komponen Gaji — nominal tidak akan muncul di payroll meski diimport.";
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

    /** @return array<int, string> */
    public function getSuccessUserIds(): array
    {
        return $this->successUserIds;
    }

    public function getPreview(): array
    {
        return $this->previewRows;
    }
}
