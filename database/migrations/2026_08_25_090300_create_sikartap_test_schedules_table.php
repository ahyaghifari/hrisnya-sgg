<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rewrite jadwal_tes legacy - 1 baris per pengajuan (HRD boleh ubah jadwal, bukan riwayat). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_test_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();
            $table->date('tanggal_tes');
            $table->time('waktu_tes');
            $table->string('tempat_tes');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_test_schedules');
    }
};
