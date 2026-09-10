<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Level "atasan pengaju" untuk step approver_type = 'supervisor'. 1 = atasan langsung
     * (parent jabatan pengaju), 2 = atasan-nya atasan, dst. NULL untuk step non-supervisor.
     */
    public function up(): void
    {
        Schema::table('m_approval_flow_steps', function (Blueprint $table) {
            $table->unsignedTinyInteger('supervisor_level')->nullable()->after('approver_ref_id');
        });
    }

    public function down(): void
    {
        Schema::table('m_approval_flow_steps', function (Blueprint $table) {
            $table->dropColumn('supervisor_level');
        });
    }
};

