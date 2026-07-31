<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggungan BPJS Kesehatan per karyawan (istri/suami, anak, orang tua, dst di luar
     * karyawan sendiri) — BpjsKesehatanCalculator mengalikan potongan dengan (1 + count).
     * 1 baris per karyawan (unique, pola sama user_koperasi), `note` cuma keterangan bebas
     * (mis. "1 ibu, 1 adik"), tidak dipakai kalkulasi — cuma `count` yang menentukan pengali.
     */
    public function up(): void
    {
        Schema::create('bpjs_dependents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('count');
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_dependents');
    }
};
