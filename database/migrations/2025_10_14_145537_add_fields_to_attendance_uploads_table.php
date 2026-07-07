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
        Schema::table('attendance_uploads', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_path');
            $table->enum('status', ['pending', 'processing', 'processed', 'processed_with_errors', 'failed'])->default('pending')->after('original_name');
            $table->integer('processed_count')->default(0)->after('record_count');
            $table->integer('error_count')->default(0)->after('processed_count');
            $table->json('error_log')->nullable()->after('error_count');
            $table->timestamp('processed_at')->nullable()->after('error_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_uploads', function (Blueprint $table) {
            $table->dropColumn([
                'original_name',
                'status',
                'processed_count',
                'error_count',
                'error_log',
                'processed_at'
            ]);
        });
    }
};
