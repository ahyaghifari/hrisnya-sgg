<?php

namespace Tests\Feature\Rekrutmen;

use App\Enum\CustomPermissionEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Panel /kelola-rekrutmen (repurpose panel `candidate`) — gerbang SISTEM_REKRUTMEN via
 * User::canAccessPanel(). Cuma cek dashboard panel (tidak menyentuh koneksi `recruitment`).
 */
class RekrutmenPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']));

        $this->actingAs($user)->get('/kelola-rekrutmen')->assertOk();
    }

    public function test_user_with_sistem_rekrutmen_permission_can_open_panel(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_REKRUTMEN->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekruter', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_REKRUTMEN->value);

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/kelola-rekrutmen')->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/kelola-rekrutmen')
            ->assertForbidden();
    }
}
