<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixNipTypo extends Command
{
    protected $signature = 'nip:fix-typo {--apply : Actually update the database. Without this flag, only prints the planned changes.}';

    protected $description = 'Fix known NIP typos found by cross-checking rekap-karyawan.xlsx against the users table';

    // old (nip di database, salah) => new (nip di excel, benar)
    private const FIXES = [
        '02032022068'   => ['new' => '01082022452', 'name' => 'dr. Bagus Putra Kurniawan'],
        '2026051816492' => ['new' => '01122017098', 'name' => 'Muhammad Rizki, A. Md. Ft'],
        '02022023508'   => ['new' => '02062023508', 'name' => 'Neka Oktari Norcahya, A.Md.Kep'],
        '22122024594'   => ['new' => '220122024594', 'name' => 'Muhammad Halimi'],
    ];

    public function handle(): int
    {
        $apply = $this->option('apply');
        $this->info($apply ? 'MODE: APPLY (akan mengubah database)' : 'MODE: DRY-RUN (tidak mengubah apapun, pakai --apply untuk eksekusi)');
        $this->newLine();

        foreach (self::FIXES as $oldNip => $fix) {
            $user = User::where('nip', $oldNip)->first();

            if (!$user) {
                $this->warn("[SKIP] NIP {$oldNip} ({$fix['name']}) tidak ditemukan di database.");
                continue;
            }

            if (trim($user->name) !== trim($fix['name'])) {
                $this->warn("[SKIP] NIP {$oldNip} ditemukan tapi nama tidak cocok (db: {$user->name}, expected: {$fix['name']}) - dilewati demi keamanan.");
                continue;
            }

            $clash = User::where('nip', $fix['new'])->where('id', '!=', $user->id)->first();
            if ($clash) {
                $this->warn("[SKIP] NIP baru {$fix['new']} sudah dipakai user lain ({$clash->name}) - konflik, tidak diubah.");
                continue;
            }

            $this->line("{$fix['name']}: {$oldNip} -> {$fix['new']}");

            if ($apply) {
                $user->update(['nip' => $fix['new']]);
                Log::info("FixNipTypo: updated nip {$oldNip} -> {$fix['new']} for user_id={$user->id} ({$fix['name']})");
                $this->line('  [DIUPDATE]');
            }
        }

        $this->newLine();
        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
