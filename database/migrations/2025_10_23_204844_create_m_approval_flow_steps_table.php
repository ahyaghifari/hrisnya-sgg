<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
        |--------------------------------------------------------------------------
        | MASTER APPROVAL FLOW STEPS
        |--------------------------------------------------------------------------
        | Menyimpan detail urutan langkah-langkah dalam flow master
        |--------------------------------------------------------------------------
        */
    public function up(): void
    {
        Schema::create('m_approval_flow_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('flow_id')->constrained('m_approval_flows')->cascadeOnDelete();
            $table->unsignedInteger('step_order')->default(1);
            $table->enum('approver_type', ['role', 'user', 'job_position'])->default('role');
            $table->string('approver_ref_id');
            $table->enum('approval_mode', ['any', 'all'])->default('any');
            $table->enum('scope_type', ['same_department', 'specific_department', 'global'])->default('same_department');
            $table->foreignUlid('scope_ref_id')->nullable()->constrained('m_org_structures')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_approval_flow_steps');
    }
};
