<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Sementara: hapus setelah dipakai.
class UpdateMaritalStatusFromExcel extends Command
{
    protected $signature = 'marital:update-from-excel {file : Path file xlsx} {--apply : Tulis ke database. Tanpa ini hanya dry-run.}';

    protected $description = 'Update user_profiles.marital_status dari excel (kolom B = NIP, kolom D = status perkawinan)';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $this->info($apply ? 'MODE: APPLY' : 'MODE: DRY-RUN (pakai --apply untuk eksekusi)');

        $rows = IOFactory::load($this->argument('file'))->getActiveSheet()->toArray(null, true, false, true);
        $updated = $skipped = 0;

        foreach ($rows as $i => $row) {
            $rawNip = $row['B'] ?? '';
            // angka mentah besar jangan jadi notasi ilmiah (6.3E+15)
            $nip = trim(is_numeric($rawNip) ? sprintf('%.0f', $rawNip) : (string) $rawNip);
            // petik di depan = penahan nol depan di excel, bukan bagian NIP
            $nip = ltrim($nip, "'’`");
            $status = trim((string) ($row['D'] ?? ''));

            if ($nip === '' || $status === '') {
                continue;
            }

            // ponytail: NIP numerik di excel kehilangan nol depan; coba pad ke 11 digit
            $user = User::whereNip($nip)->first()
                ?? (ctype_digit($nip) ? User::whereNip(str_pad($nip, 11, '0', STR_PAD_LEFT))->first() : null);

            if (!$user) {
                $this->warn("[SKIP] baris {$i}: NIP {$nip} tidak ditemukan.");
                $skipped++;
                continue;
            }

            $this->line("{$user->nip} {$user->name}: {$status}");

            if ($apply) {
                $user->profile()->updateOrCreate([], ['marital_status' => $status]);
            }
            $updated++;
        }

        $this->info("Selesai. Cocok: {$updated}, dilewati: {$skipped}.");

        return self::SUCCESS;
    }
}
