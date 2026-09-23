<?php

declare(strict_types=1);

namespace App\Policies\Sp;

use App\Models\Sp\EmployeeSp;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ditulis manual sesuai bentuk yang di-generate `php artisan shield:generate --resource=EmployeeSpResource`
 * (baris permission ViewAny:EmployeeSp dkk masih perlu di-seed lewat command itu saat DB tersedia -
 * lihat planning/2026-09-23-sp-dan-merit-point.md §2.1, gerbang modul tetap SISTEM_PEMBINAAN_KARYAWAN
 * yang dicek terpisah di layer Livewire, sama pola CoachingPolicy).
 */
class EmployeeSpPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeSp');
    }

    public function view(AuthUser $authUser, EmployeeSp $employeeSp): bool
    {
        return $authUser->can('View:EmployeeSp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeSp');
    }

    public function update(AuthUser $authUser, EmployeeSp $employeeSp): bool
    {
        return $authUser->can('Update:EmployeeSp');
    }

    public function delete(AuthUser $authUser, EmployeeSp $employeeSp): bool
    {
        return $authUser->can('Delete:EmployeeSp');
    }

    public function restore(AuthUser $authUser, EmployeeSp $employeeSp): bool
    {
        return $authUser->can('Restore:EmployeeSp');
    }

    public function forceDelete(AuthUser $authUser, EmployeeSp $employeeSp): bool
    {
        return $authUser->can('ForceDelete:EmployeeSp');
    }
}
