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
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->integer('approved_manpower')->default(0)->after('planned_manpower');
            $table->boolean('is_draft')->default(true)->after('parent_id');
            $table->string('status')->default('pending')->after('is_draft');
            $table->string('request_type')->nullable()->after('status');
            $table->foreignUlid('request_by')->nullable()->after('request_type')->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->after('request_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->dropColumn([
                'is_draft',
                'status',
                'request_type',
                'request_by',
                'approved_by',
                'approved_at',
                'rejected_at',
            ]);
        });
    }
};
