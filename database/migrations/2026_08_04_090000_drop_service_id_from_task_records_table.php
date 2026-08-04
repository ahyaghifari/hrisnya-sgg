<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur "Layanan" (m_task_services) tidak pernah kepake di UI — cuma dipakai
     * createFormalTask() di TaskService yang juga tidak pernah dipanggil dari mana pun.
     * Drop kolom dulu di sini sebelum tabel m_task_services didrop di migration berikutnya.
     */
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('service_id')->nullable()->after('department_id')->constrained('m_task_services')->cascadeOnDelete();
        });
    }
};
