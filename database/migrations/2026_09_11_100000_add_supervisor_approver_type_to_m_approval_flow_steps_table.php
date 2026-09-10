<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah sumber approver 'supervisor' (atasan langsung pengaju) di step alur persetujuan.
     * Di-resolve jadi job_position (parent jabatan pengaju) saat snapshot dibuat, jadi
     * approver_ref_id-nya kosong di master step → kolom dibuat nullable.
     */
    public function up(): void
    {
        Schema::table('m_approval_flow_steps', function (Blueprint $table) {
            $table->string('approver_type', 20)->default('role')->change();
            $table->string('approver_ref_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('m_approval_flow_steps', function (Blueprint $table) {
            $table->enum('approver_type', ['role', 'user', 'job_position'])->default('role')->change();
            $table->string('approver_ref_id')->nullable(false)->change();
        });
    }
};
