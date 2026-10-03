<?php

namespace App\Models\Persuratan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengguna modul surat (DB `persuratan`, tabel `users` hasil impor sistem lama) — BUKAN App\Models\User HRIS.
 * password & reset_token sengaja disembunyikan dari serialisasi. Kolom lama email/peran/id_* belum dibuang
 * (menunggu keputusan migrasi), jadi tidak ada di $fillable — tidak diedit lewat aplikasi.
 */
class User extends Model
{
    protected $connection = 'persuratan';

    protected $table = 'users';

    protected $fillable = ['nama_lengkap', 'username', 'password', 'status', 'hris_user_id'];

    protected $hidden = ['password', 'reset_token'];

    /** User yang pernah membuat surat tidak boleh dihapus (nonaktifkan saja) supaya riwayat pembuat tidak yatim. */
    protected static function booted(): void
    {
        static::deleting(fn (User $user) => ! $user->surat()->exists());
    }

    public function surat(): HasMany
    {
        return $this->hasMany(Surat::class, 'legacy_user_id');
    }
}
