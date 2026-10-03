<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users hasil impor sistem lama punya kolom NOT NULL tanpa default (email, peran, id_*) yang sudah tidak
 * diisi aplikasi baru -> pengguna baru gagal dibuat. Dibuat boleh kosong dulu (TIDAK ada data yang dibuang);
 * kolomnya sendiri dibuang di migrasi terpisah setelah keputusan username / id_unit / peran beres.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('persuratan')->hasTable('users')) {
            return;
        }

        $db = DB::connection('persuratan');
        $ubah = [
            'email' => 'VARCHAR(255) NULL',
            'peran' => "ENUM('admin','auditor','pj','manager','direktur','manager_audit','sekretaris') NULL",
            'id_departemen' => 'INT(11) NULL',
            'id_unit' => 'INT(11) NULL',
            'id_perusahaan' => 'INT(11) NULL',
        ];

        foreach ($ubah as $kolom => $definisi) {
            if (Schema::connection('persuratan')->hasColumn('users', $kolom)) {
                $db->statement("ALTER TABLE `users` MODIFY `{$kolom}` {$definisi}");
            }
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan ke NOT NULL: baris baru bisa sudah berisi NULL.
    }
};
