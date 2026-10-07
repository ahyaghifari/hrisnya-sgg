<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Cara pengumuman sampai: notification (notifikasi saja) | banner (banner Beranda saja) | both.
            $table->string('delivery', 12)->default('both')->after('tone');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('delivery');
        });
    }
};
