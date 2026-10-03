<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Dasar Klarifikasi jadi item per baris (nomor otomatis), bukan 1 blok Trix bernomor manual
 * (instruksi user 2026-10-02). Isi lama dipindah utuh sebagai 1 item - tidak di-parse, supaya
 * teks (dan hash TTD dokumen selesai) tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_report_dasar_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sp_report_id')->constrained('sp_reports')->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sp_report_id');
        });

        DB::table('sp_reports')->whereNotNull('dasar_klarifikasi')->where('dasar_klarifikasi', '!=', '')
            ->get(['id', 'dasar_klarifikasi'])
            ->each(fn ($r) => DB::table('sp_report_dasar_items')->insert([
                'id' => (string) Str::ulid(),
                'sp_report_id' => $r->id,
                'text' => $r->dasar_klarifikasi,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('sp_reports', fn (Blueprint $table) => $table->dropColumn('dasar_klarifikasi'));
    }

    public function down(): void
    {
        Schema::table('sp_reports', fn (Blueprint $table) => $table->text('dasar_klarifikasi')->nullable()->after('kejadian'));

        DB::table('sp_report_dasar_items')->orderBy('sort_order')->get()->groupBy('sp_report_id')
            ->each(fn ($items, $id) => DB::table('sp_reports')->where('id', $id)->update([
                'dasar_klarifikasi' => $items->pluck('text')->implode("\n"),
            ]));

        Schema::dropIfExists('sp_report_dasar_items');
    }
};
