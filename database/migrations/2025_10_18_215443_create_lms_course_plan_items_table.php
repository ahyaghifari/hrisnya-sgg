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
        Schema::create('lms_course_plan_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->nullable()->constrained('lms_course_plans')->cascadeOnDelete();
            $table->foreignUlid('course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->enum('source_type', ['employee', 'system'])->nullable();
            
            $table->string('proposed_title')->nullable();
            $table->text('proposed_description')->nullable();
            $table->foreignUlid('competency_id')->nullable()->constrained('m_competencies')->nullOnDelete();
            $table->foreignUlid('target_org_structure_id')->nullable()->constrained('m_org_structures')->nullOnDelete();
            $table->foreignUlid('target_job_position_id')->nullable()->constrained('m_job_positions')->nullOnDelete();

            $table->integer('estimated_participants')->nullable();
            $table->integer('estimated_cost')->nullable();

            $table->enum('request_status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'cancelled',
                'completed'
            ])->default('draft');

            $table->foreignUlid('realized_course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->foreignUlid('realized_schedule_id')->nullable()->constrained('lms_course_schedules')->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_plan_items');
    }
};
