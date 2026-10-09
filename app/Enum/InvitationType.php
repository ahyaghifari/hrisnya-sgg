<?php

namespace App\Enum;

/** Jenis undangan (tabel `invitations` dipakai lintas modul). Tambah case baru per kebutuhan. */
enum InvitationType: string
{
    case SP_KLARIFIKASI = 'sp_klarifikasi';
    case COACHING = 'coaching';

    public function label(): string
    {
        return match ($this) {
            self::SP_KLARIFIKASI => 'Undangan Klarifikasi',
            self::COACHING => 'Undangan Coaching',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SP_KLARIFIKASI => 'fa-comments',
            self::COACHING => 'fa-user-group',
        };
    }
}
