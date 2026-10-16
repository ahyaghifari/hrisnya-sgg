<?php

namespace Tests\Feature\Pwa;

use App\Livewire\Pwa\UbahAkunLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aktivasi SSO self-service (instruksi user 2026-10-16): password SSO = password HRIS, username = NIP.
 * User cukup ketik password HRIS; nilainya diverifikasi lalu dipakai sebagai password Keycloak.
 */
class UbahAkunLoginTest extends TestCase
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

    public function test_wrong_hris_password_is_rejected_and_nothing_is_sent(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-1', 'password' => Hash::make('rahasia123')]);

        Http::fake();

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->set('hrisPassword', 'salah')
            ->call('save')
            ->assertHasErrors(['hrisPassword']);

        Http::assertNothingSent();
    }

    public function test_linked_user_syncs_keycloak_password_to_hris_password(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-2', 'password' => Hash::make('rahasia123')]);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-2/reset-password' => Http::response(null, 204),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->set('hrisPassword', 'rahasia123')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(fn ($req) => $req->url() === 'https://kc.test/admin/realms/hris/users/kc-sub-2/reset-password'
            && $req['value'] === 'rahasia123');
    }

    public function test_user_without_keycloak_link_gets_account_created_with_nip_username(): void
    {
        $user = User::factory()->create(['keycloak_sub' => null, 'password' => Hash::make('rahasia123')]);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users' => Http::response(null, 201, ['Location' => 'https://kc.test/admin/realms/hris/users/kc-new']),
            'kc.test/admin/realms/hris/users/kc-new/reset-password' => Http::response(null, 204),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->assertSet('ssoLinked', false)
            ->set('hrisPassword', 'rahasia123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('ssoLinked', true);

        $this->assertSame('kc-new', $user->fresh()->keycloak_sub);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && $req->url() === 'https://kc.test/admin/realms/hris/users'
            && $req['username'] === $user->nip);
    }
}
