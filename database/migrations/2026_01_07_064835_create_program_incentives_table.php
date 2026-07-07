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
        Schema::create('program_incentives', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->constrained('m_companies')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('program_name');
            $table->text('note')->nullable();
            $table->date('incentive_date');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_processed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_incentives');
    }
};
