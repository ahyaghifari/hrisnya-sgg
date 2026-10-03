<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            // Tugas berulang (Board): daily/weekly/monthly/yearly. Saat dicentang selesai, dibuat salinan baru dengan tenggat periode berikutnya.
            $table->string('recurrence', 10)->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropColumn('recurrence');
        });
    }
};
