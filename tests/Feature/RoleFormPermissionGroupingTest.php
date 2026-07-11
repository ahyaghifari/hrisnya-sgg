<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RoleForm men-generate CheckboxList per permission Shield ("Verb:Subject") dan
 * mengelompokkannya berdasarkan navigationGroup Resource pemilik model tsb — regresi di
 * sini biasanya berupa exception saat render (bukan assertion salah), jadi cukup pastikan
 * halaman edit Role render 200 dengan katalog permission Shield asli.
 */
class RoleFormPermissionGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_role_page_renders_with_shield_permission_catalog(): void
    {
        $this->artisan('shield:generate', [
            '--panel' => 'admin',
            '--all' => true,
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        $admin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole($superAdminRole);

        $editableRole = Role::create(['name' => 'Kepegawaian', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->get(RoleResource::getUrl('edit', ['record' => $editableRole]))
            ->assertOk();
    }
}
