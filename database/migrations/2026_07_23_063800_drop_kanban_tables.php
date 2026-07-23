<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Kanban dipensiunkan — digantikan board/checklist/comment di atas task_records (lihat
 * migration create_task_boards_table dkk). Modul ini baru ~10 hari & belum ada data produksi
 * berarti, jadi drop langsung tanpa migrasi data. Urutan drop: anak dulu baru induk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('kanban_task_comments');
        Schema::dropIfExists('kanban_task_members');
        Schema::dropIfExists('kanban_task_attachments');
        Schema::dropIfExists('kanban_task_items');
        Schema::dropIfExists('kanban_tasks');
        Schema::dropIfExists('kanban_board_members');
        Schema::dropIfExists('kanban_boards');
    }

    public function down(): void
    {
        // Sengaja tidak direkonstruksi — lihat migration lama create_kanban_*_table kalau
        // benar-benar butuh rollback skema (bukan cuma data, datanya sudah hilang).
    }
};
