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
        Schema::table('m_approval_flows', function (Blueprint $table) {
            $table->enum('flow_type', ['default', 'specific'])
                ->default('default')
                ->after('module');
        });

        Schema::create('m_approval_flow_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('approval_flow_id')
                ->constrained('m_approval_flows')
                ->cascadeOnDelete();
            $table->ulidMorphs('assignable');
            $table->timestamps();
            
            $table->unique([
                'approval_flow_id',
                'assignable_id',
                'assignable_type'
            ], 'flow_assign_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_approval_flows', function (Blueprint $table) {
            $table->dropColumn('flow_type');
        });

        Schema::dropIfExists('m_approval_flow_assignments');
    }
};
