<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_user_histories', function (Blueprint $table) {
            $table->unique(['user_id', 'course_id']);
        });

        Schema::table('lms_meeting_attendances', function (Blueprint $table) {
            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lms_user_histories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_id']);
        });

        Schema::table('lms_meeting_attendances', function (Blueprint $table) {
            $table->dropUnique(['meeting_id', 'user_id']);
        });
    }
};
