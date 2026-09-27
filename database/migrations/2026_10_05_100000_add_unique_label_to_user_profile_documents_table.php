<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `label` jadi tipe tetap (App\Enum\UserProfileDocumentType) - upload ulang tipe yang sama
 * mengganti (upsert), bukan menambah baris baru (instruksi user 2026-10-05). Unique constraint
 * di DB jadi jaring pengaman terhadap race condition, bukan cuma dijaga di kode.
 *
 * Baris lama yang labelnya sama persis utk 1 user (duplikat dari era lampiran-bebas) dihapus
 * sisakan yang terbaru, supaya unique index tidak gagal dipasang.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_profile_documents')
            ->select('user_id', 'label')
            ->groupBy('user_id', 'label')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function ($row) {
                $ids = DB::table('user_profile_documents')
                    ->where('user_id', $row->user_id)
                    ->where('label', $row->label)
                    ->orderByDesc('created_at')
                    ->pluck('id');

                DB::table('user_profile_documents')->whereIn('id', $ids->skip(1))->delete();
            });

        Schema::table('user_profile_documents', function (Blueprint $table) {
            $table->unique(['user_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::table('user_profile_documents', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'label']);
        });
    }
};
