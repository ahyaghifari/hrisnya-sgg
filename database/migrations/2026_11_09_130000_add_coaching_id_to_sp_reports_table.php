<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber Coaching dicatat di Berita Acara (bukan di SP): coaching sebelumnya sudah dilakukan sebelum
 * klarifikasi. Data lama dipindah dari employee_sps.source_id (source_type=coaching) lewat sp_reports.sp_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->foreignUlid('coaching_id')->nullable()->after('invitation_id')->constrained('coachings')->nullOnDelete();
        });

        DB::table('employee_sps')
            ->where('source_type', 'coaching')
            ->whereNotNull('source_id')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('coachings')->whereColumn('coachings.id', 'employee_sps.source_id'))
            ->get(['id', 'source_id'])
            ->each(fn ($sp) => DB::table('sp_reports')->where('sp_id', $sp->id)->update(['coaching_id' => $sp->source_id]));
    }
    public function down(): void
    {
        Schema::table('sp_reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('coaching_id'));
    }
};