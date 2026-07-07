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
        Schema::create('lms_courses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')->nullable()->constrained('lms_categories')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type_nakes', ['nakes', 'non-nakes', 'all'])->default('all');
            $table->enum('source_type', ['internal', 'external'])->default('internal');
            $table->text('thumbnail')->nullable();

            $table->enum('type', ['seasonal', 'scheduler'])->default('seasonal');
            $table->string('status')->nullable();

            $table->enum('repeat_cycle', ['daily', 'weekly', 'monthly', 'yearly'])->nullable()->comment('Hanya untuk scheduler');
            $table->integer('repeat_interval')->nullable()->comment('Setiap X periode');
            $table->date('first_start_date')->nullable();
            $table->date('last_end_date')->nullable();
            $table->date('next_run_date')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->integer('minimum_score')->default(0);

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_courses');
    }
};
