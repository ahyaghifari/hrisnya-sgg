<?php

namespace App\Services\Keycloak;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Wrapper tipis Keycloak Admin REST API buat kelola user dari HRIS.
 * Auth: client_credentials (client service-account + role realm-management).
 */
class KeycloakAdmin
{
    private string $base;

    private string $realm;

    public function __construct()
    {
        $this->base = rtrim((string) config('services.keycloak.base_url'), '/');
        $this->realm = (string) config('services.keycloak.realms');

        if ($this->base === '' || $this->realm === '') {
            throw new RuntimeException('Keycloak belum dikonfigurasi (KEYCLOAK_BASE_URL / KEYCLOAK_REALM).');
        }
    }

    public static function isConfigured(): bool
    {
        return filled(config('services.keycloak.base_url'))
            && filled(config('services.keycloak.realms'))
            && filled(config('services.keycloak.admin_client_id'))
            && filled(config('services.keycloak.admin_client_secret'));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * $search: prefix-match username/email/firstName/lastName (param `search` Keycloak).
     * Kalau isinya angka semua, dianggap NIP → pakai param `q=nip:...` (atribut custom
     * `nip` di User Profile Keycloak; huruf kecil).
     */
    public function users(?string $search = null, int $first = 0, int $max = 20): array
    {
        $params = ['first' => $first, 'max' => $max];

        if (filled($search)) {
            $params[ctype_digit($search) ? 'q' : 'search'] = ctype_digit($search)
                ? "nip:{$search}"
                : $search;
        }

        return $this->http()
            ->get("{$this->adminUrl()}/users", $params)
            ->throw()
            ->json();
    }

    /** NIP dari atribut custom Keycloak (attributes.nip[0]; key huruf kecil). */
    public function nipOf(array $user): ?string
    {
        return $user['attributes']['nip'][0] ?? null;
    }

    public function count(?string $search = null): int
    {
        return (int) $this->http()
            ->get("{$this->adminUrl()}/users/count", array_filter(['search' => $search]))
            ->throw()
            ->json();
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $res = $this->http()->get("{$this->adminUrl()}/users/{$id}");

        return $res->status() === 404 ? null : $res->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return string  id user baru
     */
    public function create(array $attrs): string
    {
        $res = $this->http()
            ->post("{$this->adminUrl()}/users", $attrs + ['enabled' => true])
            ->throw();

        // Keycloak balikin id user baru di header Location: .../users/{id}
        return basename((string) $res->header('Location'));
    }

    /** @param array<string, mixed> $attrs */
    public function update(string $id, array $attrs): void
    {
        $this->http()->put("{$this->adminUrl()}/users/{$id}", $attrs)->throw();
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $this->update($id, ['enabled' => $enabled]);
    }

    public function setPassword(string $id, string $password, bool $temporary = false): void
    {
        $this->http()
            ->put("{$this->adminUrl()}/users/{$id}/reset-password", [
                'type' => 'password',
                'value' => $password,
                'temporary' => $temporary,
            ])
            ->throw();
    }

    public function delete(string $id): void
    {
        $this->http()->delete("{$this->adminUrl()}/users/{$id}")->throw();
    }

    // ── Groups ──────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>  grup top-level (subGroups diabaikan di UI HRIS)
     *
     * $search: substring-match nama grup (param `search` Keycloak).
     */
    public function groups(?string $search = null, int $first = 0, int $max = 20): array
    {
        $params = ['first' => $first, 'max' => $max, 'briefRepresentation' => 'false'];

        if (filled($search)) {
            $params['search'] = $search;
        }

        return $this->http()
            ->get("{$this->adminUrl()}/groups", $params)
            ->throw()
            ->json();
    }

    /** @return string  id grup baru */
    public function createGroup(string $name): string
    {
        $res = $this->http()
            ->post("{$this->adminUrl()}/groups", ['name' => $name])
            ->throw();

        return basename((string) $res->header('Location'));
    }

    /**
     * @return array<int, array<string, mixed>>  sub-grup langsung dari $parentId
     *
     * Keycloak 23+ tidak lagi menyertakan subGroups inline di GET /groups —
     * harus endpoint children ini.
     */
    public function childGroups(string $parentId, ?string $search = null, int $first = 0, int $max = 100): array
    {
        $params = ['first' => $first, 'max' => $max, 'briefRepresentation' => 'false'];

        if (filled($search)) {
            $params['search'] = $search;
        }

        return $this->http()
            ->get("{$this->adminUrl()}/groups/{$parentId}/children", $params)
            ->throw()
            ->json();
    }

    /** @return string  id sub-grup baru */
    public function createChildGroup(string $parentId, string $name): string
    {
        $res = $this->http()
            ->post("{$this->adminUrl()}/groups/{$parentId}/children", ['name' => $name])
            ->throw();

        return basename((string) $res->header('Location'));
    }

    /** @param array<string, mixed> $attrs  mis. ['name' => 'baru'] untuk rename */
    public function updateGroup(string $id, array $attrs): void
    {
        $this->http()->put("{$this->adminUrl()}/groups/{$id}", $attrs)->throw();
    }

    public function deleteGroup(string $id): void
    {
        $this->http()->delete("{$this->adminUrl()}/groups/{$id}")->throw();
    }

    /** @return array<int, array<string, mixed>> */
    public function groupMembers(string $id, int $first = 0, int $max = 100): array
    {
        return $this->http()
            ->get("{$this->adminUrl()}/groups/{$id}/members", ['first' => $first, 'max' => $max])
            ->throw()
            ->json();
    }

    public function addUserToGroup(string $userId, string $groupId): void
    {
        $this->http()->put("{$this->adminUrl()}/users/{$userId}/groups/{$groupId}")->throw();
    }

    public function removeUserFromGroup(string $userId, string $groupId): void
    {
        $this->http()->delete("{$this->adminUrl()}/users/{$userId}/groups/{$groupId}")->throw();
    }

    /** @return array<int, array<string, mixed>>  grup yang diikuti user */
    public function userGroups(string $userId): array
    {
        return $this->http()
            ->get("{$this->adminUrl()}/users/{$userId}/groups", ['max' => 200])
            ->throw()
            ->json();
    }

    /**
     * @return array<int, array{id: string, name: string, path: string}>
     *
     * Semua grup + sub-grup, flat, buat dropdown pilih grup.
     */
    public function flatGroups(): array
    {
        $out = [];

        $walk = function (array $nodes) use (&$walk, &$out): void {
            foreach ($nodes as $g) {
                $out[] = ['id' => $g['id'], 'name' => $g['name'], 'path' => $g['path'] ?? $g['name']];

                $children = $g['subGroups'] ?? [];

                if (! $children && ($g['subGroupCount'] ?? 0) > 0) {
                    $children = $this->childGroups($g['id'], null, 0, 200);
                }

                $walk($children);
            }
        };

        $walk($this->groups(null, 0, 200));

        return $out;
    }

    private function adminUrl(): string
    {
        return "{$this->base}/admin/realms/{$this->realm}";
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson();
    }

    private function token(): string
    {
        // ponytail: cache 50s < default access token lifespan Keycloak (60s+). Naikin kalau perlu.
        return Cache::remember('keycloak_admin_token', 50, function (): string {
            $id = (string) config('services.keycloak.admin_client_id');
            $secret = (string) config('services.keycloak.admin_client_secret');

            if ($id === '' || $secret === '') {
                throw new RuntimeException('Keycloak Admin API belum dikonfigurasi (KEYCLOAK_ADMIN_CLIENT_ID / _SECRET).');
            }

            return Http::asForm()
                ->post("{$this->base}/realms/{$this->realm}/protocol/openid-connect/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $id,
                    'client_secret' => $secret,
                ])
                ->throw()
                ->json('access_token');
        });
    }
}
