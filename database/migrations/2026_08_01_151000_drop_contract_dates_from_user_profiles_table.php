<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balikan dari kolom contract_end_date di user_profiles (ditambah migration
 * 2026_08_01_122053_*) - ternyata cuma duplikat murni dari data yang SUDAH ada di user_contracts
 * (tabel ledger riwayat kontrak, migration create_user_contracts_table). "Kontrak aktif saat ini"
 * sekarang dibaca lewat User::currentContract() (hasOne latestOfMany, pola sama
 * User::activeJobPosition()), bukan kolom pointer terpisah di profile yang harus disinkronkan
 * manual tiap approve(). contract_start_date TIDAK pernah jadi kolom user_profiles - migration
 * 2026_08_01_150000_* sudah direvisi supaya cuma menambahkannya ke user_transitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->date('contract_end_date')->nullable()->after('joining_date');
        });
    }
};
