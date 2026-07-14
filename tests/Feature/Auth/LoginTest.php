<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Masuk');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-yang-salah',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $response->assertSessionHasInput('email', $user->email);
    }

    public function test_users_cannot_authenticate_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'tidak-ada@nowhere.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_users_are_redirected_away_from_login_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect();
        $response->assertStatus(302);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee($user->name);
    }

    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'siapa@nowhere.com',
                'password' => 'salah',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'siapa@nowhere.com',
            'password' => 'salah',
        ]);

        $response->assertStatus(429);
    }

    public function test_only_admin_and_super_admin_roles_can_access_admin_panel(): void
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Karyawan / Staff', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $karyawan = User::factory()->create();
        $karyawan->assignRole('Karyawan / Staff');

        $panel = filament()->getDefaultPanel();

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($superAdmin->canAccessPanel($panel));
        $this->assertFalse($karyawan->canAccessPanel($panel));
    }
}
