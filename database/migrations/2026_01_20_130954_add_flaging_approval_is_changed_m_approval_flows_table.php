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
            $table->foreignUlid('updated_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::table('attendance_requests', function (Blueprint $table) {
            $table->boolean('is_flow_changed')->default(false)->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_approval_flows', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });

        Schema::table('attendance_requests', function (Blueprint $table) {
            $table->dropColumn('is_flow_changed');
        });
    }
};
