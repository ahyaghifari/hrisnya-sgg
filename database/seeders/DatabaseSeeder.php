<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ReligionsSeeder::class,
            RoleSeeder::class,
            AttendanceRequestTypeSeeder::class,
        ]);
        
        $companies = [
            ['name' => 'Syifa Global Group', 'location' => '', 'code' => '0000', 'id' => '01J2F0C8KQ9M7D4S8RBX6E1ZVP'],
            ['name' => 'RSU Syifa Medika Banjarbaru', 'location' => 'Banjarbaru'],
            ['name' => 'RSU Syifa Medika Barabai', 'location' => 'Barabai'],
            ['name' => 'Syifa Global Med', 'location' => ''],
            ['name' => 'Syifa Global Lestari Banjarbaru', 'location' => 'Banjarbaru'],
            ['name' => 'Syifa Global Lestari Surabaya', 'location' => 'Surabaya'],
            ['name' => 'Lerina Inn', 'location' => ''],
            ['name' => 'Syifa Agro Natura', 'location' => ''],
        ];

        // Insert companies
        $companyModels = [];
        foreach ($companies as $company) {
            $companyAttributes = [
                'location' => $company['location'],
            ];

            if (!empty($company['code'])) {
                $companyAttributes['code'] = $company['code'];
            }

            if (!empty($company['id'])) {
                $companyAttributes['id'] = $company['id'];
            }

            $companyModels[$company['name']] = Companies::firstOrCreate(
                ['name' => $company['name']],
                $companyAttributes
            );
        }

        // ===== SUPER ADMIN USER =====
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@syifa.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
            ]
        );

        // ===== ROLES =====
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'company_id' => null,
        ]);

        // Admin role per company + user admin per company
        foreach ($companyModels as $companyName => $company) {
            $adminRole = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);

            // Create default admin user for this company
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@' . Str::slug($companyName) . '.com'],
                [
                    'name' => 'Admin ' . $companyName,
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                ]
            );

            // Assign role admin (tanpa permission langsung)
            $adminUser->assignRole($adminRole);
        }

        // ===== ADD USERS FOR RSU Syifa Medika Banjarbaru =====
        $targetCompanyName = 'RSU Syifa Medika Banjarbaru';
        if (isset($companyModels[$targetCompanyName])) {
            $company = $companyModels[$targetCompanyName];
            $rolesForCompany = [
                'Karyawan',
                'Kepegawaian',
                'Supervisor',
                'Manajer',
                'Direktur',
                'Leading Development',
                'Keuangan',
            ];

            foreach ($rolesForCompany as $roleName) {
                // Create role scoped to the company
                $roleModel = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'company_id' => $company->id,
                ]);

                // Create user with name and email in the requested format
                $user = User::firstOrCreate(
                    ['email' => Str::slug($roleName) . '@' . Str::slug($targetCompanyName) . '.com'],
                    [
                        'name' => $roleName . ' ' . $targetCompanyName,
                        'password' => Hash::make('password'),
                        'company_id' => $company->id,
                    ]
                );

                // Assign the company role to the user
                $user->assignRole($roleModel);
            }
        }

        // Jenis cuti default + backfill kategori (butuh companies sudah ada).
        $this->call(LeaveTypeCategorySeeder::class);

        // Super Admin gets all permissions
        $superAdminRole->syncPermissions(Permission::all());

        // Assign role ke super admin user
        $superAdmin->assignRole($superAdminRole);
    }
}
