<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status perkawinan karyawan - belum pernah ditrack untuk employee (cuma ada di Candidates
 * sebelumnya). Ditambahkan untuk mengisi kolom "STATUS PERKAWINAN" di export rekap gaji
 * (lihat App\Exports\Payroll\PayrollSlipRekapExport), string bebas (Belum Kawin/Kawin/dst)
 * bukan enum tertutup - beda perusahaan bisa beda istilah, dan tidak dipakai logic apapun
 * (murni data tampilan), jadi tidak perlu dipaksa ke set nilai tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('marital_status');
        });
    }
};
