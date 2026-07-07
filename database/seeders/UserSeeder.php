<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Direktur',
            'HC Supervisor',
            'HC Manager',
            'Kasubag / Staff Kepegawaian',
            'Staff Finance',
            'Learning & Development',
            'Manager / Kepala Bidang',
            'Supervisor / Kepala Ruangan',
            'Karyawan / Staff',
        ];

        $companies = [
            'Syifa Global Group' => [
                'suffix' => '@mail.com',
            ],
            'RSU Syifa Medika Banjarbaru' => [
                'suffix' => '_banjarbaru@mail.com',
            ],
        ];

        foreach ($companies as $companyName => $config) {

            $company = Companies::query()
                ->where('name', $companyName)
                ->first();

            if (! $company) {
                continue;
            }

            foreach ($roles as $roleName) {

                $email = $this->generateEmail($roleName, $config['suffix']);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'              => $roleName,
                        'password'          => Hash::make('password'),
                        'company_id'        => $company->id,
                        'email_verified_at' => now(),
                    ]
                );

                $role = Role::query()
                    ->where('name', $roleName)
                    ->where('company_id', $company->id)
                    ->first();

                if ($role && ! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            }
        }
    }

    protected function generateEmail(string $roleName, string $suffix): string
    {
        $slug = strtolower($roleName);
        $slug = str_replace([' ', '/', '&'], '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);

        return $slug . $suffix;
    }
}
