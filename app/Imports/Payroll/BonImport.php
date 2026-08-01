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
 * Bon RSU — menulis baris manual (component=bon) yang NIP-nya cocok dengan Payroll yang SUDAH
 * digenerate untuk periode ini. Baris yang NIP-nya tidak match user, atau user itu belum punya
 * Payroll di periode ini, di-skip & dilaporkan sebagai error.
 *
 * TIDAK langsung mengubah payroll_items/total Payroll — baru ke-apply setelah Generate/
 * Regenerate dijalankan ulang untuk periode ini.
 *
 * Idempotent per (periode, user): upload ulang untuk NIP yang sama MENGGANTI nominal
 * sebelumnya (bukan menambah baris baru) — supaya revisi file tidak dobel-hitung.
 *
 * HANYA buat setting `bon_metode` = 'simple' (default) — company dengan mode 'detail' harus
 * tambah rincian Bon satu-satu lewat halaman detail payroll (PayrollDetailPage), supaya tiap
 * baris punya keterangan sendiri dan tidak ada risiko import menimpa/menduplikasi rincian yang
 * sudah diinput manual. Gate utamanya di PayrollsPage::importGuardPasses() (tombol "Import Bon"
 * disembunyikan sama sekali kalau mode Detail) — validateRow() di sini jadi lapis kedua kalau
 * class ini dipanggil langsung (bukan lewat UI), bukan cuma sembunyikan tombol.
 *
 * Mode `preview: true` cuma memvalidasi tiap baris (termasuk NIP tidak ditemukan) tanpa
 * menulis apa pun ke database — dipakai buat tampilkan pratinjau sebelum user klik "Simpan"
 * (lihat PayrollsPage::previewImport()/confirmImport()).
 */
class BonImport implements ToCollection, WithHeadingRow
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
                        'component' => PayrollComponentEnum::BON->value,
                    ],
                    [
                        'amount' => $amount,
                        'note' => 'Import Excel',
                    ]
                );
            });

            $this->successCount++;
        }
    }

    private function validateRow(?User $user, float $amount, string $nip, ?string $companyId): ?string
    {
        if (Setting::resolve('bon_metode', $companyId, default: 'simple') === 'detail') {
            return "NIP {$nip}: company ini pakai mode Bon \"Detail\" - import Excel tidak berlaku, tambah rincian Bon lewat halaman detail payroll.";
        }

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

    public function getPreview(): array
    {
        return $this->previewRows;
    }
}
