<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Bank Soal (LmsQuestionBanks), Kuis (LmsQuizzes), Pertanyaan Evaluasi (LmsFeedbackQuestion),
 * Baseline Pelatihan (LmsCourseBaselines) - Resource+Policy+permission Shield sudah lama ada,
 * CRUD di semua halaman ini malah sudah pakai Policy/permission Shield string dari awal, cuma
 * mount() (ViewAny) yang masih numpang SISTEM_LMS. Diganti murni ke Policy/permission Shield.
 *
 * Sekalian dibenerin 2 celah yang ketauan:
 * - LmsFeedbackQuestionsPage::swapWithNeighbor() (reorder pertanyaan) sebelumnya cuma cek
 *   SISTEM_LMS (akses baca LMS), bukan Update:LmsFeedbackQuestion - diganti.
 * - CreateQuiz::saveNewQuestion()/openNewQuestionModal() ("+ Buat Soal Baru" di form Kuis)
 *   sebelumnya TIDAK CEK PERMISSION SAMA SEKALI, padahal nyimpen langsung ke Bank Soal -
 *   ditambah Create:LmsQuestionBanks.
 *
 * Nav link Bank Soal/Kuis/Pertanyaan Evaluasi/Baseline Pelatihan sebelumnya juga tanpa
 * gerbang sama sekali (desktop & mobile).
 *
 * Grant ViewAny ke role yang sudah punya sistem_lms - safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = ['ViewAny:LmsQuestionBanks', 'ViewAny:LmsQuizzes', 'ViewAny:LmsFeedbackQuestion', 'ViewAny:LmsCourseBaselines'];
        $permissions = Permission::whereIn('name', $names)->get();

        if ($permissions->count() !== count($names)) {
            return;
        }

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_lms'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - mencabut akses lewat rollback berisiko mengunci role
        // dari menu-menu ini tanpa sengaja.
    }
};
