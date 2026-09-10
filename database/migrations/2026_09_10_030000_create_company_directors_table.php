<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jabatan Direktur per perusahaan — 1 baris per company. Sebelumnya disimpan sebagai Setting
 * `sikartap_direktur_job_position_id`; dipindah ke tabel sendiri supaya modul lain yang butuh
 * "siapa Direktur perusahaan ini" tidak scatter lewat Setting key. Resolusi lewat
 * App\Services\CompanyDirectorService. Data Setting lama di-migrasi otomatis di bawah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_directors', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('company_id')->unique()->constrained('m_companies')->cascadeOnDelete();
            // nullable: 1 baris per perusahaan boleh ada tanpa jabatan terisi (belum diatur).
            $table->foreignUlid('job_position_id')->nullable()->constrained('m_job_positions')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('settings')
            ->where('key', 'sikartap_direktur_job_position_id')
            ->whereNotNull('company_id')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get(['company_id', 'value'])
            ->each(function ($row): void {
                $jobExists = DB::table('m_job_positions')->where('id', $row->value)->exists();

                if (! $jobExists) {
                    return;
                }

                DB::table('company_directors')->updateOrInsert(
                    ['company_id' => $row->company_id],
                    ['job_position_id' => $row->value, 'created_at' => now(), 'updated_at' => now()],
                );
            });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_directors');
        Schema::enableForeignKeyConstraints();
    }
};
