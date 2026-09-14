<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;

/**
 * Akun di DB `rekrutmen_db` (pelamar + staff rekrutmen). Dari HRIS umumnya read-only — cuma
 * dipakai buat menampilkan nama di relasi (creator lowongan, actor log, dst). Auth kandidat
 * TETAP di project `rekrutmen`, HRIS tidak pernah login lewat tabel ini — satu-satunya tulisan
 * dari sisi HRIS adalah PelamarUserResource (reset password / kosongkan verifikasi email).
 */
class User extends Model
{
    protected $connection = 'recruitment';

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];
}
