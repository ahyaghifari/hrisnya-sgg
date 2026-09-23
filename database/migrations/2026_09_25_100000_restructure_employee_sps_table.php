<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restrukturisasi SP (instruksi user 2026-09-25, lihat diagram ER): surat peringatan dipecah jadi
 * 3 tabel - employee_sps (surat inti), sp_items (poin pelanggaran, N per surat, tabel terpisah
 * lihat migration berikutnya), sp_reports (Berita Acara, 1:1, tabel terpisah).
 *
 * - `nomor`: nomor surat, baru.
 * - `reason_id`/`reason_note`: DIHAPUS, diganti total oleh sp_items (poin bebas per baris,
 *   bukan pilih dari master sp_reasons lagi).
 * - `employee_response`/`response_date`: DIHAPUS, diganti sp_reports.subject_clarification
 *   (klarifikasi diambil SAAT investigasi oleh HC-GA, sebelum SP terbit - bukan self-service
 *   karyawan lagi setelah SP terbit).
 * - `issued_by`+`issued_at`: `issued_by` diganti nama jadi `created_by` (field sama, cuma
 *   rename), `issued_at` dihapus - pakai `created_at` bawaan Eloquent (SP selalu dibuat &
 *   "diterbitkan" dalam satu langkah, tidak ada state draft terpisah).
 * - `knows_by`: BARU - pimpinan/HC Supervisor/Manager yang "mengetahui" surat ini (sesuai
 *   flowchart approval HC-GA), nullable karena approval-nya offline/verbal, bukan approval flow
 *   di sistem.
 *
 * sp_reasons (master alasan) SENGAJA TIDAK dihapus tabelnya - cuma tidak dipakai lagi oleh
 * employee_sps. Tetap dipakai sp_recommendations (jalur rekomendasi dari Coaching, saat ini
 * dinonaktifkan lewat SpService::recommendationCreationEnabled(), tapi infrastrukturnya
 * dibiarkan utuh untuk kemungkinan dipakai lagi nanti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropForeign(['reason_id']);
            $table->dropColumn(['reason_id', 'reason_note', 'employee_response', 'response_date']);
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->renameColumn('issued_by', 'created_by');
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropColumn('issued_at');
            $table->string('nomor')->nullable()->after('id');
            $table->foreignUlid('knows_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('knows_by');
            $table->dropColumn('nomor');
            $table->timestamp('issued_at')->nullable();
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->renameColumn('created_by', 'issued_by');
        });

        Schema::table('employee_sps', function (Blueprint $table) {
            $table->foreignUlid('reason_id')->nullable()->constrained('sp_reasons')->restrictOnDelete();
            $table->text('reason_note')->nullable();
            $table->text('employee_response')->nullable();
            $table->timestamp('response_date')->nullable();
        });
    }
};
