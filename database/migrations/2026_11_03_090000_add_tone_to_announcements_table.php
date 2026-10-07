<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Warna banner di Beranda: info | success | warning.
            $table->string('tone', 10)->default('info')->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('tone');
        });
    }
};
