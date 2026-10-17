<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penanda dokter umum (hanya bermakna bila is_dokter). Sumber user_profiles, employees salinan. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['user_profiles', 'employees'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('is_dokter_umum')->default(false)->after('is_dokter'));
        }
    }

    public function down(): void
    {
        foreach (['user_profiles', 'employees'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('is_dokter_umum'));
        }
    }
};
