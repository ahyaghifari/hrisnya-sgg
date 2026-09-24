<?php

namespace App\Console\Commands;

use App\Models\Job\JobOpenings;
use App\Models\Master\JobPositions;
use App\Models\Rekrutmen\JobOpening as RecruitmentJobOpening;
use Illuminate\Console\Command;

/**
 * Isi job_openings.division_name di DB recruitment untuk lowongan yang sudah ada. Nilainya sendiri
 * diturunkan dari jabatan HRIS oleh App\Models\Rekrutmen\JobOpening::booted(), jadi command ini cuma
 * memastikan job_position_id-nya tertaut lalu menyimpan ulang barisnya.
 *
 * Perlu karena kolom itu baru ada 2026-09-23: lowongan lama tidak punya isinya, dan token {divisi}
 * di naskah Persetujuan Onboarding keluar sebagai tanda hubung sampai lowongannya disimpan ulang.
 *
 * Sekali jalan saja; aman diulang (idempoten, cuma menyentuh baris yang divisinya masih kosong).
 */
class BackfillRecruitmentDivisionNames extends Command
{
    protected $signature = 'rekrutmen:backfill-divisi {--dry-run : Cuma tampilkan, jangan simpan}';

    protected $description = 'Isi divisi lowongan lama di DB recruitment dari jabatan HRIS-nya';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $targets = RecruitmentJobOpening::withTrashed()->whereNull('division_name')->get();

        if ($targets->isEmpty()) {
            $this->info('Semua lowongan sudah punya divisi.');

            return self::SUCCESS;
        }

        // Lowongan lama bisa belum punya job_position_id (jalur push baru mengirimnya 2026-09-23),
        // jadi tautannya dicari dulu lewat lowongan HRIS-nya.
        $positionByHrisOpening = JobOpenings::pluck('job_position_id', 'id');

        $terisi = 0;
        $gagal = [];

        foreach ($targets as $target) {
            $positionId = $target->job_position_id ?: $positionByHrisOpening->get($target->hris_job_opening_id);

            if (! $positionId) {
                $gagal[] = "{$target->title} (belum tertaut ke jabatan HRIS)";

                continue;
            }

            // Divisinya diisi hook simpan JobOpening::booted(); di dry-run hook itu tidak jalan, jadi
            // nilainya dihitung langsung dari jabatannya cuma buat ditampilkan.
            $divisi = JobPositions::find($positionId)?->division_or_unit_name;

            if (! $dryRun) {
                $target->job_position_id = $positionId;
                $target->save();
                $target->refresh();
                $divisi = $target->division_name;
            }

            if (! $divisi) {
                $gagal[] = "{$target->title} (jabatannya belum punya struktur organisasi)";

                continue;
            }

            $this->line("  {$target->title} -> {$divisi}");
            $terisi++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '') . "{$terisi} lowongan terisi dari " . $targets->count() . ' yang divisinya kosong.');

        if ($gagal !== []) {
            $this->newLine();
            $this->warn('Belum bisa diisi otomatis:');
            foreach ($gagal as $item) {
                $this->line("  - {$item}");
            }
        }

        return self::SUCCESS;
    }
}
