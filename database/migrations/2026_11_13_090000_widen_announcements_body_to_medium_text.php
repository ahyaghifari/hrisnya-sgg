<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // TEXT (64KB) kepenuhan oleh HTML rich editor (gambar base64 inline) - naik ke MEDIUMTEXT (16MB).
        Schema::table('announcements', function (Blueprint $table) {
            $table->mediumText('body')->change();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->text('body')->change();
        });
    }
};
