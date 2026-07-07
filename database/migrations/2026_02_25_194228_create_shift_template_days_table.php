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
        Schema::create('shift_template_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shift_template_id')->constrained('shift_templates')->cascadeOnDelete();
            $table->integer('day_number');
            $table->foreignUlid('shift_id')->nullable()->constrained('m_shifts')->nullOnDelete();
            $table->boolean('is_off_day')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_template_days');
    }
};
