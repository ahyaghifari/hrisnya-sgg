<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keanggotaan rotasi PERMANEN per user (§2.10 planning) — `anchor_date` tidak berubah walau
     * digenerate berkali-kali per periode (bulan), supaya rotasi nyambung lintas bulan tanpa
     * reset. Genuinely baru, tidak ada tabel lama yang setara (sistem lama minta start_date
     * manual tiap generate, lihat `GenerateScheduleFromTemplate.php`). Dibuat kosong.
     */
    public function up(): void
    {
        Schema::create('schedule_shift_template_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('shift_template_id')->constrained('schedule_shift_templates')->cascadeOnDelete();
            $table->date('anchor_date');
            $table->date('effective_until')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_template_assignments');
    }
};
