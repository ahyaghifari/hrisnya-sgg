<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * contract_start_date di user_transitions - data milik BARIS TRANSISI itu sendiri (sejajar
 * effective_date/old_value yang sudah ada), dipakai buat mengisi UserContract::contract_start_date
 * saat approve() dan ditampilkan di detail transisi. BUKAN duplikat "state saat ini" (itu ada di
 * user_contracts, lihat migration create_user_contracts_table + User::currentContract()) - jadi
 * TIDAK ditambahkan ke user_profiles seperti draft awal migration ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_transitions', function (Blueprint $table) {
            $table->date('contract_start_date')->nullable()->after('effective_date');
        });
    }

    public function down(): void
    {
        Schema::table('user_transitions', function (Blueprint $table) {
            $table->dropColumn('contract_start_date');
        });
    }
};
