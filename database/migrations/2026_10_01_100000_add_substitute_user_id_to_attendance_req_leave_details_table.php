<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_req_leave_details', function (Blueprint $table) {
            $table->foreignUlid('substitute_user_id')->nullable()->after('total_days')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_req_leave_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_user_id');
        });
    }
};
