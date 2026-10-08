<?php

namespace App\Enum;

/** Jenis undangan (tabel `invitations` dipakai lintas modul). Tambah case baru per kebutuhan. */
enum InvitationType: string
{
    case SP_KLARIFIKASI = 'sp_klarifikasi';
}
