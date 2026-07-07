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
        Schema::create('coachings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')
                ->constrained('m_companies')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUlid('coach_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('title')->nullable();
            $table->text('goals')->nullable();
            $table->text('employee_condition')->nullable();
            $table->text('solution')->nullable();
            $table->text('improvement_action')->nullable();
            $table->date('improvement_due_date')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coachings');
    }
};
