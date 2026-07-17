<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roster harian tipe SHIFT — pengganti `shift_schedule_assignments`. Tahap 1: tabel BARU,
     * diisi lewat COPY data dari `shift_schedule_assignments` (ID dipertahankan sama, biar
     * `shift_swap_requests.req_schedule_id`/`target_schedule_id` gampang di-repoint di Tahap 2
     * tanpa remap ID). Tabel lama TIDAK didrop sesi ini — Filament (`ScheduleShiftsTable`, dst)
     * masih baca tabel lama sampai Tahap 2.
     */
    public function up(): void
    {
        Schema::create('schedule_shift_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('shift_type_id')->constrained('schedule_shift_types')->cascadeOnDelete();
            $table->date('date');
            $table->string('notes')->nullable();
            $table->string('schedule_status')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_assignments');
    }
};
