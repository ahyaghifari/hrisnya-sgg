<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users (DB persuratan) di-import dari sistem lama (id dipertahankan; surat.legacy_user_id = users.id).
 * hris_user_id = tautan opsional ke user HRIS (ULID, tanpa FK karena beda database); kosong untuk
 * user di luar HRIS (mis. Koperasi). Hanya menambah kolom — tidak ada yang dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sc = Schema::connection('persuratan');

        if ($sc->hasTable('users') && ! $sc->hasColumn('users', 'hris_user_id')) {
            $sc->table('users', fn (Blueprint $table) => $table->char('hris_user_id', 26)->nullable()->index()->after('status'));
        }
    }

    public function down(): void
    {
        $sc = Schema::connection('persuratan');

        if ($sc->hasTable('users') && $sc->hasColumn('users', 'hris_user_id')) {
            $sc->table('users', fn (Blueprint $table) => $table->dropColumn('hris_user_id'));
        }
    }
};
