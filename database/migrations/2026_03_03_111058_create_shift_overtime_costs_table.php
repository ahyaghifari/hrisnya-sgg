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
        // Delete the old table if it exists
        Schema::dropIfExists('m_overtime_rate_rules');

        Schema::table('m_shifts', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('shift_schedule_assignments', function (Blueprint $table) {
            $table->string('schedule_status')->nullable()->after('notes');
        });

        Schema::create('shift_overtime_costs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shift_id')
                ->constrained('m_shifts')
                ->cascadeOnDelete();
            $table->foreignUlid('overtime_type_id')
                ->nullable()
                ->constrained('m_overtime_types')
                ->nullOnDelete();
            $table->enum('day_type', ['weekday', 'weekend'])->default('weekday');
            $table->integer('hour');
            $table->decimal('rate_multiplier', 5, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_overtime_costs');

        Schema::table('m_shifts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('shift_schedule_assignments', function (Blueprint $table) {
            $table->dropColumn('schedule_status');
        });
    }
};
