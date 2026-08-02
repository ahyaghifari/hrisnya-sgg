<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('list_id')->nullable()->after('project_id')
                ->constrained('task_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('list_id');
        });
    }
};
