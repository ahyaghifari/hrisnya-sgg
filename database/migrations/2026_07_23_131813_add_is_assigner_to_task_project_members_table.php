<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag per member (bukan role ke-3) — owner project bisa kasih izin "assign/bikin task
     * buat orang lain" ke member tertentu tanpa perlu menjadikannya owner (yang juga bisa
     * kelola anggota). Owner otomatis dianggap boleh assign juga (dicek di kode, bukan di sini).
     */
    public function up(): void
    {
        Schema::table('task_project_members', function (Blueprint $table) {
            $table->boolean('is_assigner')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('task_project_members', function (Blueprint $table) {
            $table->dropColumn('is_assigner');
        });
    }
};
