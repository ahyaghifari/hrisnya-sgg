<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pengganti App\Enum\Payroll\GajiPokokKenaikanOverrideEnum (hardcode 1 user) - kebutuhan
 * ternyata bukan cuma 1 orang, jadi dipindah ke data master per user, dikelola dari halaman
 * portal (bukan lagi tambah `case` di kode). Rate persen flat, sama makna dengan
 * Setting::gaji_pokok_kenaikan_per_kelas tapi khusus 1 user - lihat GajiPokokCalculator untuk
 * urutan prioritas lengkap (Gaji Khusus -> master ini -> Setting company).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gaji_pokok_kenaikan_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('rate', 5, 2);
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrasi baris tunggal yang tadinya hardcode di enum (2026-08-22, Abdurrahman Hakim 8%).
        // Insert langsung, bukan lewat model - migration harus tetap jalan walau user itu sudah
        // tidak ada lagi (kena FK cascade skip via whereExists).
        if (Schema::hasTable('users') && \DB::table('users')->where('id', '01kn1dpvvfs1hdwej8k3j4rmr0')->exists()) {
            \DB::table('gaji_pokok_kenaikan_overrides')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => '01kn1dpvvfs1hdwej8k3j4rmr0',
                'rate' => 8.0,
                'note' => 'Migrasi dari GajiPokokKenaikanOverrideEnum (2026-08-22)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gaji_pokok_kenaikan_overrides');
    }
};
