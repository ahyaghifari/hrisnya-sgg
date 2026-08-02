<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

/** User model punya global scope 'non_karyawan' (buang Direktur/Super Admin dari daftar
 * karyawan) - provider ini bypass scope itu khusus pas retrieve buat login, supaya 4 akun
 * itu tetap bisa masuk. Lihat User::boot() dan config('auth.providers.users.driver'). */
class KaryawanAwareUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        /** @var Builder $query */
        $query = parent::newModelQuery($model);

        return $query->withoutGlobalScope('non_karyawan');
    }
}
