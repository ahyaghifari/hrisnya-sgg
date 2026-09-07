<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal efektif TMT Karyawan Tetap untuk Sikartap (instruksi user 2026-09-07). HRD isi
 * `tmt_tetap` saat mengajukan ke Direktur (step 8). Direktur cuma putuskan lulus/tidak.
 *
 * `keputusan_direktur_applied_at` = kapan efek "resmi Tetap" (stempel user_profiles.tmt_tetap
 * + flip employee_type_id) BENAR-BENAR dijalankan. NULL + status approved = masih nunggu
 * tanggal TMT tiba; command harian `sikartap:apply-due-tetap` yang menerapkan. tmt_tetap <=
 * hari approve = langsung diterapkan. Juga guard idempotensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->date('tmt_tetap')->nullable()->after('keputusan_direktur_note');
            $table->timestamp('keputusan_direktur_applied_at')->nullable()->after('tmt_tetap');
        });

        // Backfill: pengajuan yang sudah approved di logika lama SUDAH langsung diterapkan
        // (markUserPermanent stempel user_profiles.tmt_tetap saat approve). Stempel applied_at
        // = keputusan_direktur_at, dan tmt_tetap ikut yang di profil (atau tanggal keputusan).
        DB::table('sikartap')
            ->where('status_keputusan_direktur', 'approved')
            ->whereNull('keputusan_direktur_applied_at')
            ->update(['keputusan_direktur_applied_at' => DB::raw('keputusan_direktur_at')]);

        // Portable (SQLite + MySQL) — hindari UPDATE ... JOIN. Backfill sekali jalan.
        DB::table('sikartap')
            ->where('status_keputusan_direktur', 'approved')
            ->whereNull('tmt_tetap')
            ->get(['id', 'user_id', 'keputusan_direktur_at'])
            ->each(function ($row) {
                $profileTmt = DB::table('user_profiles')->where('user_id', $row->user_id)->value('tmt_tetap');
                $fallback = $row->keputusan_direktur_at ? substr((string) $row->keputusan_direktur_at, 0, 10) : null;

                DB::table('sikartap')->where('id', $row->id)->update([
                    'tmt_tetap' => $profileTmt ?: $fallback,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->dropColumn(['tmt_tetap', 'keputusan_direktur_applied_at']);
        });
    }
};
