<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('persuratan')->table('perusahaan', function (Blueprint $table) {
            $table->renameColumn('kode', 'kode_surat');
        });
    }

    public function down(): void
    {
        Schema::connection('persuratan')->table('perusahaan', function (Blueprint $table) {
            $table->renameColumn('kode_surat', 'kode');
        });
    }
};
