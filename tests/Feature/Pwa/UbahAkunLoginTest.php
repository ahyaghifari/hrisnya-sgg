<?php

namespace Tests\Feature\Pwa;

use App\Livewire\Pwa\UbahAkunLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman self-service ganti username & password SSO (instruksi user 2026-09-17). Terpisah
 * total dari password akun lokal HRIS (UbahPassword, tidak lagi sync ke Keycloak - lihat
 * revisi instruksi user 2026-09-17 yang sama). Email TIDAK bisa diubah di sini, dan akun SSO
 * sendiri tidak bisa dibuat sendiri - kalau belum tersambung cuma ditampilkan keterangan
 * suruh lapor ke IT, tidak ada form sama sekali.
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

    public function test_user_without_keycloak_link_sees_notice_instead_of_form(): void
    {
        $user = User::factory()->create(['keycloak_sub' => null]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->assertSet('ssoLinked', false)
            ->assertSee('Belum punya akun SSO')
            ->assertDontSee('Simpan');

        Http::assertNothingSent();
    }

    public function test_sso_linked_user_can_change_username_without_touching_password(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-1']);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-1' => Http::sequence()
                ->push(['id' => 'kc-sub-1', 'username' => 'lama.username'])
                ->push(null, 204),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->assertSet('ssoLinked', true)
            ->assertSet('username', 'lama.username')
            ->set('username', 'baru.username')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(fn ($req) => $req->url() === 'https://kc.test/admin/realms/hris/users/kc-sub-1'
            && $req->method() === 'PUT'
            && $req['username'] === 'baru.username');

        // Password TIDAK disentuh kalau field password dikosongkan.
        Http::assertNotSent(fn ($req) => str_ends_with($req->url(), '/reset-password'));
    }

    public function test_filling_password_also_calls_keycloak_reset_password(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-2']);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-2' => Http::response(['id' => 'kc-sub-2', 'username' => 'user2']),
            'kc.test/admin/realms/hris/users/kc-sub-2/reset-password' => Http::response(null, 204),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->set('password', 'passwordbaru123')
            ->set('password_confirmation', 'passwordbaru123')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(fn ($req) => $req->url() === 'https://kc.test/admin/realms/hris/users/kc-sub-2/reset-password'
            && $req['value'] === 'passwordbaru123');
    }

    public function test_keycloak_failure_shows_error_and_does_not_crash(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-3']);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-3' => Http::sequence()
                ->push(['id' => 'kc-sub-3', 'username' => 'user3'])
                ->push(null, 500),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->set('username', 'baru.username')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_username_is_required(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-4']);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-4' => Http::response(['id' => 'kc-sub-4', 'username' => 'user4']),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->set('username', '')
            ->call('save')
            ->assertHasErrors(['username']);
    }

    public function test_password_must_be_confirmed_when_filled(): void
    {
        $user = User::factory()->create(['keycloak_sub' => 'kc-sub-5']);

        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users/kc-sub-5' => Http::response(['id' => 'kc-sub-5', 'username' => 'user5']),
        ]);

        Livewire::actingAs($user)->test(UbahAkunLogin::class)
            ->set('password', 'passwordbaru123')
            ->set('password_confirmation', 'beda')
            ->call('save')
            ->assertHasErrors(['password']);
    }
}
