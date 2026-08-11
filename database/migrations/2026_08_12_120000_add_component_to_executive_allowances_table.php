<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris ExecutiveAllowance sekarang bisa dipilih dari PayrollComponentEnum (mis. BPJS
 * Kesehatan, Insentif) - bukan cuma label bebas manual (permintaan user 2026-08-12).
 * Kolom ini NULL kalau baris manual, isi value enum (mis. 'bpjs_kesehatan') kalau dari
 * komponen - dipakai buat prefill form Ubah tahu harus balik ke mode Komponen atau Manual.
 * `label`/`type` TETAP diisi (diturunkan dari enum saat pilih Komponen) - Calculator baca
 * dari situ, tidak perlu tahu soal kolom `component` ini sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executive_allowances', function (Blueprint $table) {
            $table->string('component', 50)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('executive_allowances', function (Blueprint $table) {
            $table->dropColumn('component');
        });
    }
};
