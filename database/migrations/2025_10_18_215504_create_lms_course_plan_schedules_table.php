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
        Schema::create('lms_course_plan_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('plan_item_id')->constrained('lms_course_plan_items')->cascadeOnDelete();

            $table->enum('planned_month', [
                'jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'
            ])->nullable();

            $table->tinyInteger('planned_week')->nullable();
            $table->date('planned_date')->nullable();

            $table->enum('status', [
                'planned', 'rescheduled', 'completed', 'cancelled'
            ])->default('planned');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_plan_schedules');
    }
};
