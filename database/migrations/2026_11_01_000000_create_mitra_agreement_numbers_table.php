<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nomor perjanjian kerja per lamaran rekrutmen (DB `recruitment` dipegang project lain, jadi
// tabel ini di DB HRIS). `kind` = jenis dokumen (mitra = RS, pkwtt = holding), tiap jenis punya
// urutan sendiri yang reset tiap tahun lewat unique(kind, year, seq).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_agreement_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('job_application_id', 26)->unique();
            $table->string('kind', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('seq');
            $table->string('number');
            $table->timestamps();

            $table->unique(['kind', 'year', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_agreement_numbers');
    }
};
