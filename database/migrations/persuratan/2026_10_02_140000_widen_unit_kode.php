<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sistem lama: unit.kode_unit VARCHAR(20) — samakan supaya data lama muat. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('persuratan')->table('unit', function (Blueprint $table) {
            $table->string('kode', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::connection('persuratan')->table('unit', function (Blueprint $table) {
            $table->string('kode', 10)->change();
        });
    }
};
