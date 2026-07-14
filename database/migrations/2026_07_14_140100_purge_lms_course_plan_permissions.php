<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan dari 2026_07_14_140000_drop_lms_course_plan_feature — permission Shield
 * (format Verb:Subject, auto-generate dari Resource yang terdaftar) untuk
 * LmsCoursePlans/LmsCoursePlanItems jadi permission hantu begitu Resource-nya dihapus.
 * role_has_permissions/model_has_permissions ikut kehapus lewat FK cascadeOnDelete bawaan
 * spatie/laravel-permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'like', '%:LmsCoursePlanItems')
            ->orWhere('name', 'like', '%:LmsCoursePlans')
            ->delete();
    }

    public function down(): void
    {
        // Permission Shield di-generate ulang via `php artisan shield:generate` kalau
        // Resource-nya suatu saat dikembalikan — tidak ada data bermakna untuk dipulihkan di sini.
    }
};
