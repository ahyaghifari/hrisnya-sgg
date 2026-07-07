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
        Schema::create('approval_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('module_type');
            $table->ulid('module_id');
            $table->foreignUlid('flow_id')->constrained('m_approval_flows');
            $table->string('flow_name');
            $table->json('steps_snapshot');
            $table->enum('status', ['in_review', 'approved', 'rejected', 'cancelled'])
                ->default('in_review');
            $table->integer('current_step_order')->nullable();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies');
            $table->foreignUlid('created_by')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_snapshots');
    }
};
