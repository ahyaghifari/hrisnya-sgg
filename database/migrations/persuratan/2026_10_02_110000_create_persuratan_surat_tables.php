<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unit = kode unit di nomor surat (bukan unik: sistem lama punya unit beda dengan kode sama).
 * nomor_surat_counter di-scope (perusahaan, tahun) — SAMA seperti sistem lama, lintas unit & jenis.
 */
return new class extends Migration
{
    private const CONN = 'persuratan';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('unit', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10);
            $table->string('nama');
            $table->timestamps();
        });

        Schema::connection(self::CONN)->create('nomor_surat_counter', function (Blueprint $table) {
            $table->foreignId('perusahaan_id')->constrained('perusahaan');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->primary(['perusahaan_id', 'tahun']);
        });

        Schema::connection(self::CONN)->create('surat', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->unsignedInteger('nomor_urut');
            $table->foreignId('perusahaan_id')->constrained('perusahaan');
            $table->string('jenis', 10);
            $table->foreignId('unit_id')->constrained('unit');
            $table->date('tanggal');
            $table->string('perihal');
            $table->string('dibuat_oleh_id', 26)->nullable();
            $table->string('dibuat_oleh_nama')->nullable();
            $table->timestamps();
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('surat');
        Schema::connection(self::CONN)->dropIfExists('nomor_surat_counter');
        Schema::connection(self::CONN)->dropIfExists('unit');
    }
};
