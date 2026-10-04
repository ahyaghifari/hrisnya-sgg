<?php

namespace App\Filament\Keycloak\Pages;

use App\Models\Employee\Employee;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdmin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class KeycloakGroups extends Page
{
    protected string $view = 'filament.keycloak.pages.keycloak-groups';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Groups';

    protected static ?string $title = 'Keycloak Groups';

    protected static ?string $slug = 'groups';

    protected static ?int $navigationSort = 2;

    /** Pohon grup penuh: [['id','name','path','children' => [...]], ...]. */
    public array $tree = [];

    public ?string $treeError = null;

    // Panel kanan: grup terpilih + anggotanya.
    public ?string $selectedId = null;

    public ?string $selectedName = null;

    /** @var array<int, array<string, mixed>> */
    public array $members = [];

    public function mount(): void
    {
        $this->refreshTree();
    }

    protected function kc(): KeycloakAdmin
    {
        return app(KeycloakAdmin::class);
    }

    public function refreshTree(): void
    {
        try {
            $this->treeError = null;
            $this->tree = $this->buildTree(null);
        } catch (Throwable $e) {
            $this->tree = [];
            $this->treeError = $e->getMessage();
        }

        // Grup terpilih mungkin sudah terhapus / di-rename.
        if ($this->selectedId && ! $this->findNode($this->tree, $this->selectedId)) {
            $this->selectedId = $this->selectedName = null;
            $this->members = [];
        } elseif ($this->selectedId) {
            $this->selectedName = $this->findNode($this->tree, $this->selectedId)['name'] ?? $this->selectedName;
        }
    }

    /**
     * ponytail: 1 request Keycloak per grup (rekursif). Grup segelintir level, aman.
     * Dipanggil cuma saat mount + sesudah aksi, bukan tiap render.
     */
    private function buildTree(?string $parentId): array
    {
        $groups = $parentId === null
            ? $this->kc()->groups(null, 0, 200)
            : $this->kc()->childGroups($parentId, null, 0, 200);

        return collect($groups)
            ->map(fn (array $g): array => [
                'id' => $g['id'],
                'name' => $g['name'],
                'path' => $g['path'] ?? null,
                // subGroupCount ada di Keycloak 23+; kalau tidak ada, tetap rekursi buat aman.
                'children' => ($g['subGroupCount'] ?? 1) > 0 ? $this->buildTree($g['id']) : [],
            ])
            ->all();
    }

    private function findNode(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }

            if ($found = $this->findNode($node['children'], $id)) {
                return $found;
            }
        }

        return null;
    }

    public function selectGroup(string $id, string $name): void
    {
        $this->selectedId = $id;
        $this->selectedName = $name;
        $this->loadMembers();
    }

    public function loadMembers(): void
    {
        $this->members = $this->selectedId
            ? $this->kc()->groupMembers($this->selectedId, 0, 200)
            : [];
    }

    /** Select karyawan HRIS: value = nip. */
    private function hrisUserOptions(?string $search): array
    {
        return User::query()
            ->whereIn('users.id', Employee::query()->select('user_id')->whereNotNull('nip'))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->searchNameOrNip($search)
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByName()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->nip => "{$u->nip} — {$u->name}"])
            ->all();
    }

    /** id user Keycloak dari NIP: link eksplisit (keycloak_sub) dulu, fallback cari via NIP. */
    private function keycloakUserId(string $nip): ?string
    {
        return User::whereNip($nip)->value('keycloak_sub')
            ?: ($this->kc()->users($nip, 0, 1)[0]['id'] ?? null);
    }

    // ── Pemicu aksi dari tombol (mount dgn arguments dari sisi PHP,
    //    biar tidak bergantung parsing object-literal di wire:click) ──────

    public function newRootGroup(): void
    {
        $this->mountAction('create');
    }

    public function newSubgroup(): void
    {
        if ($this->selectedId) {
            $this->mountAction('create', ['parentId' => $this->selectedId, 'parentName' => $this->selectedName]);
        }
    }

    public function editSelected(): void
    {
        if ($this->selectedId) {
            $this->mountAction('rename', ['id' => $this->selectedId, 'name' => $this->selectedName]);
        }
    }

    public function deleteSelected(): void
    {
        if ($this->selectedId) {
            $this->mountAction('delete', ['id' => $this->selectedId, 'name' => $this->selectedName]);
        }
    }

    public function promptRemoveMember(string $userId): void
    {
        $username = collect($this->members)->firstWhere('id', $userId)['username'] ?? $userId;
        $this->mountAction('removeMember', ['userId' => $userId, 'username' => $username]);
    }

    // ── Aksi (di-mount dgn arguments dari tombol) ───────────────────────

    public function createAction(): Action
    {
        return Action::make('create')
            ->label('Buat Grup')
            ->modalHeading(fn (array $arguments): string => ($arguments['parentId'] ?? null)
                ? 'Buat Sub-grup di "' . ($arguments['parentName'] ?? '') . '"'
                : 'Buat Grup Root')
            ->schema([
                TextInput::make('name')->label('Nama')->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                ($arguments['parentId'] ?? null)
                    ? $this->kc()->createChildGroup($arguments['parentId'], $data['name'])
                    : $this->kc()->createGroup($data['name']);
                Notification::make()->title('Grup dibuat')->success()->send();
                $this->refreshTree();
            });
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->label('Ubah Nama')
            ->fillForm(fn (array $arguments): array => ['name' => $arguments['name'] ?? null])
            ->schema([
                TextInput::make('name')->label('Nama Grup')->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->kc()->updateGroup($arguments['id'], ['name' => $data['name']]);
                Notification::make()->title('Grup diperbarui')->success()->send();
                $this->refreshTree();
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus Grup')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (array $arguments): string => 'Hapus "' . ($arguments['name'] ?? '') . '" beserta seluruh sub-grupnya?')
            ->action(function (array $arguments): void {
                $this->kc()->deleteGroup($arguments['id']);
                Notification::make()->title('Grup dihapus')->success()->send();

                if (($arguments['id'] ?? null) === $this->selectedId) {
                    $this->selectedId = $this->selectedName = null;
                    $this->members = [];
                }

                $this->refreshTree();
            });
    }

    public function addMemberAction(): Action
    {
        return Action::make('addMember')
            ->label('Assign Anggota')
            ->modalHeading(fn (): string => 'Assign anggota ke "' . $this->selectedName . '"')
            ->schema([
                Select::make('hris_nip')
                    ->label('Karyawan HRIS')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => $this->hrisUserOptions($search))
                    ->getOptionLabelUsing(fn ($value) => optional(User::whereNip($value)->first(), fn ($u) => "{$u->nip} — {$u->name}")),
            ])
            ->action(function (array $data): void {
                if (! $this->selectedId) {
                    return;
                }

                $userId = $this->keycloakUserId($data['hris_nip']);

                if (! $userId) {
                    Notification::make()->title('User Keycloak tidak ada untuk NIP itu')->danger()->send();

                    return;
                }

                $this->kc()->addUserToGroup($userId, $this->selectedId);
                Notification::make()->title('Anggota di-assign')->success()->send();
                $this->loadMembers();
            });
    }

    public function removeMemberAction(): Action
    {
        return Action::make('removeMember')
            ->label('Keluarkan Anggota')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Keluarkan anggota')
            ->modalDescription(fn (array $arguments): string => 'Keluarkan "' . ($arguments['username'] ?? '') . '" dari "' . $this->selectedName . '"?')
            ->action(function (array $arguments): void {
                if (! $this->selectedId) {
                    return;
                }

                $this->kc()->removeUserFromGroup($arguments['userId'], $this->selectedId);
                Notification::make()->title('Anggota dikeluarkan')->success()->send();
                $this->loadMembers();
            });
    }
}
