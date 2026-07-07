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
        Schema::create('task_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('m_org_structures')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('m_task_services')->cascadeOnDelete();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('sla_reviewer_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->text('requirement')->nullable();
            $table->boolean('is_self_assigned')->default(false);
            $table->string('status')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->string('risk_level')->nullable();

            $table->bigInteger('total_cost')->default(0);
            $table->bigInteger('unit_cost')->default(0);

            $table->date('due_date')->nullable();
            $table->date('responded_date')->nullable();
            $table->date('started_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->boolean('is_overdue')->default(false);

            $table->text('collaboration_note')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('solution')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
