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
        Schema::create('attendance_req_permission_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->constrained('attendance_requests')->cascadeOnDelete();
            $table->foreignUlid('permission_type_id')->nullable()->constrained('m_permission_types')->nullOnDelete();
            $table->string('permission_type')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('purpose')->nullable();
            $table->string('location')->nullable();
            $table->foreignUlid('substitute_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('substitute_user_name')->nullable();
            $table->text('substitute_task')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_req_permission_details');
    }
};
