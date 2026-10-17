<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Employee\Employee;
use App\Models\Payroll\PayrollJasaUnitDetail;
use App\Models\Payroll\JasaUnitType;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import nominal Jasa Unit per karyawan untuk 1 periode gaji (kolom: nip, kode, nominal) —
 * dipakai JasaUnitInputPage::importJasaUnitDetail(). `kode` dicocokkan ke JasaUnitType.code
 * (wajib+unik per company, lihat JasaUnitTypesPage), bukan nama (lebih stabil, tahan typo nama).
 *
 * Idempotent per (periode, user, jasa): upload ulang untuk kombinasi yang sama MENGGANTI
 * nominal sebelumnya (updateOrCreate), bukan menambah baris baru — sama filosofi BonImport,
 * supaya revisi file tidak dobel-hitung.
 *
 * Baris invalid (NIP/kode tidak ketemu, karyawan tidak eligible, nominal <= 0) di-skip &
 * dilaporkan sebagai error, baris lain tetap lanjut diproses (partial success).
 *
 * Mode `preview: true` cuma memvalidasi tiap baris tanpa menulis apa pun ke database —
 * dipakai buat tampilkan pratinjau di modal import sebelum user klik "Konfirmasi" (lihat
 * JasaUnitInputPage::previewImport()/confirmImport()), sama pola BonImport.
 */
class JasaUnitDetailImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];

    private int $successCount = 0;

    private array $previewRows = [];

    public function __construct(
        protected string $companyId,
        protected string $periodId,
        protected ?string $importedBy,
        protected bool $preview = false,
    ) {}

    public function collection(Collection $rows): void
    {
        $eligibleTypeIds = PayrollComponentRecipient::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::JASA_UNIT->value)
            ->pluck('employee_type_id');

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $nip = trim((string) ($row['nip'] ?? ''));
            $kode = trim((string) ($row['kode'] ?? ''));
            $amount = (float) ($row['nominal'] ?? 0);

            if ($nip === '') {
                continue; // baris kosong, bukan error
            }

            $employee = Employee::where('company_id', $this->companyId)->where('nip', $nip)->first();
            $jasa = JasaUnitType::where('company_id', $this->companyId)->where('code', $kode)->first();

            $error = $this->validateRow($employee, $jasa, $amount, $nip, $kode, $eligibleTypeIds);

            if ($this->preview) {
                $this->previewRows[] = [
                    'row' => $rowNum,
                    'nip' => $nip,
                    'kode' => $kode,
                    'nama' => $employee?->nama_lengkap,
                    'jasa' => $jasa?->name,
                    'nominal' => $amount,
                    'error' => $error,
                ];

                continue;
            }

            if ($error) {
                $this->errors[] = "Baris {$rowNum}: {$error}";

                continue;
            }

            PayrollJasaUnitDetail::updateOrCreate(
                [
                    'payroll_period_id' => $this->periodId,
                    'employee_id' => $employee->id,
                    'jasa_unit_type_id' => $jasa->id,
                ],
                [
                    'amount' => $amount,
                    'created_by' => $this->importedBy,
                ]
            );

            $this->successCount++;
        }
    }

    private function validateRow(?Employee $employee, ?JasaUnitType $jasa, float $amount, string $nip, string $kode, $eligibleTypeIds): ?string
    {
        if (! $employee) {
            return "NIP {$nip} tidak ditemukan.";
        }

        if (! $jasa) {
            return "Kode jasa '{$kode}' tidak ditemukan.";
        }

        if (! $eligibleTypeIds->contains($employee->employee_type_id)) {
            return "NIP {$nip}: tipe karyawan ini belum terdaftar sebagai penerima Jasa Unit di halaman Penerima Komponen Gaji.";
        }

        if ($amount <= 0) {
            return "NIP {$nip}: nominal harus lebih dari 0.";
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
