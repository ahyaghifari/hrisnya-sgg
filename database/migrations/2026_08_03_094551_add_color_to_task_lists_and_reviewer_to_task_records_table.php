<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_lists', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('name');
        });

        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('reviewer_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn('reviewer_id');
        });

        Schema::table('task_lists', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
