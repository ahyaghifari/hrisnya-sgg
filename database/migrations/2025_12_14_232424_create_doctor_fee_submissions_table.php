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
        Schema::create('doctor_fee_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')
                ->constrained('m_companies')
                ->cascadeOnDelete();

            $table->string('sep_number')->nullable();
            $table->string('patient_name')->nullable();
            $table->decimal('total_claim', 15, 2)->nullable();
            $table->string('service_type')->nullable();
            $table->string('status')->nullable();

            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_fee_submissions');
    }
};
