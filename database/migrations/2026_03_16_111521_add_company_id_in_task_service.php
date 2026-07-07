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
        Schema::table('m_task_services', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('id')->constrained('m_companies')->cascadeOnDelete();
        });

        Schema::table('trainer_assesments_results', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('id')->constrained('m_companies')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_task_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
