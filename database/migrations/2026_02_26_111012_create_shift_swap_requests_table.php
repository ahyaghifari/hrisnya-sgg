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
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('target_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('req_schedule_id')->constrained('shift_schedule_assignments')->cascadeOnDelete();
            $table->foreignUlid('target_schedule_id')->constrained('shift_schedule_assignments')->cascadeOnDelete();
            $table->date('req_date');
            $table->date('target_date');
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected'])->default('pending');
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('m_shifts', function (Blueprint $table) {
            $table->string('shift_type')->nullable()->after('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
        Schema::table('m_shifts', function (Blueprint $table) {
            $table->dropColumn('shift_type');
        });
    }
};
