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
        Schema::table('task_reviews', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('reviewer_id');
            $table->text('rejected_note')->nullable()->after('comment');
            $table->timestamp('decided_at')->nullable()->after('rejected_note');
            $table->unique(['task_id', 'reviewer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_reviews', function (Blueprint $table) {
            $table->dropUnique(['task_id', 'reviewer_id']);
            $table->dropColumn(['status', 'rejected_note', 'decided_at']);
        });
    }
};
