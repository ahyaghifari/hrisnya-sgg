<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Konsistensi approval engine: approval_snapshots.status pakai 'cancelled' (2 L),
 * approval_activities.status pakai 'canceled' (1 L) - beda ejaan enum untuk kejadian yang sama.
 * Samakan ke 'cancelled' di approval_activities juga.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ENUM MODIFY cuma sintaks MySQL - SQLite (dipakai test suite, lihat phpunit.xml) tidak
        // punya tipe ENUM sama sekali (kolomnya TEXT biasa), jadi ALTER ini dilewati di sana;
        // data-fix di bawah (DML biasa) tetap jalan di semua driver.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_activities MODIFY status ENUM('pending', 'created', 'approved', 'rejected', 'skipped', 'canceled', 'cancelled') DEFAULT 'pending'");
        }

        DB::table('approval_activities')->where('status', 'canceled')->update(['status' => 'cancelled']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_activities MODIFY status ENUM('pending', 'created', 'approved', 'rejected', 'skipped', 'cancelled') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_activities MODIFY status ENUM('pending', 'created', 'approved', 'rejected', 'skipped', 'canceled', 'cancelled') DEFAULT 'pending'");
        }

        DB::table('approval_activities')->where('status', 'cancelled')->update(['status' => 'canceled']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approval_activities MODIFY status ENUM('pending', 'created', 'approved', 'rejected', 'skipped', 'canceled') DEFAULT 'pending'");
        }
    }
};
