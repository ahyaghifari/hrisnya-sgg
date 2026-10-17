<?php

declare(strict_types=1);

namespace App\Policies\Employee;

use App\Models\Employee\EmployeeContract;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Satu model untuk dua jenis baris (orientasi dan kontrak), tapi izinnya TETAP terpisah: baris
 * type 'orientasi' memakai `Verb:EmployeeOrientation`, baris type 'kontrak' memakai
 * `Verb:EmployeeContract`, supaya role yang hanya boleh mengelola orientasi tidak ikut mengelola
 * kontrak. Pengecekan tingkat-kelas (viewAny / create) tanpa baris memakai izin kontrak; halaman
 * dan resource orientasi mengecek izin orientasi secara eksplisit.
 */
class EmployeeContractPolicy
{
    use HandlesAuthorization;

    private function subject(?EmployeeContract $contract): string
    {
        return $contract?->type === EmployeeContract::TYPE_ORIENTASI ? 'EmployeeOrientation' : 'EmployeeContract';
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeContract');
    }

    public function view(AuthUser $authUser, EmployeeContract $contract): bool
    {
        return $authUser->can('View:' . $this->subject($contract));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeContract');
    }

    public function update(AuthUser $authUser, EmployeeContract $contract): bool
    {
        return $authUser->can('Update:' . $this->subject($contract));
    }

    public function delete(AuthUser $authUser, EmployeeContract $contract): bool
    {
        return $authUser->can('Delete:' . $this->subject($contract));
    }

    public function restore(AuthUser $authUser, EmployeeContract $contract): bool
    {
        return $authUser->can('Restore:' . $this->subject($contract));
    }

    public function forceDelete(AuthUser $authUser, EmployeeContract $contract): bool
    {
        return $authUser->can('ForceDelete:' . $this->subject($contract));
    }
}
