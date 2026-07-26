<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Urutan soal di dalam 1 kuis — dipakai portal CreateQuiz/QuizzesPage untuk mengurutkan
     * repeater soal (admin QuizForm.php belum punya UI reorder, cuma tambah).
     */
    public function up(): void
    {
        Schema::table('lms_quiz_questions', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('question_bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('lms_quiz_questions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
