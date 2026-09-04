<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;

/**
 * Akun di DB `rekrutmen_db` (pelamar + staff rekrutmen). Read-only dari HRIS — cuma dipakai
 * buat menampilkan nama di relasi (creator lowongan, actor log, dst). Auth kandidat TETAP di
 * project `rekrutmen`, HRIS tidak pernah autentikasi lewat tabel ini.
 */
class User extends Model
{
    protected $connection = 'recruitment';

    protected $table = 'users';

    protected $guarded = [];
}
