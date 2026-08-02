<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            // Rating bintang 1-5 dari creator ke pelaksana, opsional, cuma bisa diisi setelah
            // task COMPLETE (2026-08-02).
            $table->unsignedTinyInteger('rating')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
