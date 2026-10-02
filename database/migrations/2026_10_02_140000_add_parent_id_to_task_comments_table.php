<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Balasan komentar (1 tingkat): parent_id null = komentar awal. Aditif - baris lama tidak tersentuh. */
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->foreignUlid('parent_id')->nullable()->after('user_id')->constrained('task_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
