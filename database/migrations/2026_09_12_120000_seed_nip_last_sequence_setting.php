<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed urutan terakhir NIP generator — dipakai App\Services\Kepegawaian\NipGeneratorService.
 * Global (company_id null), lintas 3 company kode 0000/0001/0002 (Syifa Global Group, RSU
 * Syifa Medika Banjarbaru, RSU Syifa Medika Barabai). Nilai awal 37 = urutan terakhir yang
 * sudah dipakai per 2026-09-12 — karyawan berikutnya dapat 038, dst, wrap 999 -> 001.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'nip_last_sequence')->whereNull('company_id')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => null,
            'module' => 'kepegawaian',
            'key' => 'nip_last_sequence',
            'type' => 'integer',
            'value' => '37',
            'label' => 'Urutan Terakhir NIP',
            'description' => 'Urutan 3 digit terakhir yang sudah dipakai NIP generator (wrap 999 -> 001). Lintas company 0000/0001/0002.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'nip_last_sequence')->whereNull('company_id')->delete();
    }
};
