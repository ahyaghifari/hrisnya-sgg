<?php

namespace App\Filament\Keycloak\Pages;

use App\Models\User;
use App\Services\Keycloak\KeycloakAdmin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class KeycloakUsers extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.keycloak.pages.keycloak-users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $title = 'Keycloak Users';

    protected static ?string $slug = '/';

    protected static ?int $navigationSort = 1;

    // Filter by grup: root + sub-grup level 1.
    public ?string $filterRootGroup = null;

    public ?string $filterSubGroup = null;

    protected function kc(): KeycloakAdmin
    {
        return app(KeycloakAdmin::class);
    }

    /** @return array<string, string> id => nama */
    public function rootGroupOptions(): array
    {
        return collect($this->kc()->groups(null, 0, 200))
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> id => nama (sub-grup dari root terpilih) */
    public function subGroupOptions(): array
    {
        if (! $this->filterRootGroup) {
            return [];
        }

        return collect($this->kc()->childGroups($this->filterRootGroup, null, 0, 200))
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedFilterRootGroup(): void
    {
        $this->filterSubGroup = null;
        $this->resetTable();
    }

    public function updatedFilterSubGroup(): void
    {
        $this->resetTable();
    }

    public function resetGroupFilter(): void
    {
        $this->filterRootGroup = null;
        $this->filterSubGroup = null;
        $this->resetTable();
    }

    /** attributes payload buat Keycloak; merge biar atribut lain user tidak kehapus. */
    private function withNip(array $existingAttributes, ?string $nip): array
    {
        $attributes = $existingAttributes;

        if (filled($nip)) {
            $attributes['nip'] = [$nip];
        } else {
            unset($attributes['nip']);
        }

        return $attributes;
    }

    /** Nama karyawan HRIS yang tersambung ke user Keycloak ini (keycloak_sub dulu, fallback NIP). */
    private function hrisNameFor(array $record): ?string
    {
        $name = User::where('keycloak_sub', $record['id'])->value('name');

        if (! $name && ($nip = $this->kc()->nipOf($record))) {
            $name = User::where('nip', $nip)->value('name');
        }

        return $name;
    }

    /** Select karyawan HRIS: value = nip, buat autofill form. */
    private function hrisUserOptions(?string $search): array
    {
        return User::query()
            ->whereNotNull('nip')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('nip', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->nip => "{$u->nip} — {$u->name}"])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int $page, int $recordsPerPage): array {
                $first = ($page - 1) * $recordsPerPage;
                $groupId = $this->filterSubGroup ?: $this->filterRootGroup;

                // Filter grup aktif: tampilkan anggota grup itu (search teks diabaikan).
                return $groupId
                    ? $this->kc()->groupMembers($groupId, $first, $recordsPerPage)
                    : $this->kc()->users($search, $first, $recordsPerPage);
            })
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('username')->label('Username')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->state(fn (array $record): ?string => $this->kc()->nipOf($record))
                    ->searchable(),
                TextColumn::make('hris')
                    ->label('Karyawan HRIS')
                    // ponytail: 1 query per baris (maks 50). Ganti ke whereIn kalau lambat.
                    ->state(fn (array $record): string => $this->hrisNameFor($record) ?? '— tidak ada di HRIS —')
                    ->color(fn (string $state): string => str_starts_with($state, '—') ? 'gray' : 'success'),
                IconColumn::make('enabled')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Buat User')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Select::make('hris_nip')
                            ->label('Ambil dari Karyawan HRIS')
                            ->helperText('Pilih untuk mengisi otomatis. Boleh dikosongi lalu isi manual.')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => $this->hrisUserOptions($search))
                            ->getOptionLabelUsing(fn ($value) => optional(User::where('nip', $value)->first(), fn ($u) => "{$u->nip} — {$u->name}"))
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (! $state || ! ($u = User::where('nip', $state)->first())) {
                                    return;
                                }
                                [$first, $last] = array_pad(explode(' ', trim($u->name), 2), 2, '');
                                $set('username', $u->nip);
                                $set('email', $u->email);
                                $set('firstName', $first);
                                $set('lastName', $last);
                                $set('nip', $u->nip);
                            }),
                        TextInput::make('username')->required()->helperText('Default NIP — boleh diubah kalau perlu.'),
                        TextInput::make('email')->email(),
                        TextInput::make('firstName')->label('Nama Depan'),
                        TextInput::make('lastName')->label('Nama Belakang'),
                        TextInput::make('nip')->label('NIP')->helperText('Disimpan ke atribut Keycloak "nip" — kunci sambungan ke HRIS.'),
                        TextInput::make('password')->label('Password Awal')->password()->revealable()->required(),
                        Toggle::make('temporary')->label('Wajib ganti password saat login')->default(false),
                    ])
                    ->action(function (array $data): void {
                        $id = $this->kc()->create([
                            'username' => $data['username'],
                            'email' => $data['email'] ?: null,
                            'firstName' => $data['firstName'] ?: null,
                            'lastName' => $data['lastName'] ?: null,
                            'enabled' => true,
                            'attributes' => $this->withNip([], $data['nip'] ?? null),
                        ]);
                        $this->kc()->setPassword($id, $data['password'], (bool) $data['temporary']);

                        // Simpan link ke HRIS: keycloak_sub = id user Keycloak (1:1).
                        $nip = $data['nip'] ?: $data['username'];
                        User::where('nip', $nip)->update(['keycloak_sub' => $id]);

                        Notification::make()->title('User dibuat')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (array $record): string => 'User: ' . ($record['username'] ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (array $record) => view('filament.keycloak.pages._user-detail', [
                        'user' => $record,
                        'nip' => $this->kc()->nipOf($record),
                        'hrisName' => $this->hrisNameFor($record),
                        'groups' => $this->kc()->userGroups($record['id']),
                    ])),

                Action::make('toggleEnabled')
                    ->label(fn (array $record): string => ($record['enabled'] ?? false) ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon('heroicon-o-power')
                    ->color(fn (array $record): string => ($record['enabled'] ?? false) ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        $this->kc()->setEnabled($record['id'], ! ($record['enabled'] ?? false));
                        Notification::make()->title('Status user diperbarui')->success()->send();
                    }),

                Action::make('delete')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (array $record): string => 'Hapus user "' . ($record['username'] ?? '') . '" dari Keycloak?')
                    ->action(function (array $record): void {
                        $this->kc()->delete($record['id']);
                        Notification::make()->title('User dihapus')->success()->send();
                    }),

                ActionGroup::make([
                Action::make('edit')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (array $record): array => [
                        'username' => $record['username'] ?? null,
                        'email' => $record['email'] ?? null,
                        'firstName' => $record['firstName'] ?? null,
                        'lastName' => $record['lastName'] ?? null,
                        'nip' => $this->kc()->nipOf($record),
                        'enabled' => $record['enabled'] ?? false,
                    ])
                    ->schema([
                        TextInput::make('username')->required(),
                        TextInput::make('email')->email(),
                        TextInput::make('firstName')->label('Nama Depan'),
                        TextInput::make('lastName')->label('Nama Belakang'),
                        TextInput::make('nip')->label('NIP')->helperText('Atribut Keycloak "nip" — kunci sambungan ke HRIS.'),
                        Toggle::make('enabled')->label('Aktif'),
                    ])
                    ->action(function (array $record, array $data): void {
                        $this->kc()->update($record['id'], [
                            'username' => $data['username'],
                            'email' => $data['email'] ?: null,
                            'firstName' => $data['firstName'] ?: null,
                            'lastName' => $data['lastName'] ?: null,
                            'enabled' => (bool) $data['enabled'],
                            'attributes' => $this->withNip($record['attributes'] ?? [], $data['nip'] ?? null),
                        ]);
                        Notification::make()->title('User diperbarui')->success()->send();
                    }),

                Action::make('link')
                    ->label('Hubungkan ke HRIS')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Select::make('hris_nip')
                            ->label('Karyawan HRIS')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => $this->hrisUserOptions($search))
                            ->getOptionLabelUsing(fn ($value) => optional(User::where('nip', $value)->first(), fn ($u) => "{$u->nip} — {$u->name}")),
                    ])
                    ->action(function (array $record, array $data): void {
                        User::where('nip', $data['hris_nip'])->update(['keycloak_sub' => $record['id']]);
                        Notification::make()->title('Terhubung ke karyawan HRIS')->success()->send();
                    }),

                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextInput::make('password')->label('Password Baru')->password()->revealable()->required(),
                        Toggle::make('temporary')->label('Sementara (wajib ganti saat login)')->default(false),
                    ])
                    ->action(function (array $record, array $data): void {
                        $this->kc()->setPassword($record['id'], $data['password'], (bool) $data['temporary']);
                        Notification::make()->title('Password direset')->success()->send();
                    }),

                Action::make('addToGroup')
                    ->label('Tambah ke Grup')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Select::make('group_id')
                            ->label('Grup')
                            ->required()
                            ->searchable()
                            ->options(fn (): array => collect($this->kc()->flatGroups())
                                ->mapWithKeys(fn (array $g): array => [$g['id'] => $g['path']])
                                ->all()),
                    ])
                    ->action(function (array $record, array $data): void {
                        $this->kc()->addUserToGroup($record['id'], $data['group_id']);
                        Notification::make()->title('User ditambahkan ke grup')->success()->send();
                    }),

                Action::make('removeFromGroup')
                    ->label('Keluarkan dari Grup')
                    ->icon('heroicon-o-user-minus')
                    ->schema([
                        Select::make('group_id')
                            ->label('Grup')
                            ->required()
                            ->options(fn (array $record): array => collect($this->kc()->userGroups($record['id']))
                                ->mapWithKeys(fn (array $g): array => [$g['id'] => $g['path'] ?? $g['name']])
                                ->all()),
                    ])
                    ->action(function (array $record, array $data): void {
                        $this->kc()->removeUserFromGroup($record['id'], $data['group_id']);
                        Notification::make()->title('User dikeluarkan dari grup')->success()->send();
                    }),
                ]),
            ]);
    }
}
