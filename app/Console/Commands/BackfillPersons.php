<?php

namespace App\Console\Commands;

use App\Models\Person\Person;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\Identity\PersonSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Salin SEMUA users + user_profiles ke persons* (lihat PersonSyncService). Idempotent - aman
 * dijalankan berulang. --dry-run tidak menulis apa pun, cuma menghitung & melaporkan konflik data
 * (NIK kosong/ganda) yang perlu dibereskan sebelum constraint unik dipasang.
 */
class BackfillPersons extends Command
{
    protected $signature = 'identity:backfill {--dry-run : Hitung & laporkan saja, tanpa menulis} {--user= : Batasi ke satu NIP}';

    protected $description = 'Salin users + user_profiles ke persons/person_identities/person_addresses (idempotent)';

    public function handle(PersonSyncService $sync): int
    {
        $dry = (bool) $this->option('dry-run');

        $users = User::withoutGlobalScopes()->withTrashed()
            ->when($this->option('user'), fn ($q, $nip) => $q->where('nip', $nip));

        $total = (clone $users)->count();
        $existing = Person::whereIn('user_id', (clone $users)->select('id'))->count();

        $this->info(($dry ? '[DRY-RUN] ' : '') . "users: {$total}, sudah punya person: {$existing}, akan dibuat: " . ($total - $existing));

        $linked = (clone $users)->whereNotNull('person_id')->count();
        $this->line("users.person_id terisi: {$linked} dari {$total}");

        $this->reportConflicts();

        if ($dry) {
            return self::SUCCESS;
        }

        $failed = 0;

        $users->chunkById(200, function ($chunk) use ($sync, &$failed) {
            foreach ($chunk as $user) {
                try {
                    $sync->syncUser($user);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("NIP {$user->nip}: {$e->getMessage()}");
                }
            }
        });

        $this->info('Selesai. Gagal: ' . $failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Konflik data yang harus diputuskan HC/Admin sebelum NIK dibuat unik. Hanya jumlah & NIP. */
    private function reportConflicts(): void
    {
        $blank = UserProfiles::whereNull('no_nik')->orWhere('no_nik', '')->count();
        $this->line("NIK kosong: {$blank}");

        $dupes = UserProfiles::select('no_nik', DB::raw('count(*) as n'))
            ->whereNotNull('no_nik')->where('no_nik', '!=', '')
            ->groupBy('no_nik')->havingRaw('count(*) > 1')->get();

        $this->line('NIK ganda: ' . $dupes->count() . ' nilai');

        foreach ($dupes as $d) {
            $nips = User::withoutGlobalScopes()->withTrashed()
                ->whereIn('id', UserProfiles::where('no_nik', $d->no_nik)->pluck('user_id'))
                ->pluck('nip')->implode(', ');
            $this->warn("  NIP berbagi NIK: {$nips}");
        }
    }
}
