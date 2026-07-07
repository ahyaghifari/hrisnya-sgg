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
        Schema::create('lms_user_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('course_id')
                ->constrained('lms_courses')
                ->cascadeOnDelete();

            $table->foreignUlid('schedule_id')
                ->nullable()
                ->constrained('lms_course_schedules')
                ->nullOnDelete();

            $table->decimal('score', 5, 2)->nullable();
            $table->text('certificate_url')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_user_histories');
    }
};
