<?php

namespace App\Filament\Keycloak\Pages;

use App\Services\Keycloak\KeycloakAdmin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

/**
 * Kelola CLIENT ROLE Keycloak — langkah "1 -> 2" dari rantai role (role
 * didefinisikan di sini per client, baru nanti di-assign ke user/grup supaya
 * ikut ke token & bisa disinkron RoleSync di sisi aplikasi client). Role
 * Keycloak sifatnya PER CLIENT (bukan realm-wide), makanya wajib pilih
 * client dulu sebelum kelola role-nya.
 *
 * Butuh role tambahan di service account (selain view-users/manage-users
 * yang dipakai halaman lain): realm-management -> manage-clients.
 */
class KeycloakRoles extends Page
{
    protected string $view = 'filament.keycloak.pages.keycloak-roles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $title = 'Keycloak Roles';

    protected static ?string $slug = 'roles';

    protected static ?int $navigationSort = 5;

    // uuid internal client Keycloak (dipakai buat panggil API), bukan clientId (nama teknis).
    public ?string $selectedClientUuid = null;

    public ?string $selectedClientLabel = null;

    /** @var array<int, array<string, mixed>> */
    public array $roles = [];

    public ?string $rolesError = null;

    protected function kc(): KeycloakAdmin
    {
        return app(KeycloakAdmin::class);
    }

    /** @return array<string, string> uuid client => label buat dropdown */
    public function clientOptions(): array
    {
        return collect($this->kc()->clients(null, 0, 100))
            ->mapWithKeys(fn (array $c): array => [
                $c['id'] => $c['clientId'] . (filled($c['name'] ?? null) ? " — {$c['name']}" : ''),
            ])
            ->all();
    }

    public function updatedSelectedClientUuid(): void
    {
        $options = $this->clientOptions();
        $this->selectedClientLabel = $options[$this->selectedClientUuid] ?? null;
        $this->loadRoles();
    }

    public function loadRoles(): void
    {
        if (! $this->selectedClientUuid) {
            $this->roles = [];
            $this->rolesError = null;

            return;
        }

        try {
            $this->rolesError = null;
            $this->roles = $this->kc()->clientRoles($this->selectedClientUuid);
        } catch (Throwable $e) {
            $this->roles = [];
            $this->rolesError = $e->getMessage();
        }
    }

    // Dipicu dari wire:click di blade (mount dari sisi PHP, bukan object-literal di
    // wire:click, biar tidak bergantung parsing JS-ish yang pernah bermasalah — lihat
    // KeycloakGroups).
    public function newRole(): void
    {
        if ($this->selectedClientUuid) {
            $this->mountAction('createRole');
        }
    }

    public function promptEditRole(string $name, ?string $description): void
    {
        $this->mountAction('editRole', ['name' => $name, 'description' => $description]);
    }

    public function promptDeleteRole(string $name): void
    {
        $this->mountAction('deleteRole', ['name' => $name]);
    }

    public function createRoleAction(): Action
    {
        return Action::make('createRole')
            ->label('Buat Role')
            ->modalHeading(fn (): string => 'Buat Role di "' . $this->selectedClientLabel . '"')
            ->schema([
                TextInput::make('name')
                    ->label('Nama Role')
                    ->required()
                    ->helperText('Nama teknis — ini yang muncul di claim resource_access token, dan yang dipetakan lewat config(\'keycloak-sso.roles.map\') di sisi aplikasi client.'),
                Textarea::make('description')->label('Deskripsi')->rows(2),
            ])
            ->action(function (array $data): void {
                $this->kc()->createClientRole($this->selectedClientUuid, $data['name'], $data['description'] ?: null);
                Notification::make()->title('Role dibuat')->success()->send();
                $this->loadRoles();
            });
    }

    public function editRoleAction(): Action
    {
        return Action::make('editRole')
            ->label('Ubah')
            ->icon('heroicon-o-pencil-square')
            ->fillForm(fn (array $arguments): array => ['description' => $arguments['description'] ?? null])
            ->schema([
                Textarea::make('description')->label('Deskripsi')->rows(2),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->kc()->updateClientRole($this->selectedClientUuid, $arguments['name'], [
                    'description' => $data['description'] ?: null,
                ]);
                Notification::make()->title('Role diperbarui')->success()->send();
                $this->loadRoles();
            });
    }

    public function deleteRoleAction(): Action
    {
        return Action::make('deleteRole')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (array $arguments): string => 'Hapus role "' . ($arguments['name'] ?? '') . '"? User/grup yang sudah dapat role ini kehilangan akses tersebut.')
            ->action(function (array $arguments): void {
                $this->kc()->deleteClientRole($this->selectedClientUuid, $arguments['name']);
                Notification::make()->title('Role dihapus')->success()->send();
                $this->loadRoles();
            });
    }
}
