<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus total fitur "Rencana Pelatihan" (Course Plan) — keputusan user 2026-07-14, lihat
 * planning/2026-07-14-perbaikan-lms-dan-akses-portal.md. Alasan: realized_course_id (penghubung
 * rencana yang disetujui ke course asli) tidak pernah diisi kode manapun, jadi rencana &
 * pelaksanaan course sudah lama terputus di praktiknya — Course sendiri sudah punya alur
 * draft/publish yang cukup untuk kebutuhan "belum siap".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Approval snapshot untuk module 'lms_course_request' — activities/histories ikut
        // cascadeOnDelete lewat FK snapshot_id.
        DB::table('approval_snapshots')->where('module_type', 'lms_course_request')->delete();

        // Master flow yang dikonfigurasi khusus untuk module ini jadi tidak berguna lagi.
        DB::table('m_approval_flows')->where('module', 'lms_course_request')->delete();

        Schema::dropIfExists('lms_course_plan_schedules');
        Schema::dropIfExists('lms_course_plan_items');
        Schema::dropIfExists('lms_course_plans');
    }

    public function down(): void
    {
        // Data (2 rencana, 3 item usulan, flow & snapshot terkait) sudah dihapus permanen di
        // up() — down() cuma mengembalikan skema tabel kosong, bukan data-nya.
        Schema::create('lms_course_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title')->nullable();
            $table->integer('year')->nullable();
            $table->enum('type_nakes', ['nakes', 'non-nakes', 'all'])->default('non-nakes');
            $table->enum('status', ['draft', 'submitted', 'approved', 'archived'])->default('draft');
            $table->integer('total_courses')->default(0);
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lms_course_plan_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->nullable()->constrained('lms_course_plans')->cascadeOnDelete();
            $table->foreignUlid('course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->enum('source_type', ['employee', 'system'])->nullable();
            $table->string('proposed_title')->nullable();
            $table->text('proposed_description')->nullable();
            $table->foreignUlid('competency_id')->nullable()->constrained('m_competencies')->nullOnDelete();
            $table->foreignUlid('target_org_structure_id')->nullable()->constrained('m_org_structures')->nullOnDelete();
            $table->foreignUlid('target_job_position_id')->nullable()->constrained('m_job_positions')->nullOnDelete();
            $table->integer('estimated_participants')->nullable();
            $table->integer('estimated_cost')->nullable();
            $table->enum('request_status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled', 'completed'])->default('draft');
            $table->foreignUlid('realized_course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->foreignUlid('realized_schedule_id')->nullable()->constrained('lms_course_schedules')->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lms_course_plan_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_item_id')->constrained('lms_course_plan_items')->cascadeOnDelete();
            $table->enum('planned_month', ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'])->nullable();
            $table->tinyInteger('planned_week')->nullable();
            $table->date('planned_date')->nullable();
            $table->enum('status', ['planned', 'rescheduled', 'completed', 'cancelled'])->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
