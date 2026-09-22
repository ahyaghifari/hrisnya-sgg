<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kolom id ulid di pivot attendance_device_users tidak pernah diisi belongsToMany::attach()
 * sehingga insert gagal ("Field 'id' doesn't have a default value"). Pivot murni, tidak ada
 * yang mereferensikan id-nya, jadi pakai composite primary key saja.
 *
 * Tabelnya DIBANGUN ULANG (buat tabel baru -> salin isi -> buang lama -> ganti nama), bukan
 * dropColumn('id') langsung: SQLite (database test, lihat phpunit.xml) menolak membuang kolom
 * primary key ("cannot drop PRIMARY KEY column"), dan karena RefreshDatabase menjalankan semua
 * migrasi, versi dropColumn membuat SELURUH test suite gagal di tahap migrasi. Cara ini jalan
 * sama di MySQL maupun SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_device_users_new', function (Blueprint $table) {
            $table->foreignUlid('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['attendance_device_id', 'user_id']);
        });

        DB::table('attendance_device_users')
            ->orderBy('attendance_device_id')
            ->get(['attendance_device_id', 'user_id', 'created_at', 'updated_at'])
            ->chunk(500)
            ->each(fn ($rows) => DB::table('attendance_device_users_new')->insert(
                $rows->map(fn ($r) => (array) $r)->all()
            ));

        Schema::drop('attendance_device_users');
        Schema::rename('attendance_device_users_new', 'attendance_device_users');
    }

    public function down(): void
    {
        Schema::create('attendance_device_users_old', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attendance_device_id', 'user_id']);
        });

        DB::table('attendance_device_users')
            ->get(['attendance_device_id', 'user_id', 'created_at', 'updated_at'])
            ->each(fn ($r) => DB::table('attendance_device_users_old')->insert(
                ['id' => (string) Str::ulid()] + (array) $r
            ));

        Schema::drop('attendance_device_users');
        Schema::rename('attendance_device_users_old', 'attendance_device_users');
    }
};
