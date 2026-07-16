<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * start_kelas_level/max_kelas_level/class_increment_rate di-backfill OTOMATIS tapi HANYA
     * untuk company yang datanya terkonfirmasi dari Excel HR (Holding = kode '0000', RS Syifa
     * Medika Banjarbaru = kode '0001' — lihat knowledge/markdown/analisis-payroll-4-*.md §2).
     * Company lain (Barabai, Lerina Inn, dst) SENGAJA dibiarkan NULL — datanya belum ada,
     * jangan ditebak. bpjs_basis juga sengaja tidak diisi (keputusan manual per pangkat).
     *
     * Dicocokkan lewat `m_companies.code` (stabil lintas environment), bukan hardcode ULID.
     */
    public function up(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->unsignedInteger('start_kelas_level')->nullable()->after('company_id');
            $table->unsignedInteger('max_kelas_level')->nullable()->after('start_kelas_level');
            $table->decimal('class_increment_rate', 5, 4)->nullable()->after('max_kelas_level');
            $table->string('bpjs_basis')->nullable()->after('class_increment_rate');
        });

        $holdingId = DB::table('m_companies')->where('code', '0000')->value('id');
        $rsBanjarbaruId = DB::table('m_companies')->where('code', '0001')->value('id');

        // [start_kelas_level, max_kelas_level] per tingkat pangkat, dari
        // analisis-payroll-4-pembacaan-excel-skema-aktual.md §2. Holding & RS beda start
        // untuk Staff/Koordinator (RS mulai lebih rendah), max sama.
        $tiers = [
            'holding' => [
                'Pelaksana'   => [1, 5],
                'Staff'       => [2, 5],
                'Koordinator' => [3, 7],
                'Supervisor'  => [5, 9],
                'Manager'     => [7, 13],
                'Direktur'    => [9, 15],
            ],
            'rs' => [
                'Pelaksana'   => [1, 5],
                'Staff'       => [1, 5],
                'Koordinator' => [2, 7],
                'Supervisor'  => [5, 9],
                'Manager'     => [7, 13],
                'Direktur'    => [9, 15],
            ],
        ];

        if ($holdingId) {
            foreach ($tiers['holding'] as $tierName => [$start, $max]) {
                DB::table('m_pangkats')
                    ->where('company_id', $holdingId)
                    ->where('name', 'like', "%{$tierName}%")
                    ->update([
                        'start_kelas_level' => $start,
                        'max_kelas_level' => $max,
                        'class_increment_rate' => 0.08,
                    ]);
            }
        }

        if ($rsBanjarbaruId) {
            foreach ($tiers['rs'] as $tierName => [$start, $max]) {
                DB::table('m_pangkats')
                    ->where('company_id', $rsBanjarbaruId)
                    ->where('name', 'like', "%{$tierName}%")
                    ->update([
                        'start_kelas_level' => $start,
                        'max_kelas_level' => $max,
                        'class_increment_rate' => 0.08,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn(['start_kelas_level', 'max_kelas_level', 'class_increment_rate', 'bpjs_basis']);
        });
    }
};
