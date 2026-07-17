<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pengganti `shift_template_days`. Tabel BARU, diisi lewat COPY dari tabel lama. */
    public function up(): void
    {
        Schema::create('schedule_shift_template_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_shift_template_id')->constrained('schedule_shift_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->foreignUlid('shift_type_id')->nullable()->constrained('schedule_shift_types')->cascadeOnDelete();
            $table->boolean('is_off_day')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_template_days');
    }
};
