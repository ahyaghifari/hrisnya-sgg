<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Toggle Merit Point pindah dari Pengaturan Pembinaan ke Pengaturan Kepegawaian: key & module diganti.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'pembinaan_merit_point_enabled')
            ->update(['key' => 'kepegawaian_merit_point_enabled', 'module' => 'kepegawaian']);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'kepegawaian_merit_point_enabled')
            ->update(['key' => 'pembinaan_merit_point_enabled', 'module' => 'pembinaan']);
    }
};
