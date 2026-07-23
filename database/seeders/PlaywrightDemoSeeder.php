<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data deterministik buat suite Playwright (tests/e2e) — company + hierarki atasan/bawahan
 * + 2 akun tetap. Dijalankan lewat `php artisan migrate:fresh --seed=...` di DB sqlite
 * terpisah (lihat tests/e2e/global-setup.ts), bukan buat dev/production.
 */
class PlaywrightDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Companies::create([
            'code' => 'E2E',
            'name' => 'E2E Test Company',
            'location' => 'Test',
            'is_active' => true,
        ]);

        $managerPosition = JobPositions::create([
            'company_id' => $company->id,
            'name' => 'Manager (E2E)',
        ]);

        $staffPosition = JobPositions::create([
            'company_id' => $company->id,
            'name' => 'Staff (E2E)',
            'parent_id' => $managerPosition->id,
        ]);

        $atasan = User::create([
            'company_id' => $company->id,
            'name' => 'Atasan E2E',
            'email' => 'atasan@e2e.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $bawahan = User::create([
            'company_id' => $company->id,
            'name' => 'Bawahan E2E',
            'email' => 'bawahan@e2e.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        UserJobPositions::create([
            'user_id' => $atasan->id,
            'job_position_id' => $managerPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        UserJobPositions::create([
            'user_id' => $bawahan->id,
            'job_position_id' => $staffPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $this->command?->info('PlaywrightDemoSeeder selesai: atasan@e2e.test / bawahan@e2e.test, password: password');
    }
}
