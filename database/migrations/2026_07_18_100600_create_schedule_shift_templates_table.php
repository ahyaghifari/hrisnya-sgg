<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pola rotasi tipe SHIFT — pengganti `shift_templates`. Tambah `schedule_id` (dulu cuma
     * `company_id`) supaya 1 template terikat ke 1 grup jadwal tertentu. Tabel BARU, diisi
     * lewat COPY dari `shift_templates` (ID dipertahankan sama). Tabel lama TIDAK didrop.
     */
    public function up(): void
    {
        Schema::create('schedule_shift_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('cycle_length');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_templates');
    }
};
