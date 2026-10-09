<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lampiran yang dikirim lewat komentar: tampil di komentar sekaligus di daftar Lampiran (ditandai "dari komentar"). */
    public function up(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->foreignUlid('comment_id')->nullable()->after('uploaded_by')->constrained('task_comments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comment_id');
        });
    }
};
