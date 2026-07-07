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
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('lms_course_plan_schedules');
        Schema::dropIfExists('lms_course_plan_items');
        Schema::dropIfExists('lms_course_plans');
        Schema::dropIfExists('m_sub_divisions');
        Schema::dropIfExists('m_divisions');
        Schema::dropIfExists('m_departments');
        Schema::dropIfExists('m_job_position_competencies');
        Schema::dropIfExists('m_job_positions');
        Schema::dropIfExists('attendance_req_activities');
        Schema::dropIfExists('attendance_req_histories');

        Schema::create('m_org_structures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('capacity')->nullable();
            $table->foreignUlid('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('m_org_structures')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1); // 1=Department, 2=Division, 3=Sub-Division
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
