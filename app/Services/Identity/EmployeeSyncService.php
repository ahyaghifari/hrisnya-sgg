<?php

namespace App\Services\Identity;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeCareer;
use App\Models\Person\Person;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin users + user_profiles + user_job_positions ke employees / employee_careers
 * (roadmap_migrasi_bertahap_identity_hris.md). SATU ARAH, idempotent, sama pola dengan
 * PersonSyncService: tabel lama tetap sumber & diedit seperti biasa.
 *
 * - employees.job_position_id/pangkat_id/kelas_id = kondisi sekarang, dari user_profiles apa adanya.
 * - employee_careers = riwayat dari user_job_positions. Selisih antara dua sumber jabatan ini
 *   (profil vs ledger) SENGAJA tidak ditutupi di sini - dilaporkan identity:verify.
 */
class EmployeeSyncService
{
    public function __construct(private PersonSyncService $persons) {}

    /** Akun yang bukan karyawan (2x Super Admin). Direktur & Sekretaris Direktur tetap karyawan
     * karena masih punya payroll (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS). Tetap dapat `persons`. */
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
            $person = Person::where('user_id', $user->id)->first() ?? $this->persons->syncUser($user);
            $profile = UserProfiles::where('user_id', $user->id)->first();

            $employee = Employee::updateOrCreate(['user_id' => $user->id], [
                'person_id' => $person->id,
                'nip' => $user->nip,
                'company_id' => $user->company_id,
                'employee_type_id' => $profile?->employee_type_id,
                'job_position_id' => $profile?->job_position_id,
                'pangkat_id' => $profile?->pangkat_id,
                'kelas_id' => $profile?->kelas_id,
                'joining_date' => $profile?->joining_date,
                'tmt_tetap' => $profile?->tmt_tetap,
                'status' => ($user->is_active && ! $user->trashed()) ? 'aktif' : 'nonaktif',
            ]);

            $this->syncCareers($employee);

            return $employee;
        });
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
