<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\Payroll\UserKoperasi;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * UserKoperasi tidak punya Filament Resource (roster koperasi cuma dikelola lewat portal,
 * lihat KoperasiPage) jadi permission-nya tidak auto-digenerate `shield:generate` — dibuat
 * manual di sini, tetap pakai konvensi penamaan ability "Verb:Subject" yang sama supaya
 * konsisten & otomatis muncul di RoleForm (/admin/roles). $userKoperasi nullable di
 * update/delete supaya bisa dipakai baik untuk cek per-baris maupun cek visibilitas tombol
 * generik (tanpa instance spesifik, mis. tombol "Ubah Amount" massal).
 */
class UserKoperasiPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserKoperasi');
    }

    public function view(AuthUser $authUser, UserKoperasi $userKoperasi): bool
    {
        return $authUser->can('View:UserKoperasi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserKoperasi');
    }

    public function update(AuthUser $authUser, ?UserKoperasi $userKoperasi = null): bool
    {
        return $authUser->can('Update:UserKoperasi');
    }

    public function delete(AuthUser $authUser, ?UserKoperasi $userKoperasi = null): bool
    {
        return $authUser->can('Delete:UserKoperasi');
    }
}
