<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan Direktur Sikartap lepas dari Approval Engine (instruksi user 2026-09-07). Direktur
 * di-resolve dari 1 jabatan per perusahaan (Setting `sikartap_direktur_job_position_id`),
 * bukan flow approval berjenjang. Kolom baru menyimpan jejak keputusan yang dulu ada di
 * approval_snapshots/approval_histories.
 *
 * State status_keputusan_direktur: pending -> diajukan -> approved | rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->timestamp('keputusan_direktur_diajukan_at')->nullable()->after('status_keputusan_direktur');
            $table->string('keputusan_direktur_diajukan_by')->nullable()->after('keputusan_direktur_diajukan_at');
            $table->string('keputusan_direktur_by')->nullable()->after('keputusan_direktur_diajukan_by');
            $table->timestamp('keputusan_direktur_at')->nullable()->after('keputusan_direktur_by');
            $table->text('keputusan_direktur_note')->nullable()->after('keputusan_direktur_at');
        });

        // Konversi pengajuan yang lagi "in_review" di Approval Engine -> state baru 'diajukan'
        // supaya tetap muncul di antrian Direktur pola baru. Snapshot lama dibiarkan (histori).
        if (Schema::hasTable('approval_snapshots')) {
            $inflight = DB::table('approval_snapshots')
                ->where('module_type', 'sikartap_director_decision')
                ->where('status', 'in_review')
                ->pluck('module_id');

            if ($inflight->isNotEmpty()) {
                DB::table('sikartap')
                    ->whereIn('id', $inflight)
                    ->where('status_keputusan_direktur', 'pending')
                    ->update([
                        'status_keputusan_direktur' => 'diajukan',
                        'keputusan_direktur_diajukan_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->dropColumn([
                'keputusan_direktur_diajukan_at',
                'keputusan_direktur_diajukan_by',
                'keputusan_direktur_by',
                'keputusan_direktur_at',
                'keputusan_direktur_note',
            ]);
        });

        DB::table('sikartap')->where('status_keputusan_direktur', 'diajukan')->update(['status_keputusan_direktur' => 'pending']);
    }
};
