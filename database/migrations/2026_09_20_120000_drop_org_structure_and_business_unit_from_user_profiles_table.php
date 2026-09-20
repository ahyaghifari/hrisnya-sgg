<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `user_profiles.org_structure_id` dan `business_unit_id` dihapus (instruksi user 2026-09-20).
 * Struktur organisasi karyawan sekarang DITURUNKAN dari jabatan
 * (profil.job_position_id -> m_job_positions.org_structure_id), perusahaan lewat users.company_id.
 * `business_unit_id` tidak punya FK/tabel tujuan dan tidak dibaca kode manapun.
 *
 * DATA HILANG PERMANEN: karyawan yang org-nya hanya ada di profil (tanpa jabatan / jabatan tanpa
 * struktur) kehilangan org sampai diberi jabatan. down() hanya memulihkan struktur kolomnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_profiles', 'org_structure_id')) {
            $hasFk = collect(Schema::getForeignKeys('user_profiles'))
                ->contains(fn (array $fk) => in_array('org_structure_id', $fk['columns'], true));

            if ($hasFk) {
                Schema::table('user_profiles', fn (Blueprint $table) => $table->dropForeign(['org_structure_id']));
            }
        }

        $drop = array_values(array_filter(
            ['org_structure_id', 'business_unit_id'],
            fn ($c) => Schema::hasColumn('user_profiles', $c)
        ));

        if ($drop) {
            Schema::dropColumns('user_profiles', $drop);
        }
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_profiles', 'org_structure_id')) {
                $table->foreignUlid('org_structure_id')->nullable()->constrained('m_org_structures')->nullOnDelete();
            }
            if (! Schema::hasColumn('user_profiles', 'business_unit_id')) {
                $table->ulid('business_unit_id')->nullable();
            }
        });
    }
};
