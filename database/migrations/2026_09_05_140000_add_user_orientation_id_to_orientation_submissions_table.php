<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orientation_submissions dibuat SEBELUM tabel user_orientations ada (migration 09-03 vs 09-04),
 * jadi belum punya FK ke periode orientasi. Tiap pengajuan penilaian sekarang menempel ke 1
 * baris user_orientations (periode orientasi karyawan saat pengajuan dibuat) — nullable +
 * nullOnDelete supaya data lama / periode yang dihapus tidak bikin pengajuan ikut terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->foreignUlid('user_orientation_id')->nullable()->after('user_id')
                ->constrained('user_orientations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_orientation_id');
        });
    }
};
