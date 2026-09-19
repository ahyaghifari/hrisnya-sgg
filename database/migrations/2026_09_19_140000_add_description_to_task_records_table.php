<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            // `requirement` = kebutuhan (judul singkat tugas), `description` = uraian/penjelasan detailnya.
            $table->text('description')->nullable()->after('requirement');
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
