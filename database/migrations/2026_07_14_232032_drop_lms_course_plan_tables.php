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
        Schema::dropIfExists('lms_course_plan_schedules');
        Schema::dropIfExists('lms_course_plan_items');
        Schema::dropIfExists('lms_course_plans');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
