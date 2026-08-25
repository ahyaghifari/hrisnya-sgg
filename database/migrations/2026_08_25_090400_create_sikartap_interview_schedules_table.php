<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rewrite jadwal_wawancara legacy - 1 baris per pengajuan. Pewawancara disimpan sebagai
 * nama teks bebas (bukan FK ke users), sama seperti legacy - pewawancara sering bukan
 * user sistem (mis. psikolog eksternal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_interview_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();
            $table->date('tanggal_wawancara');
            $table->time('waktu_wawancara');
            $table->string('tempat_wawancara');
            $table->string('pewawancara');
            $table->string('pewawancara_2')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_interview_schedules');
    }
};
