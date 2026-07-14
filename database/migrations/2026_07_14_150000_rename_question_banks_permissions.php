<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan dari rename model QuestionBanks/QuestionOptions -> LmsQuestionBanks/LmsQuestionOptions
 * (gap #15, knowledge/markdown/analisis-lms.md §12). Permission Shield berbasis nama model — rename
 * di tempat (bukan delete+create) supaya role_has_permissions yang sudah di-assign tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'like', '%:QuestionBanks')
            ->update(['name' => DB::raw("REPLACE(name, ':QuestionBanks', ':LmsQuestionBanks')")]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'like', '%:LmsQuestionBanks')
            ->update(['name' => DB::raw("REPLACE(name, ':LmsQuestionBanks', ':QuestionBanks')")]);
    }
};
