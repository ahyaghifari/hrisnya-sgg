<?php

namespace Tests\Feature\Pwa;

use App\Livewire\Pwa\Akun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kartu "SSO" di halaman Akun - preview username+email tanpa buka UbahAkunLogin dulu
 * (instruksi user 2026-09-17).
 */
class AkunSsoCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.base_url' => 'https://kc.test/',
            'services.keycloak.realms' => 'hris',
            'services.keycloak.admin_client_id' => 'hris-admin',
            'services.keycloak.admin_client_secret' => 'secret',
        ]);

        Cache::flush();
    }

    public function test_sso_linked_user_sees_username_from_keycloak(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-1', 'email' => 'budi@example.com']);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-1' => Http::response(['id' => 'kc-sub-1', 'username' => 'budi.s']),
        ]);

        Livewire::actingAs($user)->test(Akun::class)
            ->assertSet('ssoLinked', true)
            ->assertSet('ssoUsername', 'budi.s')
            ->assertSee('budi.s')
            ->assertSee('budi@example.com');
    }

    public function test_non_sso_user_sees_not_linked_notice(): void
    {
        $user = User::factory()->create(['keycloak_sub' => null, 'email' => 'siti@example.com']);

        Livewire::actingAs($user)->test(Akun::class)
            ->assertSet('ssoLinked', false)
            ->assertSee('Belum tersambung');

        Http::assertNothingSent();
    }
}
