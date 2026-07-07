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
        Schema::table('lms_user_histories', function (Blueprint $table) {
            $table->boolean('is_passed')->default(false)->after('score');
            $table->timestamp('passed_at')->nullable()->after('is_passed');
        });

        Schema::table('lms_course_users', function (Blueprint $table) {
            $table->boolean('is_passed')->default(false)->after('completed_at');
            $table->timestamp('passed_at')->nullable()->after('is_passed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_user_histories', function (Blueprint $table) {
            $table->dropColumn('is_passed');
            $table->dropColumn('passed_at');
        });

        Schema::table('lms_course_users', function (Blueprint $table) {
            $table->dropColumn('is_passed');
            $table->dropColumn('passed_at');
        });
    }
};
