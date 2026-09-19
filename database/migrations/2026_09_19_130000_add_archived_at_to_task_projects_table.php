<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_projects', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_projects', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
