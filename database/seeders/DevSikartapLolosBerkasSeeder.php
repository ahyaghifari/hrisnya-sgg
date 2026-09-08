<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DATA UJI — 10 pengajuan Sikartap berstatus "lolos berkas" (status_berkas=approved,
 * status_tes=pending) buat nyoba: bulk jadwal tes, input hasil tes (modal), bulk jadwal
 * wawancara. Idempoten-ish: skip kalau sudah ada >=10 pengajuan approved+belum jadwal tes.
 *
 *   php artisan db:seed --class=DevSikartapLolosBerkasSeeder
 *
 * Aman dihapus kapan saja — bukan bagian dari DatabaseSeeder.
 */
class DevSikartapLolosBerkasSeeder extends Seeder
{
    private const TARGET = 10;

    public function run(): void
    {
        $existing = Sikartap::where('status_berkas', 'approved')
            ->whereIn('status_tes', [null, 'pending'])
            ->doesntHave('testSchedule')
            ->count();

        if ($existing >= self::TARGET) {
            $this->command?->info("Sudah ada {$existing} pengajuan lolos berkas siap tes — tidak menambah.");

            return;
        }

        $need = self::TARGET - $existing;

        $company = Companies::query()->where('is_active', true)->orderBy('name')->first()
            ?? Companies::query()->orderBy('name')->first();

        if (! $company) {
            $this->command?->error('Tidak ada perusahaan di m_companies — buat dulu.');

            return;
        }

        $usedUserIds = Sikartap::pluck('user_id')->all();

        $users = User::query()
            ->where('company_id', $company->id)
            ->whereNotIn('id', $usedUserIds)
            ->limit($need)
            ->get();

        $missing = $need - $users->count();
        $seq = (int) (User::max('nip') ?: 900000);

        for ($i = 0; $i < $missing; $i++) {
            $seq++;
            $users->push(User::create([
                'nip' => (string) $seq,
                'name' => 'Uji Sikartap ' . $seq,
                'email' => "uji.sikartap.{$seq}@example.test",
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ]));
        }

        $made = 0;
        foreach ($users as $user) {
            Sikartap::create([
                'user_id' => $user->id,
                'status_berkas' => 'approved',
                'berkas_lengkap' => true,
                'status_tes' => 'pending',
                'status_wawancara' => 'pending',
                'status_mcu' => 'pending',
                'status_keputusan_direktur' => 'pending',
            ]);
            $made++;
        }

        $this->command?->info("Dibuat {$made} pengajuan Sikartap lolos berkas di perusahaan \"{$company->name}\".");
    }
}
