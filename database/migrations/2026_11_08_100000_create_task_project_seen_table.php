<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kapan user terakhir membuka Diskusi Board - dasar bubble "kamu ditandai".
        Schema::create('task_project_seen', function (Blueprint $table) {
            $table->foreignUlid('project_id')->constrained('task_projects')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('seen_at');

            $table->primary(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_project_seen');
    }
};
