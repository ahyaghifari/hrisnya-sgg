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
        Schema::create('approval_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('snapshot_id')->constrained('approval_snapshots')->cascadeOnDelete();
            $table->integer('current_step_order')->nullable();
            $table->enum('approver_type', ['role', 'user', 'job_position'])->nullable();
            $table->string('approver_ref_id')->nullable();
            $table->string('scope_type')->nullable();
            $table->string('scope_ref_id')->nullable();
            $table->enum('status', ['pending', 'created', 'approved', 'rejected', 'skipped', 'canceled'])->default('pending');
            $table->text('note')->nullable();
            $table->foreignUlid('acted_by')->nullable()->constrained('users');
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_activities');
    }
};
