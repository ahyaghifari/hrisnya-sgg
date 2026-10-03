<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Pulihkan surat.legacy_id & surat.legacy_user_id — terhapus oleh rollback migrasi 160000 yang gagal
 *    di tengah (DDL MySQL tidak transaksional). Idempoten: hanya menambah kalau belum ada.
 * 2) perusahaan.legacy_id dibuang: perusahaan.id sekarang SAMA dengan id sistem lama (diatur seeder).
 */
return new class extends Migration
{
    private const CONN = 'persuratan';

    public function up(): void
    {
        $sc = Schema::connection(self::CONN);

        $sc->table('surat', function (Blueprint $table) use ($sc) {
            if (! $sc->hasColumn('surat', 'legacy_id')) {
                $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            }
            if (! $sc->hasColumn('surat', 'legacy_user_id')) {
                $table->unsignedInteger('legacy_user_id')->nullable()->index()->after('dibuat_oleh_nama');
            }
        });

        if ($sc->hasColumn('perusahaan', 'legacy_id')) {
            $sc->table('perusahaan', fn (Blueprint $table) => $table->dropColumn('legacy_id'));
        }
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('perusahaan', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
        });
    }
};
