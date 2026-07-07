<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['target_schedule_id']);
            $table->foreignUlid('target_schedule_id')
                ->nullable()
                ->change();
            $table->foreign('target_schedule_id')
                ->references('id')
                ->on('shift_schedule_assignments')
                ->nullOnDelete();
        });

        Schema::table('m_approval_flows', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['target_schedule_id']);

            $table->foreignUlid('target_schedule_id')
                ->nullable(false)
                ->change();

            $table->foreign('target_schedule_id')
                ->references('id')
                ->on('shift_schedule_assignments')
                ->cascadeOnDelete();
        });

        Schema::table('m_approval_flows', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
