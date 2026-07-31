<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perubahan roster HARIAN (schedule_shift_assignments) - beda dari
     * schedule_change_logs (itu snapshot metadata schedule, bukan roster per-sel). 1 baris = 1
     * sel (user_id+date) yang berubah, dari mana (manual/import lewat setCell(), generate lewat
     * generateFromTemplate(), reset lewat resetPeriod()). old_value/new_value nyimpen
     * shift_type_id, 'off', atau null (kosong) - sama representasi kayak $draft di
     * ScheduleShiftRosterTab, diresolve ke kode shift pas ditampilkan (bukan disimpan sebagai
     * kode, karena kode bisa berubah/dihapus belakangan).
     */
    public function up(): void
    {
        Schema::create('schedule_shift_roster_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            // string (bukan ulid()) - nilainya bisa shift_type_id (ulid) ATAU literal 'off', jadi
            // bukan murni FK.
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('source');
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_roster_logs');
    }
};
