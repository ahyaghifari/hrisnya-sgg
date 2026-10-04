<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.person_id = akun -> orang (roadmap_migrasi_bertahap_identity_hris.md): bentuk akhirnya
 * User -> Person -> Employee/Doctor. Nullable & unique (1 orang paling banyak 1 akun). Diisi oleh
 * PersonSyncService (observer + identity:backfill) lewat query langsung, belum dibaca fitur apa
 * pun. persons.user_id tetap sebagai jembatan sampai fase cleanup. Kolom users.nip/name TIDAK
 * dicabut - itu baru setelah semua pembacanya dipindah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('person_id')->nullable()->unique()->after('id')
                ->constrained('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
