<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            // Keterangan singkat lampiran (mis. "Foto lokasi", "Draft revisi 2") - opsional.
            $table->string('description', 255)->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
