<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->string('public_token')->nullable()->unique()->after('duration');
        });

        Schema::table('lms_courses', function (Blueprint $table) {
            $table->string('feedback_public_token')->nullable()->unique()->after('requires_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });

        Schema::table('lms_courses', function (Blueprint $table) {
            $table->dropColumn('feedback_public_token');
        });
    }
};
