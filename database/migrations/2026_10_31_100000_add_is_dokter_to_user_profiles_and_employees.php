<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda dokter. Sumber sementara masih user_profiles (form HRIS), employees cuma salinan
 * (EmployeeSyncService). Query User default membuang dokter lewat global scope 'dokter'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->boolean('is_dokter')->default(false);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_dokter')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['is_dokter']);
            $table->dropColumn('is_dokter');
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('is_dokter');
        });
    }
};
