<?php

namespace App\Auth;

use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;

/** User model punya global scope 'non_karyawan' (buang Direktur/Super Admin dari daftar
 * karyawan) - provider ini bypass scope itu khusus pas retrieve buat login, supaya 4 akun
 * itu tetap bisa masuk. Lihat User::boot() dan config('auth.providers.users.driver').
 *
 * Login dengan NIP (`Auth::attempt(['nip' => ...])`) dicari lewat employees.nip (scope
 * User::whereNip), bukan kolom users.nip, supaya kolom itu bisa dicabut. Hanya dipakai saat
 * proses login; sesi yang sudah berjalan dipulihkan lewat retrieveById (tidak berubah). */
class KaryawanAwareUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        /** @var Builder $query */
        $query = parent::newModelQuery($model);

        return $query->withoutGlobalScopes(['non_karyawan', 'dokter']);
    }

    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter($credentials, fn ($key) => ! str_contains($key, 'password'), ARRAY_FILTER_USE_KEY);

        if (empty($credentials)) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if ($key === 'nip') {
                $query->whereNip($value);
            } elseif (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
