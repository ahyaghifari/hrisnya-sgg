<?php

namespace App\Imports\Employees;

use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Master\Religions;
use App\Models\Role;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeImport implements ToCollection, WithHeadingRow
{
    private array $errors       = [];
    private int   $successCount = 0;

    public function __construct(
        protected ?string $companyId = null,
        protected ?string $importedBy = null,
    ) {}

    public function collection(Collection $rows): void
    {
        // Cache master-data look-ups (name → id) scoped to company
        $cid = $this->companyId;

        $religions    = Religions::pluck('id', 'name'); // agama: master data global, tidak per company
        $jobPositions = JobPositions::when($cid, fn ($q) => $q->where('company_id', $cid))->pluck('id', 'name');
        $pangkats     = Pangkats::when($cid, fn ($q) => $q->where('company_id', $cid))->pluck('id', 'name');
        $kelasData    = Kelas::when($cid, fn ($q) => $q->where('company_id', $cid))->pluck('id', 'name');
        $employeeTypes = EmployeeTypes::when($cid, fn ($q) => $q->where('company_id', $cid))->pluck('id', 'name');

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // account for header in row 1

            // Skip completely empty rows
            if (empty(trim((string) ($row['nama'] ?? ''))) && empty(trim((string) ($row['email'] ?? '')))) {
                continue;
            }

            try {
                DB::transaction(function () use (
                    $row, $cid,
                    $religions, $jobPositions,
                    $pangkats, $kelasData, $employeeTypes
                ) {
                    $name  = trim((string) ($row['nama'] ?? ''));
                    $nip   = $row['nip'] ?: null;
                    $email = strtolower(trim((string) ($row['email'] ?? '')));

                    // ── Duplicate handling: same email OR nip → restore & update ──
                    $user = User::withTrashed()
                        ->when($cid, fn ($q) => $q->where('company_id', $cid))
                        ->where(function ($q) use ($email, $nip) {
                            $q->where('email', $email);
                            if ($nip !== null) {
                                $q->orWhere('nip', $nip);
                            }
                        })
                        ->first();

                    $userData = [
                        'name'       => $name,
                        'email'      => $email,
                        'nip'        => $nip,
                        'company_id' => $cid,
                    ];

                    if ($filled = ($row['password'] ?? '')) {
                        $userData['password'] = Hash::make($filled);
                    }

                    if ($user) {
                        // Restore if soft-deleted, then overwrite data
                        $user->restore();
                        $user->update($userData);
                    } else {
                        $userData['password'] = Hash::make($row['password'] ?? 'Password123!');
                        $user = User::create($userData);
                    }

                    // ── Role ─────────────────────────────────────────────────
                    $roleName = trim((string) ($row['role'] ?? ''));
                    if ($roleName !== '') {
                        $role = Role::where('name', $roleName)
                            ->when($cid, fn ($q) => $q->where('company_id', $cid))
                            ->first();
                        if ($role) {
                            $user->syncRoles([$role]);
                        }
                    }

                    // ── Gender mapping ────────────────────────────────────────
                    $genderRaw = trim((string) ($row['jenis_kelamin'] ?? ''));
                    $gender    = match ($genderRaw) {
                        'Laki-laki' => 'laki_laki',
                        'Perempuan' => 'perempuan',
                        default     => $genderRaw ?: null,
                    };

                    $joiningDate   = $this->parseDate($row['tanggal_bergabung'] ?? null);
                    $jobPositionId = $jobPositions[$row['jabatan'] ?? ''] ?? null;

                    // ── UserProfile: update if exists, create otherwise ───────
                    UserProfiles::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'nickname'             => $row['panggilan'] ?: null,
                            'gender'               => $gender,
                            'place_of_birth'       => $row['tempat_lahir'] ?: null,
                            'date_of_birth'        => $this->parseDate($row['tanggal_lahir'] ?? null),
                            'no_nik'               => $row['no_nik'] ?: null,
                            'no_kk'                => $row['no_kk'] ?: null,
                            'no_bpjs_kes'          => $row['no_bpjs_kesehatan'] ?: null,
                            'no_bpjs_ket'          => $row['no_bpjs_ketenagakerjaan'] ?: null,
                            'bank_account_number'  => $row['no_rekening'] ?: null,
                            'bank_account_name'    => $row['nama_rekening'] ?: null,
                            'npwp'                 => $row['npwp'] ?: null,
                            'country'              => $row['negara'] ?: null,
                            'province'             => $row['provinsi'] ?: null,
                            'city'                 => $row['kota'] ?: null,
                            'district'             => $row['kecamatan'] ?: null,
                            'village'              => $row['kelurahan'] ?: null,
                            'address'              => $row['alamat'] ?: null,
                            'postal_code'          => $row['kode_pos'] ?: null,
                            'rt'                   => $row['rt'] ?: null,
                            'rw'                   => $row['rw'] ?: null,
                            'phone'                => $row['no_telepon'] ?: null,
                            'whatsapp'             => $row['no_whatsapp'] ?: null,
                            'religion_id'          => $religions[$row['agama'] ?? ''] ?? null,
                            'bank_name'            => $row['bank'] ?: null,
                            'job_position_id'      => $jobPositionId,
                            'pangkat_id'           => $pangkats[$row['pangkat'] ?? ''] ?? null,
                            'kelas_id'             => $kelasData[$row['kelas'] ?? ''] ?? null,
                            'employee_type_id'     => $employeeTypes[$row['tipe_karyawan'] ?? ''] ?? null,
                            'joining_date'         => $joiningDate,
                        ]
                    );

                    // ── UserJobPositions ──────────────────────────────────────
                    if ($jobPositionId !== null) {
                        UserJobPositions::updateOrCreate(
                            [
                                'user_id'         => $user->id,
                                'job_position_id' => $jobPositionId,
                            ],
                            [
                                'assigned_by' => $this->importedBy,
                                'start_date'  => $joiningDate ?? now()->toDateString(),
                                'status'      => 'active',
                            ]
                        );
                    }
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        // Excel serial date (numeric)
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
