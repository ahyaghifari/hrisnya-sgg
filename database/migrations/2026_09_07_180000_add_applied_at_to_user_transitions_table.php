<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * applied_at = kapan efek transisi BENAR-BENAR diterapkan ke data karyawan (jabatan/role/
 * nonaktif). Beda dari approved_at: transisi bisa disetujui hari ini tapi baru diterapkan
 * pas effective_date tiba (command `transitions:apply-due`). NULL + status approved = masih
 * nunggu tanggal efektif. NULL juga jadi guard idempotensi (command skip yang applied_at-nya
 * sudah keisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_transitions', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('approved_at');
        });

        // Backfill: semua transisi yang sudah approved di logika lama SUDAH langsung diterapkan
        // saat approve. Stempel applied_at = approved_at biar command baru tidak menerapkannya
        // ulang.
        DB::table('user_transitions')
            ->where('status', 'approved')
            ->whereNull('applied_at')
            ->update(['applied_at' => DB::raw('approved_at')]);
    }

    public function down(): void
    {
        Schema::table('user_transitions', function (Blueprint $table) {
            $table->dropColumn('applied_at');
        });
    }
};
