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
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->string('code')->nullable()->after('org_structure_id');
            $table->string('description')->nullable()->after('name');
            $table->text('qualification')->nullable()->after('description');
            $table->integer('job_evaluation_score')->nullable()->after('qualification');
            $table->integer('workload_score')->nullable()->after('job_evaluation_score');
            $table->integer('planned_manpower')->nullable()->after('workload_score');
            $table->integer('actual_manpower')->nullable()->after('planned_manpower');
            $table->foreignUlid('pangkat_id')->nullable()->constrained('m_pangkats')->nullOnDelete()->after('parent_id');
            $table->foreignUlid('golongan_id')->nullable()->constrained('m_golongans')->nullOnDelete()->after('pangkat_id');
            $table->foreignUlid('kelas_id')->nullable()->constrained('m_kelas')->nullOnDelete()->after('golongan_id');

            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_job_positions', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'description',
                'qualification',
                'job_evaluation_score',
                'workload_score',
                'planned_manpower',
                'actual_manpower',
                'pangkat_id',
                'golongan_id',
                'kelas_id',
                'deleted_at'
            ]);
        });
    }
};
