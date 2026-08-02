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
        Schema::table('task_records', function (Blueprint $table) {
            // "Butuh Approval Saya" (opt-in per task oleh pembuat, 2026-08-02) - kalau true, task
            // wajib mampir status REVIEW sebelum COMPLETE (lihat TaskStatus::BOARD_ORDER vs
            // BOARD_ORDER_SELF_ASSIGNED, dipakai juga di TaskService::moveStatus()). Default false
            // - beda dari perilaku lama yang selalu mewajibkan REVIEW buat semua task assigned.
            $table->boolean('needs_review')->default(false)->after('is_self_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn('needs_review');
        });
    }
};
