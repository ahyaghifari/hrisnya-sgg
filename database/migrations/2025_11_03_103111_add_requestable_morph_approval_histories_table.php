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
        Schema::table('approval_histories', function (Blueprint $table) {
            $table->dropForeign(['attendance_request_id']);
            $table->dropColumn('attendance_request_id');
            $table->ulidMorphs('requestable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_histories', function (Blueprint $table) {
            $table->foreignUlid('attendance_request_id')->nullable()->constrained('attendance_requests')->nullOnDelete();
            $table->dropMorphs('requestable');
        });
    }
};
