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
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attendance_request_id')->nullable()->constrained('attendance_requests')->nullOnDelete();
            $table->foreignUlid('snapshot_id')->constrained('approval_snapshots')->cascadeOnDelete();
            $table->foreignUlid('activity_id')->nullable()->constrained('approval_activities')->nullOnDelete();

            $table->string('action');

            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();

            // Catatan dan metadata tambahan
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
    }
};
