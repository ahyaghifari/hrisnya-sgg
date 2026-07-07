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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignUlid('schedule_id')->nullable()->constrained('shift_schedule_assignments')->nullOnDelete()->after('date');
            $table->foreignUlid('shift_id')->nullable()->constrained('m_shifts')->nullOnDelete()->after('schedule_id');
            $table->float('late_minutes')->nullable()->after('working_hours');
            $table->float('early_leave_minutes')->nullable()->after('late_minutes');
            $table->float('overtime_minutes')->nullable()->after('early_leave_minutes');
            $table->float('permission_minutes')->nullable()->after('overtime_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['schedule_id', 'shift_id', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'permission_minutes']);
        });
    }
};
