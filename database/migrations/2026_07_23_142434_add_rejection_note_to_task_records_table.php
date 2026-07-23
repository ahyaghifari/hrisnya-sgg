<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `rejected_reason` (text, nullable) SUDAH ADA dari awal (create_tasks_table) tapi
     * tidak pernah dipakai kode manapun — dipakai ulang di sini sebagai catatan penolakan
     * review, bukan bikin kolom baru. Cuma nambah "siapa & kapan" yang belum ada. Beda dari
     * komentar publik (TaskComment) — ini cuma ditampilkan ke assigned_to (pelaksana), 1 kolom
     * ke-overwrite tiap ditolak lagi (bukan riwayat penuh).
     */
    public function up(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->foreignUlid('rejection_note_by')->nullable()->after('rejected_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('rejection_note_at')->nullable()->after('rejection_note_by');
        });
    }

    public function down(): void
    {
        Schema::table('task_records', function (Blueprint $table) {
            $table->dropForeign(['rejection_note_by']);
            $table->dropColumn(['rejection_note_by', 'rejection_note_at']);
        });
    }
};
