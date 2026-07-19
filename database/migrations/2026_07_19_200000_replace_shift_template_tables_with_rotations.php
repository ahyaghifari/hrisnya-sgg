<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti 3 tabel template (schedule_shift_templates/_days/_assignments, dibuat Tahap 1
     * restrukturisasi 2026-07-18, BELUM ADA data/kode yang pakai) jadi 1 tabel saja atas
     * permintaan user — tanpa entity "Template" terpisah (tanpa nama/cycle_length tersimpan).
     * cycle_length dihitung dari COUNT baris `day` milik 1 user di 1 schedule, bukan kolom.
     */
    public function up(): void
    {
        Schema::dropIfExists('schedule_shift_template_assignments');
        Schema::dropIfExists('schedule_shift_template_days');
        Schema::dropIfExists('schedule_shift_templates');

        Schema::create('schedule_shift_rotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('shift_type_id')->nullable()->constrained('schedule_shift_types')->cascadeOnDelete(); // null = off_day
            $table->boolean('off_day')->default(false);
            $table->unsignedSmallInteger('day'); // posisi ke-berapa dalam siklus, 1-based
            $table->date('anchor_date'); // tanggal kalender = day 1 untuk user ini, permanen (tidak berubah tiap generate)
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['schedule_id', 'user_id', 'day']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('use_template')->default(false)->after('schedule_type');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('use_template');
        });

        Schema::dropIfExists('schedule_shift_rotations');

        Schema::create('schedule_shift_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('cycle_length');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedule_shift_template_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_shift_template_id')->constrained('schedule_shift_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->foreignUlid('shift_type_id')->nullable()->constrained('schedule_shift_types')->cascadeOnDelete();
            $table->boolean('is_off_day')->default(false);
            $table->timestamps();
        });

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
};
