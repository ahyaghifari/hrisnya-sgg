<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('board_id')->nullable()->constrained('kanban_boards')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('todo'); // todo | in_progress | review | done
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('priority')->default('medium'); // low | medium | high | urgent
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('related_task_id')->nullable()->constrained('task_records')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_tasks');
    }
};
