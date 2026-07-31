<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_permission_types', function (Blueprint $table) {
            $table->text('description')->nullable()->after('default_days');
        });
    }

    public function down(): void
    {
        Schema::table('m_permission_types', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
