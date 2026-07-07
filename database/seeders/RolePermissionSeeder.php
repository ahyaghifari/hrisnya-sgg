<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->get();

        Companies::query()
            ->select('id')
            ->chunk(50, function ($companies) use ($allPermissions) {
                foreach ($companies as $company) {

                    $superAdminRole = Role::query()
                        ->where('name', 'Super Admin')
                        ->where('guard_name', 'web')
                        ->where('company_id', $company->id)
                        ->first();

                    if (! $superAdminRole) {
                        continue;
                    }

                    // Assign ALL permissions
                    $superAdminRole->syncPermissions($allPermissions);
                }
            });
    }
}
