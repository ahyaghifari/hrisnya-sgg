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
        Schema::create('candt_work_experiences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('business_type')->nullable();
            $table->text('company_address')->nullable();
            $table->string('position')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('salary', 50, 2)->nullable();
            $table->text('resign_reason')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candt_work_experiences');
    }
};
