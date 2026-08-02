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
        Schema::table('task_records', function (Blueprint $table) {
            // Opt-in per task (dicentang creator pas bikin, 2026-08-02) - notify_creator_on_review
            // relevan kalau needs_review=true (kasih tahu pas pelaksana ajukan review),
            // notify_creator_on_complete relevan kalau needs_review=false (kasih tahu pas pelaksana
            // langsung selesaikan tanpa review).
            $table->boolean('notify_creator_on_review')->default(false)->after('needs_review');
            $table->boolean('notify_creator_on_complete')->default(false)->after('notify_creator_on_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn(['notify_creator_on_review', 'notify_creator_on_complete']);
        });
    }
};
