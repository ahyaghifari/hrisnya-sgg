<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penilai Orientasi (context 'orientation') & Penilai Perpanjangan Kontrak (context 'contract')
 * digabung jadi satu set penilai perjalanan status karyawan (context 'status_karyawan'),
 * instruksi user 2026-09-07. Baris duplikat (company+departemen+jabatan sama di kedua context)
 * disatukan jadi satu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        foreach (
            DB::table('sikartap_reviewers')
                ->whereIn('context', ['orientation', 'contract'])
                ->orderBy('id')
                ->get() as $row
        ) {
            $key = $row->company_id . '|' . $row->org_structure_id . '|' . $row->job_position_id;

            if (isset($seen[$key])) {
                DB::table('sikartap_reviewers')->where('id', $row->id)->delete();

                continue;
            }

            $seen[$key] = true;
            DB::table('sikartap_reviewers')->where('id', $row->id)->update(['context' => 'status_karyawan']);
        }
    }

    public function down(): void
    {
        // Penggabungan searah — tidak bisa dipecah lagi jadi 'orientation'/'contract'.
        DB::table('sikartap_reviewers')->where('context', 'status_karyawan')->update(['context' => 'orientation']);
    }
};
