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
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_golongans', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_kelas', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_employee_types', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_religions', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_banks', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('name')->constrained('m_companies')->nullOnDelete();
        });

        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->text('file_workload_analysis')->nullable()->after('workload_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('m_golongans', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('m_kelas', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('m_employee_types', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('m_religions', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('m_banks', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });

        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->dropColumn('file_workload_analysis');
        });
    }
};
