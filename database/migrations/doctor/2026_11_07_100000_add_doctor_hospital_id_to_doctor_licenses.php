<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB `doctor`: SIP terikat penempatan (tempat praktik), bukan dokumen profil. Kolom null untuk
 * STR/STR-KT (milik dokter) dan SIP lama yang belum ditautkan ke penempatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('doctor')->table('doctor_licenses', function (Blueprint $table) {
            $table->foreignId('doctor_hospital_id')->nullable()->after('doctor_id')
                ->constrained('doctor_hospitals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('doctor')->table('doctor_licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doctor_hospital_id');
        });
    }
};
