<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_courses', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('lms_courses', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
