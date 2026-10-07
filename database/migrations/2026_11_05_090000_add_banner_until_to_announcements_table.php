<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Banner Beranda tampil sampai tanggal ini (inklusif); null = tanpa batas.
            $table->date('banner_until')->nullable()->after('delivery');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('banner_until');
        });
    }
};
