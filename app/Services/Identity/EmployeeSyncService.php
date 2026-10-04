<?php

namespace App\Services\Identity;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeCareer;
use App\Models\Employee\EmployeeDocument;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin users + user_profiles + user_job_positions + user_profile_documents ke employees,
 * employee_identities, employee_addresses, employee_documents, dan employee_careers
 * (roadmap_migrasi_bertahap_identity_hris.md). SATU ARAH, idempotent: tabel lama tetap sumber &
 * diedit seperti biasa, tabel baru cuma salinan. Dipakai bersama oleh observer model (tiap edit)
 * dan command identity:backfill supaya tidak ada dua logika salin. Tidak ada entitas Person
 * terpisah - data pribadi jadi kolom employees.
 *
 * - employees.job_position_id/pangkat_id/kelas_id = kondisi sekarang, dari user_profiles apa adanya.
 * - employee_careers = riwayat dari user_job_positions. Selisih antara dua sumber jabatan ini
 *   (profil vs ledger) SENGAJA tidak ditutupi di sini - dilaporkan identity:verify.
 */
class EmployeeSyncService
{
    /** Akun yang bukan karyawan (2x Super Admin). Direktur & Sekretaris Direktur tetap karyawan
     * karena masih punya payroll (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS). */
    public static function skippedUserIds(): array
    {
        return array_values(array_diff(User::NON_KARYAWAN_IDS, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS));
    }

    public function syncUserId(string $userId): ?Employee
    {
        $user = User::withoutGlobalScopes()->withTrashed()->find($userId);

        return $user ? $this->syncUser($user) : null;
    }

    /** Kegagalan cuma dilaporkan ke log - tabel salinan tidak boleh menggagalkan simpan data asli. */
    public function syncUserIdQuietly(string $userId): void
    {
        try {
            $this->syncUserId($userId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function syncUser(User $user): ?Employee
    {
        if (in_array($user->id, self::skippedUserIds(), true)) {
            return null;
        }

        return DB::transaction(function () use ($user) {
            $profile = UserProfiles::where('user_id', $user->id)->first();

            $employee = Employee::updateOrCreate(['user_id' => $user->id], [
                'nip' => $user->nip,
                'nama_lengkap' => $user->name,
                'nickname' => $profile?->nickname,
                'gender' => $profile?->gender,
                'marital_status' => $profile?->marital_status,
                'place_of_birth' => $profile?->place_of_birth,
                'date_of_birth' => $profile?->date_of_birth,
                'religion_id' => $profile?->religion_id,
                'profile_picture' => $profile?->profile_picture,
                'phone' => $profile?->phone,
                'whatsapp' => $profile?->whatsapp,
                'email_pribadi' => $profile?->email_pribadi,
                'company_id' => $user->company_id,
                'employee_type_id' => $profile?->employee_type_id,
                'job_position_id' => $profile?->job_position_id,
                'pangkat_id' => $profile?->pangkat_id,
                'kelas_id' => $profile?->kelas_id,
                'joining_date' => $profile?->joining_date,
                'tmt_tetap' => $profile?->tmt_tetap,
                'status' => ($user->is_active && ! $user->trashed()) ? 'aktif' : 'nonaktif',
            ]);

            // Lampiran tidak bergantung pada profil (upload tidak mensyaratkannya).
            $this->syncDocuments($employee);

            // Karyawan tanpa profil: identitas & alamat tidak dibuat.
            if ($profile) {
                $employee->identity()->updateOrCreate([], $profile->only([
                    'no_nik', 'no_kk', 'npwp', 'no_bpjs_kes', 'no_bpjs_ket',
                    'bank_name', 'bank_account_number', 'bank_account_name',
                ]));

                // type 'utama': form HRIS belum membedakan alamat KTP dan domisili.
                $employee->addresses()->updateOrCreate(['type' => 'utama'], $profile->only([
                    'country', 'province', 'city', 'district', 'village', 'address', 'postal_code', 'rt', 'rw',
                ]) + ['is_primary' => true]);
            }

            $this->syncCareers($employee);

            return $employee;
        });
    }

    /** Lampiran KTP/KK/STR/SIP/RKK: salin baris (file_path sama, file TIDAK dipindah) dan buang
     * baris salinan yang asalnya sudah dihapus. STR/SIP ikut disalin karena karyawan nakes juga
     * memilikinya. */
    private function syncDocuments(Employee $employee): void
    {
        $source = UserProfileDocument::where('user_id', $employee->user_id)->get();

        foreach ($source as $doc) {
            EmployeeDocument::updateOrCreate(
                ['employee_id' => $employee->id, 'type' => $doc->label],
                ['file_path' => $doc->file_path],
            );
        }

        EmployeeDocument::where('employee_id', $employee->id)
            ->whereNotIn('type', $source->pluck('label'))
            ->delete();
    }

    private function syncCareers(Employee $employee): void
    {
        $ledger = UserJobPositions::where('user_id', $employee->user_id)->get();

        foreach ($ledger as $row) {
            $values = [
                'employee_id' => $employee->id,
                'job_position_id' => $row->job_position_id,
                'assigned_by' => $row->assigned_by,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'status' => $row->status,
            ];

            // Snapshot pangkat/kelas hanya untuk baris yang masih aktif; baris lama dibiarkan
            // (NULL saat dibuat, tidak ditimpa) - ledger tidak mencatat pangkat/kelas masa lalu.
            if ($row->status === 'active' && ! $row->end_date) {
                $values['pangkat_id'] = $employee->pangkat_id;
                $values['kelas_id'] = $employee->kelas_id;
            }

            EmployeeCareer::updateOrCreate(['user_job_position_id' => $row->id], $values);
        }

        // Baris riwayat yang ledger-nya sudah dihapus tidak boleh tertinggal.
        EmployeeCareer::where('employee_id', $employee->id)
            ->whereNotNull('user_job_position_id')
            ->whereNotIn('user_job_position_id', $ledger->pluck('id'))
            ->delete();
    }
}
