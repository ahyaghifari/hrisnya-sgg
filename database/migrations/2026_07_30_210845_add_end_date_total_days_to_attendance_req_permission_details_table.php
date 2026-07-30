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
        Schema::table('attendance_req_permission_details', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('date');
            $table->decimal('total_days', 5, 2)->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_req_permission_details', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'total_days']);
        });
    }
};
