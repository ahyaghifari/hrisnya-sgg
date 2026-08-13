<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email pribadi karyawan - dipakai sebagai tujuan kirim slip gaji (SendPayrollSlipJob), fallback
 * ke email akun (users.email) kalau kosong. Terpisah dari email akun karena email akun bisa saja
 * cuma dipakai buat login (mis. format standar perusahaan), bukan email yang benar-benar dicek
 * karyawan buat terima dokumen pribadi seperti slip gaji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('email_pribadi')->nullable()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('email_pribadi');
        });
    }
};
