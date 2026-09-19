<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_lists', function (Blueprint $table) {
            // Muat key preset warna ("gold-dark", dst) selain hex 7 karakter.
            $table->string('color', 20)->nullable()->change();
            $table->boolean('is_review')->default(false)->after('is_final');
        });
    }

    public function down(): void
    {
        Schema::table('task_lists', function (Blueprint $table) {
            $table->dropColumn('is_review');
            $table->string('color', 7)->nullable()->change();
        });
    }
};
