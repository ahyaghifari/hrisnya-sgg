<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal presentasi kegiatan orientasi. Karyawan mengusulkan tanggal + jam saat mengajukan
 * (`presentation_proposed_at`); HR/pengelola orientasi menetapkan jadwal final
 * (`presentation_confirmed_at`, boleh sama atau beda dari usulan) — wajib sebelum HR menutup
 * pengajuan (tindak lanjut). Tidak memblokir langkah penilaian oleh reviewer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dateTime('presentation_proposed_at')->nullable()->after('attachments');
            $table->dateTime('presentation_confirmed_at')->nullable()->after('presentation_proposed_at');
            $table->text('presentation_note')->nullable()->after('presentation_confirmed_at');
            $table->foreignUlid('presentation_decided_by')->nullable()->after('presentation_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('presentation_decided_at')->nullable()->after('presentation_decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presentation_decided_by');
            $table->dropColumn([
                'presentation_proposed_at',
                'presentation_confirmed_at',
                'presentation_note',
                'presentation_decided_at',
            ]);
        });
    }
};
