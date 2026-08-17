<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redesign visibilitas Counseling (instruksi user 2026-08-17): `is_secret` per-record
 * (ditentukan karyawan sendiri saat submit) menggantikan gerbang biner lama
 * (Counseling::isVisibleTo() cuma identity match) - lihat App\Models\Counseling\Counseling
 * isAccessibleTo()/isContentVisibleTo(). `company_id` dihapus, cukup diwakilkan
 * user_id->company_id (tidak perlu disimpan redundan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselings', function (Blueprint $table) {
            $table->boolean('is_secret')->default(true)->after('status');
        });

        Schema::table('counselings', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('counselings', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('user_id')->constrained('m_companies')->cascadeOnDelete();
        });

        Schema::table('counselings', function (Blueprint $table) {
            $table->dropColumn('is_secret');
        });
    }
};
