<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * TaskReview (reviewer manual + rating) dan TaskReport (laporan mingguan) dihapus - keduanya
 * cuma dipakai app mobile lama (/api/tasks/*) yang sudah ditinggal, dikonfirmasi user
 * (2026-08-02). Alur status task disederhanakan: IN_PROGRESS -> COMPLETE langsung, tanpa tahap
 * REVIEW/approval. Portal Livewire (/tugas) sekarang jadi satu-satunya jalur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('task_reviews');
        Schema::dropIfExists('task_reports');
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - struktur lama (kolom rating/status/decided_at di
        // task_reviews, week_number/quote di task_reports) sudah tidak relevan dengan flow baru.
    }
};
