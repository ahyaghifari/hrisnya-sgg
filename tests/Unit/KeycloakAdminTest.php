<?php

namespace Tests\Unit;

use App\Services\Keycloak\KeycloakAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeycloakAdminTest extends TestCase
{
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

    public function test_token_endpoint_and_user_listing_pagination(): void
    {
        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok123']),
            'kc.test/admin/realms/hris/users?*' => Http::response([['id' => 'u1', 'username' => 'a']]),
        ]);

        $users = (new KeycloakAdmin)->users('andi', first: 25, max: 25);

        $this->assertSame('u1', $users[0]['id']);

        Http::assertSent(fn ($req) => $req->url() === 'https://kc.test/realms/hris/protocol/openid-connect/token'
            && $req['grant_type'] === 'client_credentials'
            && $req['client_id'] === 'hris-admin');

        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://kc.test/admin/realms/hris/users')
            && $req['search'] === 'andi' && $req['first'] == 25 && $req['max'] == 25
            && $req->hasHeader('Authorization', 'Bearer tok123'));
    }

    public function test_numeric_search_goes_to_q_param_as_nip(): void
    {
        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users?*' => Http::response([]),
        ]);

        (new KeycloakAdmin)->users('12345');

        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://kc.test/admin/realms/hris/users')
            && $req['q'] === 'nip:12345' && ! isset($req['search']));
    }

    public function test_nip_of_reads_custom_attribute(): void
    {
        $kc = new KeycloakAdmin;

        $this->assertSame('42', $kc->nipOf(['attributes' => ['nip' => ['42']]]));
        $this->assertNull($kc->nipOf(['attributes' => []]));
        $this->assertNull($kc->nipOf([]));
    }

    public function test_create_returns_id_from_location_header(): void
    {
        Http::fake([
            'kc.test/realms/hris/protocol/openid-connect/token' => Http::response(['access_token' => 'tok']),
            'kc.test/admin/realms/hris/users' => Http::response(null, 201, [
                'Location' => 'https://kc.test/admin/realms/hris/users/new-id-42',
            ]),
        ]);

        $id = (new KeycloakAdmin)->create(['username' => 'budi']);

        $this->assertSame('new-id-42', $id);
    }

    public function test_not_configured_hides_page(): void
    {
        config(['services.keycloak.admin_client_secret' => null]);

        $this->assertFalse(KeycloakAdmin::isConfigured());
    }
}
