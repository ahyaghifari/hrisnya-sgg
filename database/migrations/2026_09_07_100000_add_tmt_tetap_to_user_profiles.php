<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TMT (Terhitung Mulai Tanggal) karyawan diangkat jadi Tetap — 1 nilai per orang (mirip
 * joining_date), makanya kolom di user_profiles, bukan ledger seperti user_contracts. Diisi
 * otomatis saat Sikartap Keputusan Direktur final "approved" (ApprovalFlowService), atau
 * manual oleh HR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->date('tmt_tetap')->nullable()->after('joining_date');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('tmt_tetap');
        });
    }
};
