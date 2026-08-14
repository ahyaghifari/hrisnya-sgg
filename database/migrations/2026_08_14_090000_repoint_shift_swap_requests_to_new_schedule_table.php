<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shift_swap_requests.req_schedule_id/target_schedule_id masih FK ke shift_schedule_assignments
 * (tabel roster LAMA, sudah tidak ada penulis aktif sejak cutover ke schedule_shift_assignments -
 * lihat App\Models\Shift\ScheduleShiftAssignment). Fitur Tukar Shift baru mau dipasang UI-nya dan
 * harus merujuk roster yang aktif dipakai perhitungan absensi (tabel baru), bukan tabel lama.
 *
 * TIDAK bisa sekadar ganti target FK ke schedule_shift_assignments - tabel ini SUDAH ADA 5 baris
 * data asli (4 approved, 1 in_review, dicek langsung ke DB 2026-08-14) yang req_schedule_id/
 * target_schedule_id-nya valid di tabel LAMA tapi tidak match ID manapun di tabel BARU (data baru
 * dibuat independen lewat ScheduleShiftRosterTab, bukan hasil migrasi ID-preserved) - constraint
 * FK ketat ke tabel baru akan ditolak DB karena baris lama itu jadi orphan. Keputusan (instruksi
 * user 2026-08-14): LEPAS FK constraint-nya sama sekali, jangan diganti jadi FK baru - riwayat 5
 * baris lama dibiarkan utuh apa adanya, kolomnya tetap ada (dipakai app-level, divalidasi lewat
 * findOrFail() di ShiftSwapRequestService/ApprovalFlowService), cuma tidak ada penjagaan integritas
 * di level database lagi. Data baru (dari UI yang mau dipasang) akan diisi ID dari
 * schedule_shift_assignments oleh kode aplikasi, cuma tidak dipaksa oleh constraint DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['req_schedule_id']);
            $table->dropForeign(['target_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->foreign('req_schedule_id')
                ->references('id')
                ->on('shift_schedule_assignments')
                ->cascadeOnDelete();

            $table->foreign('target_schedule_id')
                ->references('id')
                ->on('shift_schedule_assignments')
                ->nullOnDelete();
        });
    }
};
