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
        Schema::create('doctor_fee_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('submission_id')
                ->constrained('doctor_fee_submissions')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('doctor_service_id')
                ->nullable()
                ->constrained('m_doctor_services')
                ->nullOnDelete();

            $table->string('service_name');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_paid')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_fee_items');
    }
};
