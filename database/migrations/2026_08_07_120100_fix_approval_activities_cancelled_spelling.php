<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konsistensi approval engine: approval_snapshots.status pakai 'cancelled' (2 L),
 * approval_activities.status pakai 'canceled' (1 L) - beda ejaan enum untuk kejadian yang sama.
 * Samakan ke 'cancelled' di approval_activities juga.
 *
 * Sebelumnya migration ini raw SQL MySQL (ALTER...MODIFY) yang di-guard skip-di-SQLite - TERNYATA
 * SALAH ASUMSI: SQLite JUGA menegakkan CHECK constraint dari definisi enum() Blueprint asli
 * (bukan "TEXT biasa" seperti dikira), jadi skip itu bikin baris status='cancelled' ditolak CHECK
 * constraint di test suite (ketahuan dari test AttendanceRequestCancelTest gagal). Diganti pakai
 * Blueprint::enum()->change() bawaan Laravel (via doctrine/dbal, sudah terpasang) - portable ke
 * MySQL & SQLite sekaligus, tidak perlu raw SQL/guard driver lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_activities', function (Blueprint $table) {
            $table->enum('status', ['pending', 'created', 'approved', 'rejected', 'skipped', 'canceled', 'cancelled'])
                ->default('pending')
                ->change();
        });

        DB::table('approval_activities')->where('status', 'canceled')->update(['status' => 'cancelled']);

        Schema::table('approval_activities', function (Blueprint $table) {
            $table->enum('status', ['pending', 'created', 'approved', 'rejected', 'skipped', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('approval_activities', function (Blueprint $table) {
            $table->enum('status', ['pending', 'created', 'approved', 'rejected', 'skipped', 'canceled', 'cancelled'])
                ->default('pending')
                ->change();
        });

        DB::table('approval_activities')->where('status', 'cancelled')->update(['status' => 'canceled']);

        Schema::table('approval_activities', function (Blueprint $table) {
            $table->enum('status', ['pending', 'created', 'approved', 'rejected', 'skipped', 'canceled'])
                ->default('pending')
                ->change();
        });
    }
};
