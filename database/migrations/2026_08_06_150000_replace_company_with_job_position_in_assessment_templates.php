<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_templates', function (Blueprint $table) {
            $table->foreignUlid('job_position_id')->nullable()->after('type_id')
                ->constrained('m_job_positions')->cascadeOnDelete();
        });

        // Backfill: pakai jabatan pertama di company yang sama, biar scoping company tetap benar
        // setelah company_id dihapus (assessment_templates cuma punya 2 baris data uji saat migration ini dibuat).
        DB::table('assessment_templates')->select('id', 'company_id')->get()->each(function ($row) {
            $jobPositionId = DB::table('m_job_positions')->where('company_id', $row->company_id)->value('id');

            if ($jobPositionId) {
                DB::table('assessment_templates')->where('id', $row->id)->update(['job_position_id' => $jobPositionId]);
            }
        });

        Schema::table('assessment_templates', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropColumn('company_id');
        });

        Schema::table('assessment_templates', function (Blueprint $table) {
            $table->ulid('job_position_id')->nullable(false)->change();
            $table->index(['job_position_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_templates', function (Blueprint $table) {
            $table->foreignUlid('company_id')->nullable()->after('type_id')
                ->constrained('m_companies')->cascadeOnDelete();
        });

        DB::table('assessment_templates')
            ->join('m_job_positions', 'assessment_templates.job_position_id', '=', 'm_job_positions.id')
            ->update(['assessment_templates.company_id' => DB::raw('m_job_positions.company_id')]);

        Schema::table('assessment_templates', function (Blueprint $table) {
            $table->dropIndex(['job_position_id', 'is_active']);
            $table->dropForeign(['job_position_id']);
            $table->dropColumn('job_position_id');
        });

        Schema::table('assessment_templates', function (Blueprint $table) {
            $table->ulid('company_id')->nullable(false)->change();
            $table->index(['company_id', 'is_active']);
        });
    }
};
