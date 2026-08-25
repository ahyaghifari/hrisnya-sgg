<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda tangan elektronik berbarcode, 1 baris per peran per dokumen (unique constraint di
 * bawah). Nama/jabatan sengaja SNAPSHOT saat ttd dibubuhkan (bukan dibaca live dari users/
 * profil) — jabatan penandatangan bisa berubah setelah dokumen dicetak, dokumen cetak harus
 * tetap menunjukkan identitas saat itu. Kode barcode sengaja pendek — lihat
 * knowledge/markdown/analisis-sarpras-migrasi.md §C.5 buat alasan panjang kode & lebar modul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_request_signatures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sarpras_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('peran');
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('kode', 32)->unique();

            $table->timestamps();

            $table->unique(['sarpras_request_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_request_signatures');
    }
};
