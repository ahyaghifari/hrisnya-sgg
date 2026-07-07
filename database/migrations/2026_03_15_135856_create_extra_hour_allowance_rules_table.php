<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Truncate existing rows before restructuring — old data is incompatible
        // with the new schema (company_id non-nullable FK would fail otherwise).
        // FK check toggling is MySQL-only syntax; skip on other drivers (mis. sqlite di testing).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        DB::table('shift_overtime_costs')->truncate();

        Schema::table('shift_overtime_costs', function (Blueprint $table) {
            if (Schema::hasColumn('shift_overtime_costs', 'day_type')) {
                $table->dropColumn('day_type');
            }
            if (Schema::hasColumn('shift_overtime_costs', 'hour')) {
                $table->dropColumn('hour');
            }
            if (Schema::hasColumn('shift_overtime_costs', 'rate_multiplier')) {
                $table->dropColumn('rate_multiplier');
            }

            if (!Schema::hasColumn('shift_overtime_costs', 'company_id')) {
                $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete()->after('overtime_type_id');
            }
            if (!Schema::hasColumn('shift_overtime_costs', 'maximum_overtime_hours')) {
                $table->integer('maximum_overtime_hours')->nullable()->after('overtime_type_id');
            }
            if (!Schema::hasColumn('shift_overtime_costs', 'overtime_rate')) {
                $table->decimal('overtime_rate', 15, 2)->nullable()->after('maximum_overtime_hours');
            }
        });

        Schema::create('extra_hour_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->ulidMorphs('allowanceable');
            $table->decimal('amount_per_hour', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('working_hours');
            $table->decimal('working_minutes', 8, 2)->nullable()->after('check_out_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('working_minutes');
            $table->integer('working_hours')->nullable()->after('check_out_time');
        });

        Schema::dropIfExists('extra_hour_rules');
    }
};
