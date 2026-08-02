<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 5 kolom ini cuma diisi/dibaca lewat app mobile lama (/api/tasks/*) yang sudah ditinggal -
 * portal Livewire (/tugas) tidak pernah menyentuhnya sama sekali. Dikonfirmasi user (2026-08-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sla_reviewer_id');
            $table->dropColumn(['risk_level', 'collaboration_note', 'root_cause', 'solution']);
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('sla_reviewer_id')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->string('risk_level')->nullable();
            $table->text('collaboration_note')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('solution')->nullable();
        });
    }
};
