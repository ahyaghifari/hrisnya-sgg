<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Deskripsi singkat = isi notifikasi; isi lengkap (body, rich text) dibuka di halaman detail.
            $table->string('summary', 200)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
