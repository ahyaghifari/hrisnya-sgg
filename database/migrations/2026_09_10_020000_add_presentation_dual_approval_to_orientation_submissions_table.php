<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal presentasi kegiatan orientasi butuh 2 persetujuan terpisah (urutan bebas): HR/pengelola
 * orientasi + pemegang jabatan pendukung (Setting orientation_presentation_approver_job_position_id
 * per perusahaan). `presentation_confirmed_at` = slot yang dikunci (diisi pihak pertama yang
 * bertindak). Fully approved = confirmed_at + hr_at + support_at semua terisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dateTime('presentation_hr_at')->nullable()->after('presentation_decided_at');
            $table->foreignUlid('presentation_hr_by')->nullable()->after('presentation_hr_at')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('presentation_support_at')->nullable()->after('presentation_hr_by');
            $table->foreignUlid('presentation_support_by')->nullable()->after('presentation_support_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presentation_hr_by');
            $table->dropConstrainedForeignId('presentation_support_by');
            $table->dropColumn(['presentation_hr_at', 'presentation_support_at']);
        });
    }
};
