<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki orientasi: user_orientations = INDUK, orientation_submissions = anak (via
 * orientation_submissions.user_orientation_id). Kolom `source_submission_id` di sini bikin
 * FK bolak-balik (induk nunjuk anak) — dibuang. Rantai perpanjangan tetap lewat
 * `previous_orientation_id` (self-chain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_orientations', function (Blueprint $table) {
            $table->dropForeign(['source_submission_id']);
            $table->dropColumn('source_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_orientations', function (Blueprint $table) {
            $table->foreignUlid('source_submission_id')->nullable()->after('source')
                ->constrained('orientation_submissions')->nullOnDelete();
        });
    }
};
