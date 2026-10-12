<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Isian "Dan lain-lain" (baris 8) di Surat Perintah Perjalanan Dinas; opsional, teks bebas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->text('lain_lain')->nullable()->after('transportasi');
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', fn (Blueprint $table) => $table->dropColumn('lain_lain'));
    }
};
