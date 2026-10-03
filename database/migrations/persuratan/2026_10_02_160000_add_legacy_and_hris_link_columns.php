<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom jejak migrasi & tautan ke HRIS (semua nullable, diisi belakangan):
 * - legacy_id: id baris di sistem lama (untuk impor idempoten + penelusuran).
 *   unit: unik per (perusahaan, legacy_id) — unit lama yang sama bisa dipakai di >1 perusahaan.
 * - surat.legacy_user_id: id_user_pembuat lama; dipetakan ke user HRIS (dibuat_oleh_id) terpisah.
 * - unit.hris_unit_id: tautan opsional ke unit HRIS (ULID, tanpa FK karena beda database).
 */
return new class extends Migration
{
    private const CONN = 'persuratan';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('perusahaan', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
        });

        Schema::connection(self::CONN)->table('unit', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->after('perusahaan_id');
            $table->char('hris_unit_id', 26)->nullable()->index()->after('nama');
            $table->unique(['perusahaan_id', 'legacy_id']);
        });

        Schema::connection(self::CONN)->table('surat', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            $table->unsignedInteger('legacy_user_id')->nullable()->index()->after('dibuat_oleh_nama');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('surat', function (Blueprint $table) {
            $table->dropColumn(['legacy_id', 'legacy_user_id']);
        });

        Schema::connection(self::CONN)->table('unit', function (Blueprint $table) {
            $table->dropUnique(['perusahaan_id', 'legacy_id']);
            $table->dropColumn(['legacy_id', 'hris_unit_id']);
        });

        Schema::connection(self::CONN)->table('perusahaan', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });
    }
};
