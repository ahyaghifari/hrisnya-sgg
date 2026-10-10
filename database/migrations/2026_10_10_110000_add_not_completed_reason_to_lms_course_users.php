<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_course_users', function (Blueprint $table) {
            $table->text('not_completed_reason')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('lms_course_users', function (Blueprint $table) {
            $table->dropColumn('not_completed_reason');
        });
    }
};
