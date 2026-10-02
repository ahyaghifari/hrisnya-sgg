<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_req_leave_details', function (Blueprint $table) {
            $table->text('substitute_task')->nullable()->after('total_days');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_req_leave_details', function (Blueprint $table) {
            $table->dropColumn('substitute_task');
        });
    }
};
