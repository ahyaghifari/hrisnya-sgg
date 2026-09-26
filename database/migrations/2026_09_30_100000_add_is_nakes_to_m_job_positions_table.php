<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penanda jabatan tenaga kesehatan. Status nakes karyawan diturunkan dari jabatannya, bukan dari users. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->boolean('is_nakes')->default(false)->after('kelas_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->dropColumn('is_nakes');
        });
    }
};
