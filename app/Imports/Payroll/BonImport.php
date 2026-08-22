<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bon RSU — menulis LANGSUNG ke payroll_items komponen Bon (lewat
 * PayrollService::applyManualRevision(), source_type=bon, edited_manually=true) untuk NIP yang
 * cocok dengan Payroll yang SUDAH digenerate untuk periode ini (2026-08-22 - sebelumnya lewat
 * payroll_manual_lines seperti BonDetailImport, diubah karena mode Simple cuma 1 nilai/user,
 * tidak butuh tabel perantara). Baris yang NIP-nya tidak match user, atau user itu belum punya
 * Payroll di periode ini, di-skip & dilaporkan sebagai error.
 *
 * edited_manually=true otomatis membuat baris ini SELAMAT dari Generate/Regenerate berikutnya
 * (Calculator Bon di-skip untuk user ini, lihat PayrollContext::protectedSourceTypes) -
 * PayrollsPage::confirmImport() tetap memanggil regenerateSinglePayroll() tiap user yang
 * berhasil (getSuccessUserIds()) supaya komponen LAIN di payroll itu (mis. perbaikan Calculator
 * terbaru) ikut ter-refresh, bukan supaya nominal Bon-nya "masuk" - itu sudah masuk duluan di sini.
 *
 * Idempotent per (periode, user): upload ulang untuk NIP yang sama MENGGANTI nominal
 * sebelumnya (bukan menambah baris baru) — applyManualRevision() update-in-place kalau baris
 * source_type=bon sudah ada.
 *
 * HANYA buat setting `bon_metode` = 'simple' (default) — company dengan mode 'detail' pakai
 * BonDetailImport (sibling class, TETAP lewat payroll_manual_lines karena bisa banyak baris
 * rincian bernama per user, format kolom nip/nama_potongan/nominal) atau tambah rincian
 * satu-satu lewat halaman detail payroll (PayrollDetailPage). Gate utamanya di
 * PayrollsPage::makeImport() (pilih class sesuai bon_metode) — validateRow() di sini jadi lapis
 * kedua kalau class ini dipanggil langsung (bukan lewat UI), bukan cuma andalkan pemilihan class
 * di pemanggil.
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
    /** @var array<int, string> user_id yang baris-nya berhasil diimport - dipakai
     * PayrollsPage::confirmImport() buat regenerate payroll otomatis (mode Bon "Simple"). */
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

            $payroll = Payroll::query()
                ->where('user_id', $user->id)
                ->where('payroll_period_id', $this->payrollPeriodId)
                ->first();

            app(PayrollService::class)->applyManualRevision(
                $payroll,
                PayrollComponentEnum::BON,
                $amount,
                'Import Excel',
            );

            $this->successCount++;
            $this->successUserIds[] = $user->id;
        }
    }

    private function validateRow(?User $user, float $amount, string $nip, ?string $companyId): ?string
    {
        if (Setting::resolve('bon_metode', $companyId, default: 'simple') === 'detail') {
            return "NIP {$nip}: company ini pakai mode Bon \"Detail\" - gunakan Import Bon (otomatis pakai format rincian nip/nama_potongan/nominal untuk mode ini) atau tambah rincian lewat halaman detail payroll.";
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
