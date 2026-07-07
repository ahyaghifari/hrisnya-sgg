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
        Schema::create('user_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number');
            $table->string('status');
            $table->foreignUlid('company_id')
                ->constrained('m_companies')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('transition_type');
            $table->ulid('transitionable_id')->nullable();
            $table->string('transitionable_type')->nullable();

            $table->string('old_value')->nullable();

            $table->date('effective_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('manager_comment')->nullable();

            $table->foreignUlid('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_transitions');
    }
};
