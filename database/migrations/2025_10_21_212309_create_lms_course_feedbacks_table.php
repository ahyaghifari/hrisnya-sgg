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
        Schema::create('lms_course_feedbacks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('lms_courses')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
        });

        Schema::table('lms_courses', function (Blueprint $table) {
            $table->foreignUlid('company_id')->after('category_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_course_feedbacks');
        Schema::table('lms_courses', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->dropSoftDeletes();
        });
    }
};
