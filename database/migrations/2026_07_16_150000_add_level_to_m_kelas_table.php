<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `m_kelas.name` sudah berupa angka literal + suffix nama company (mis. "13 (RS)") —
     * `level` di-backfill otomatis dari angka di depan `name`, dijalankan di migration yang sama
     * (bukan command terpisah) supaya `php artisan migrate` saja sudah cukup. Idempotent: baris
     * yang `level`-nya sudah terisi dilewati, baris yang nama-nya gagal di-parse dibiarkan NULL
     * dan dicatat (bukan ditebak).
     */
    public function up(): void
    {
        Schema::table('m_kelas', function (Blueprint $table) {
            $table->unsignedInteger('level')->nullable()->after('name');
        });

        DB::table('m_kelas')->whereNull('level')->orderBy('id')->get(['id', 'name'])->each(function ($row) {
            if (preg_match('/^(\d+)/', trim($row->name), $matches)) {
                DB::table('m_kelas')->where('id', $row->id)->update(['level' => (int) $matches[1]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_kelas', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
