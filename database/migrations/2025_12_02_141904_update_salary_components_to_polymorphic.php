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
        Schema::table('salary_components', function (Blueprint $table) {
            if (Schema::hasColumn('salary_components', 'job_position_id')) {
                $table->dropForeign(['job_position_id']);
                $table->dropColumn('job_position_id');
            }

            $table->string('salaryable_type')->nullable()->after('salary_component_id');
            $table->string('salaryable_id')->nullable()->after('salaryable_type');

            $table->index(['salaryable_type', 'salaryable_id'], 'salary_components_salaryable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropIndex('salary_components_salaryable_index');
            $table->dropColumn(['salaryable_type', 'salaryable_id']);

            $table->foreignId('job_position_id')
                ->nullable()
                ->constrained('job_positions')
                ->cascadeOnDelete();
        });
    }
};
