<?php

namespace App\Services\Doctor;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Akun login HRIS untuk dokter (data dokter di DB `doctor`, login tetap lewat users HRIS).
 * Dibuat bersamaan dengan dokter dari panel /dokter; employees ikut terisi lewat EmployeeSyncObserver
 * (is_dokter menyembunyikannya dari daftar karyawan biasa - global scope 'dokter' di User).
 */
class DoctorAccountService
{
    /** @param array{name: string, email: string, password: string, nip?: ?string, company_id?: ?string, jenis_kelamin?: ?string, role_ids?: array} $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'nip' => $data['nip'] ?: null,
                'company_id' => $data['company_id'] ?? null,
                'is_active' => true,
            ]);

            $user->profile()->updateOrCreate(['user_id' => $user->id], [
                'is_dokter' => true,
                'gender' => match ($data['jenis_kelamin'] ?? null) {
                    'L' => 'laki_laki',
                    'P' => 'perempuan',
                    default => null,
                },
            ]);

            if (! empty($data['role_ids'])) {
                $user->syncRoles(Role::whereIn('id', $data['role_ids'])->get());
            }

            return $user;
        });
    }

    /** Foto = atribut akun (users.profile_picture, disk public) - sama seperti foto profil karyawan. */
    public function setPhoto(User $user, ?string $path): void
    {
        $old = $user->profilePhotoPath();

        if ($path === $old) {
            return;
        }

        $user->update(['profile_picture' => $path ? [$path] : []]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
