<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Pelaksana tugas pindah ke tabel sendiri supaya 1 kartu bisa dikerjakan >1 orang, dengan rating
     * per pelaksana. task_records TIDAK diubah (sudah di production): assigned_to tetap diisi sebagai
     * pelaksana utama, rating lama disalin ke pelaksana utamanya.
     */
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained('task_records')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->foreignUlid('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        DB::table('task_records')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->chunk(500, function ($tasks) {
                DB::table('task_assignees')->insert($tasks->map(fn ($t) => [
                    'id' => (string) Str::ulid(),
                    'task_id' => $t->id,
                    'user_id' => $t->assigned_to,
                    'rating' => $t->rating,
                    'rated_by' => null,
                    'rated_at' => $t->rating ? $t->updated_at : null,
                    'created_at' => $t->created_at,
                    'updated_at' => $t->updated_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};
