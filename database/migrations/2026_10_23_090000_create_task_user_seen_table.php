<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kapan user terakhir membuka sebuah tugas - dasar bubble angka aktivitas baru di kartu.
        Schema::create('task_user_seen', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained('task_records')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('seen_at');

            $table->primary(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user_seen');
    }
};
