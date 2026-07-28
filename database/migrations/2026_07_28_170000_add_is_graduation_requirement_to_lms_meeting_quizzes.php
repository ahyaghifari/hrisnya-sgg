<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->boolean('is_graduation_requirement')->default(false)->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->dropColumn('is_graduation_requirement');
        });
    }
};
