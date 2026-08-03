<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->foreignUlid('created_by')->nullable()->after('task_id')->constrained('users')->nullOnDelete();
        });

        // Backfill item lama: dianggap dibuat pembuat task-nya (satu-satunya sumber checklist
        // sebelum fitur "tambah item ke task yang sudah ada" ada).
        DB::statement('
            UPDATE task_checklist_items
            SET created_by = (
                SELECT task_records.created_by FROM task_records WHERE task_records.id = task_checklist_items.task_id
            )
            WHERE task_checklist_items.created_by IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
