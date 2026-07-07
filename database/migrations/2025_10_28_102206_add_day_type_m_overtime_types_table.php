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
        Schema::table('m_overtime_types', function (Blueprint $table) {
            $table->dropColumn('rate_multiplier');
            $table->enum('day_type', ['weekday', 'weekend'])->nullable()->after('name');
        });

        Schema::create('m_overtime_rate_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('overtime_type_id')
                ->constrained('m_overtime_types')
                ->cascadeOnDelete();
            $table->enum('day_type', ['weekday', 'weekend'])->default('weekday');
            $table->integer('hour');
            $table->decimal('rate_multiplier', 5, 2); // pengali misal 1.5, 2.0
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_overtime_types', function (Blueprint $table) {
            $table->integer('rate_multiplier')->nullable()->after('name');
            $table->dropColumn('day_type');
        });

        Schema::dropIfExists('m_overtime_rate_rules');
    }
};
