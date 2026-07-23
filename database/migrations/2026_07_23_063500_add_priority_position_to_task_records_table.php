<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifikasi Task Request + Kanban — Kanban dilebur jadi tampilan (List/Board) di atas
     * task_records, bukan entitas board terpisah. priority buat label prioritas ala kanban,
     * position buat urutan manual di kolom board. service_id/department_id jadi nullable
     * karena cuma wajib untuk task formal (butuh approval) — task casual tidak butuh
     * keduanya, validasi wajib-atau-tidak dipindah ke service layer.
     */
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('risk_level');
            $table->unsignedInteger('position')->default(0)->after('priority');
        });

        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('department_id')->nullable()->change();
            $table->foreignUlid('service_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn(['priority', 'position']);
        });

        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('department_id')->nullable(false)->change();
            $table->foreignUlid('service_id')->nullable(false)->change();
        });
    }
};
