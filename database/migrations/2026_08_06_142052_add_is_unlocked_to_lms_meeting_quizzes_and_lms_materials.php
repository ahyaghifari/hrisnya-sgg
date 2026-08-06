<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->boolean('is_unlocked')->default(false)->after('public_token');
        });

        Schema::table('lms_materials', function (Blueprint $table) {
            $table->boolean('is_unlocked')->default(false)->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->dropColumn('is_unlocked');
        });

        Schema::table('lms_materials', function (Blueprint $table) {
            $table->dropColumn('is_unlocked');
        });
    }
};
