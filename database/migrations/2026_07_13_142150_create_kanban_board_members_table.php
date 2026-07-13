<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_board_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('board_id')->constrained('kanban_boards')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // owner | assigner | member
            $table->timestamps();
            $table->unique(['board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_board_members');
    }
};
