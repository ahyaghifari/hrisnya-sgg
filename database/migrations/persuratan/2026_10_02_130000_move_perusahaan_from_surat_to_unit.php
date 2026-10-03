<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perusahaan (& departemen) tidak disimpan di surat — diwakili unit. unit sekarang milik satu
 * perusahaan; surat -> unit -> perusahaan. unit_id wajib lagi (tanpa unit tidak tahu perusahaannya).
 * Unit yang sama di 2 perusahaan = 2 baris unit. Tabel unit & surat harus kosong saat migrasi ini jalan.
 */
return new class extends Migration
{
    private const CONN = 'persuratan';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('unit', function (Blueprint $table) {
            $table->foreignId('perusahaan_id')->after('id')->constrained('perusahaan');
        });

        Schema::connection(self::CONN)->table('surat', function (Blueprint $table) {
            $table->dropConstrainedForeignId('perusahaan_id');
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('surat', function (Blueprint $table) {
            $table->foreignId('perusahaan_id')->after('nomor_urut')->constrained('perusahaan');
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });

        Schema::connection(self::CONN)->table('unit', function (Blueprint $table) {
            $table->dropConstrainedForeignId('perusahaan_id');
        });
    }
};
