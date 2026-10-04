<?php

namespace App\Imports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import roster koperasi, format sama dengan KoperasiExport (nip, potongan_per_bulan, status).
 * Upsert per NIP, dibatasi ke company terpilih. Aturan eligibility sama dengan
 * KoperasiPage::saveAdd(). Kolom status kosong = tidak mengubah status anggota yang ada
 * (anggota baru default Aktif).
 */
class KoperasiImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    public int $created = 0;
    public int $updated = 0;

    public function __construct(protected ?string $companyId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $n = $i + 2;
            $nip = trim((string) ($row['nip'] ?? ''));
            if ($nip === '') {
                continue;
            }

            $amount = $row['potongan_per_bulan'] ?? null;
            if (! is_numeric($amount) || $amount <= 0) {
                $this->errors[] = "Baris {$n} ({$nip}): potongan harus angka > 0.";
                continue;
            }

            $user = User::with('profile')->whereNip($nip)->whereCompany($this->companyId)->first();
            if (! $user) {
                $this->errors[] = "Baris {$n} ({$nip}): NIP tidak ditemukan di perusahaan ini.";
                continue;
            }

            $active = match (strtolower(trim((string) ($row['status'] ?? '')))) {
                'aktif' => true,
                'nonaktif' => false,
                default => null,
            };

            $member = UserKoperasi::where('user_id', $user->id)->first();

            // Instance ->update()/->create() wajib: 'amount' cast PayrollEncrypted (lihat KoperasiPage::saveEdit).
            if ($member) {
                $member->update(['amount' => $amount] + ($active === null ? [] : ['is_active' => $active]));
                $this->updated++;
                continue;
            }

            if (! $user->is_active
                || ! PayrollComponentRecipient::isEligible($user->company_id, PayrollComponentEnum::KOPERASI->value, $user->profile?->employee_type_id)) {
                $this->errors[] = "Baris {$n} ({$nip}): karyawan nonaktif / tipenya belum terdaftar sebagai penerima Koperasi.";
                continue;
            }

            UserKoperasi::create(['user_id' => $user->id, 'amount' => $amount, 'is_active' => $active ?? true]);
            $this->created++;
        }
    }
}
