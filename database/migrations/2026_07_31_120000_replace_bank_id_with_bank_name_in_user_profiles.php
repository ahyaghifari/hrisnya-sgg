<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank jadi isian bebas (bank_name), bukan pilihan dari master m_banks - tabel m_banks kosong
 * total & tidak ada satu pun UserProfiles yang bank_id-nya terisi (dikonfirmasi sebelum
 * migration ini dibuat), jadi aman drop tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
            $table->string('bank_name')->nullable()->after('bank_account_name');
        });

        Schema::dropIfExists('m_banks');
    }

    public function down(): void
    {
        Schema::create('m_banks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('bank_name');
            $table->ulid('bank_id')->nullable();
            $table->foreign('bank_id')->references('id')->on('m_banks')->nullOnDelete();
        });
    }
};
