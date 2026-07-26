<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * lms_course_schedules — relasi & delete-cascade-nya ada di LmsCourses, tapi tidak ada
     * satupun jalur kode yang pernah membuat baris di tabel ini (selalu kosong). Harus jalan
     * SETELAH migration consolidate_lms_user_histories (yang men-drop lms_user_histories —
     * kolom schedule_id di tabel itu FK ke sini, jadi tabel ini harus dihapus belakangan).
     */
    public function up(): void
    {
        Schema::dropIfExists('lms_course_schedules');
    }

    public function down(): void
    {
        //
    }
};
