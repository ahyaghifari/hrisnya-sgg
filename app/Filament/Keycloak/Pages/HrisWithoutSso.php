<?php

namespace App\Filament\Keycloak\Pages;

use App\Models\Employee\Employee;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdmin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Daftar user HRIS yang BELUM punya akun SSO (users.keycloak_sub masih null).
 * Bisa langsung "Buat Akun SSO" dari sini — bikin user Keycloak + isi keycloak_sub.
 */
class HrisWithoutSso extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.keycloak.pages.hris-without-sso';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Belum Punya SSO';

    protected static ?string $title = 'Karyawan HRIS Tanpa SSO';

    protected static ?int $navigationSort = 2;

    protected function kc(): KeycloakAdmin
    {
        return app(KeycloakAdmin::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereNull('keycloak_sub')
                    ->whereIn('users.id', Employee::query()->select('user_id')->whereNotNull('nip'))
                    ->orderByName()
            )
            ->columns([
                TextColumn::make('nip')->label('NIP')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereNipLike($search))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByNip($direction)),
                TextColumn::make('name')->label('Nama')
                    ->searchable(query: fn (Builder $query, string $search) => $query->searchName($search))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByName($direction)),
                TextColumn::make('email')->label('Email')->searchable(),
            ])
            ->recordActions([
                Action::make('createSso')
                    ->label('Buat Akun SSO')
                    ->icon('heroicon-o-key')
                    ->modalHeading(fn (User $record) => "Buat Akun SSO — {$record->name}")
                    ->schema([
                        TextInput::make('password')
                            ->label('Password Awal')
                            ->password()
                            ->revealable()
                            ->required()
                            ->default(fn () => Str::password(12)),
                        Toggle::make('temporary')
                            ->label('Wajib ganti password saat login')
                            ->default(false),
                    ])
                    ->action(function (User $record, array $data): void {
                        [$first, $last] = array_pad(explode(' ', trim($record->name), 2), 2, '');

                        $id = $this->kc()->create([
                            'username' => $record->nip,
                            'email' => $record->email ?: null,
                            'firstName' => $first,
                            'lastName' => $last,
                            'enabled' => true,
                            'attributes' => ['nip' => [$record->nip]],
                        ]);
                        $this->kc()->setPassword($id, $data['password'], (bool) $data['temporary']);

                        $record->forceFill(['keycloak_sub' => $id])->save();

                        Notification::make()->title('Akun SSO dibuat')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Semua karyawan (ber-NIP) sudah punya akun SSO')
            ->recordActionsColumnLabel('Aksi');
    }
}
