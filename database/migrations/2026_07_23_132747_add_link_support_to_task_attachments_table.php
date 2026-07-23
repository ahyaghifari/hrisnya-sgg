<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * task_attachments sebelumnya cuma file_path (upload doang, dipakai mobile API) — portal
     * mau "Lampiran" campur file upload DAN link eksternal jadi 1 daftar. `type` bedain
     * keduanya, `url` khusus link, `file_name` label tampilan (file upload sebelumnya tidak
     * nyimpen nama asli sama sekali).
     */
    public function up(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->string('type')->default('file')->after('task_id'); // file | link
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('url')->nullable()->after('file_name');
            $table->foreignUlid('uploaded_by')->nullable()->after('url')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn(['type', 'file_name', 'url', 'uploaded_by']);
        });
    }
};
