<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susulan dari rename tabel+model TrainerAssesmentsResults -> TrainerAssessmentResults (gap #10).
 * Rename di tempat (bukan delete+create) supaya role_has_permissions yang sudah di-assign tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'like', '%:TrainerAssesmentsResults')
            ->update(['name' => DB::raw("REPLACE(name, ':TrainerAssesmentsResults', ':TrainerAssessmentResults')")]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'like', '%:TrainerAssessmentResults')
            ->update(['name' => DB::raw("REPLACE(name, ':TrainerAssessmentResults', ':TrainerAssesmentsResults')")]);
    }
};
