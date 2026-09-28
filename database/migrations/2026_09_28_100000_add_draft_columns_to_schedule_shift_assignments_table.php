<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai draft (pengajuan Kepala Ruangan yang belum disetujui) hidup BERDAMPINGAN dengan nilai
     * approved di baris yang sama (bukan tabel/baris terpisah) - instruksi user 2026-09-28: presensi
     * (ScheduleResolver default) tetap baca shift_type_id/off_day (approved, TIDAK berubah sama
     * sekali), sementara kartu "Presensi Hari Ini" & grid Detail Penjadwalan bisa pilih baca draft_*
     * kalau ADA (draft_request_id terisi) buat preview. Lihat App\Services\Shift\ScheduleRosterChangeService.
     */
    public function up(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->foreignUlid('draft_request_id')->nullable()->after('schedule_status')
                ->constrained('schedule_roster_change_requests')->nullOnDelete();
            $table->foreignUlid('draft_shift_type_id')->nullable()->after('draft_request_id')
                ->constrained('schedule_shift_types')->nullOnDelete();
            $table->boolean('draft_off_day')->default(false)->after('draft_shift_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('draft_request_id');
            $table->dropConstrainedForeignId('draft_shift_type_id');
            $table->dropColumn('draft_off_day');
        });
    }
};
