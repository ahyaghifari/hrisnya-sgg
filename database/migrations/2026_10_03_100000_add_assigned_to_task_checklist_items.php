<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Item checklist boleh ditugaskan ke SATU pelaksana; kalau terisi, cuma dia yang bisa mencentang.
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->foreignUlid('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_checklist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
