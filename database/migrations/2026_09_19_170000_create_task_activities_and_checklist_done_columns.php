<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log aktivitas tugas: siapa, kapan, melakukan apa (pindah List, ubah status, ceklis, dst).
        Schema::create('task_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained('task_records')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        // Kapan & siapa yang menyelesaikan (mencentang) item checklist.
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->timestamp('done_at')->nullable()->after('is_done');
            $table->foreignUlid('done_by')->nullable()->after('done_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('done_by');
            $table->dropColumn('done_at');
        });

        Schema::dropIfExists('task_activities');
    }
};
