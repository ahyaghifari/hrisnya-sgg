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
        Schema::create('task_board_backgrounds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            // 'todo' | 'saya' | 'individu' | ULID project (TaskProject::id) - satu tampilan board
            // per user, bukan per company, jadi tiap orang bisa custom sendiri-sendiri.
            $table->string('board_key');
            $table->enum('type', ['color', 'image']);
            $table->string('value');
            $table->timestamps();

            $table->unique(['user_id', 'board_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_board_backgrounds');
    }
};
