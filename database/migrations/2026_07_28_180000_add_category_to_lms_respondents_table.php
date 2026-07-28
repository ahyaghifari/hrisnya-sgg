<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Respondent dulu 1 baris dipakai bareng buat kuis DAN umpan balik dalam 1 course (unique
     * course_id+user_id). Ternyata itu bikin constraint violation begitu jalur publik NIP-nya
     * kebetulan cocok ke user yang sama tapi submit 2 kali (kuis lalu umpan balik, atau
     * sebaliknya) — resolveForPublic() sengaja TIDAK dedup (§3.1), jadi row kedua nabrak unique
     * lama. Fix: pisah per kategori (kuis/umpan_balik), unique jadi (course_id, user_id, category)
     * — row lama yang kepakai di KEDUANYA (2 baris ditemukan) di-duplikasi jadi 2 baris terpisah.
     */
    public function up(): void
    {
        Schema::table('lms_respondents', function (Blueprint $table) {
            $table->string('category')->nullable()->after('unit');
        });

        // Constraint lama harus turun DULUAN — baris duplikat di bawah sengaja punya
        // (course_id, user_id) yang sama dengan baris aslinya (bedanya cuma category),
        // jadi kalau constraint lama masih aktif, insert-nya sendiri yang nabrak. Index
        // course_id/user_id biasa ditambah dulu supaya FK constraint-nya tetap ada index
        // pendukung setelah unique index gabungan yang lama didrop (MySQL menolak drop
        // index yang masih dipakai FK tanpa index pengganti).
        Schema::table('lms_respondents', function (Blueprint $table) {
            $table->index('course_id');
            $table->index('user_id');
        });

        Schema::table('lms_respondents', function (Blueprint $table) {
            $table->dropUnique('lms_respondents_course_id_user_id_unique');
        });

        $usedInQuiz = DB::table('lms_quiz_results')->pluck('respondent_id')
            ->merge(DB::table('lms_quiz_answers')->pluck('respondent_id'))
            ->unique();
        $usedInFeedback = DB::table('lms_course_feedbacks')->pluck('respondent_id')->unique();

        $usedInBoth = $usedInQuiz->intersect($usedInFeedback);

        foreach ($usedInBoth as $respondentId) {
            $original = DB::table('lms_respondents')->where('id', $respondentId)->first();

            if (! $original) {
                continue;
            }

            $duplicateId = (string) Str::ulid();

            DB::table('lms_respondents')->insert([
                'id' => $duplicateId,
                'course_id' => $original->course_id,
                'user_id' => $original->user_id,
                'name' => $original->name,
                'nip' => $original->nip,
                'company_id' => $original->company_id,
                'unit' => $original->unit,
                'category' => 'umpan_balik',
                'created_at' => $original->created_at,
                'updated_at' => now(),
            ]);

            DB::table('lms_course_feedbacks')
                ->where('respondent_id', $respondentId)
                ->update(['respondent_id' => $duplicateId]);
        }

        DB::table('lms_respondents')->whereIn('id', $usedInQuiz)->update(['category' => 'kuis']);
        DB::table('lms_respondents')->whereNull('category')->whereIn('id', $usedInFeedback)->update(['category' => 'umpan_balik']);
        // Baris yatim (tidak pernah dipakai kuis/feedback manapun) tidak masalah dikasih apa
        // saja — default 'kuis' supaya kolomnya tetap bisa di-NOT NULL-kan di bawah.
        DB::table('lms_respondents')->whereNull('category')->update(['category' => 'kuis']);

        Schema::table('lms_respondents', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->unique(['course_id', 'user_id', 'category'], 'lms_respondents_course_user_category_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lms_respondents', function (Blueprint $table) {
            $table->dropUnique('lms_respondents_course_user_category_unique');
            $table->unique(['course_id', 'user_id'], 'lms_respondents_course_id_user_id_unique');
            $table->dropIndex(['course_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('category');
        });
    }
};
