<?php

namespace App\Filament\Rekrutmen\Resources\PelamarUsers;

use App\Filament\Rekrutmen\Resources\PelamarUsers\Pages\ManagePelamarUsers;
use App\Models\Rekrutmen\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * Kelola akun pelamar (DB `rekrutmen_db`, tabel `users`) — cuma reset password & kosongkan
 * verifikasi email buat bantu kandidat yang lupa password/tidak terima email verifikasi.
 * Bukan tempat bikin akun baru (registrasi tetap dari portal pelamar project `rekrutmen`).
 */
class PelamarUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Akun Pelamar';

    protected static ?string $modelLabel = 'Akun Pelamar';

    protected static ?string $pluralModelLabel = 'Akun Pelamar';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return filament()->auth()->user()->hasRole(['Admin', 'Super Admin']);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('is_staff', false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->sortable(),
                TextColumn::make('nik')->label('NIK')->searchable()->placeholder('—'),
                IconColumn::make('email_verified_at')
                    ->label('Terverifikasi')
                    ->boolean()
                    ->state(fn ($record) => $record->email_verified_at !== null),
                TextColumn::make('created_at')->label('Daftar')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-m-key')
                    ->color('warning')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])
                    ->modalHeading(fn ($record) => 'Reset Password — ' . $record->name)
                    ->modalSubmitActionLabel('Simpan')
                    ->action(function (array $data, $record): void {
                        $record->update(['password' => Hash::make($data['password'])]);
                        Notification::make()->title('Password akun pelamar diperbarui')->success()->send();
                    }),

                Action::make('clearEmailVerification')
                    ->label('Kosongkan Verifikasi Email')
                    ->icon('heroicon-m-envelope')
                    ->color('gray')
                    ->visible(fn ($record) => $record->email_verified_at !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Kosongkan verifikasi email?')
                    ->modalDescription('Kandidat perlu verifikasi ulang emailnya sebelum bisa lanjut memakai akun.')
                    ->action(function ($record): void {
                        $record->update(['email_verified_at' => null]);
                        Notification::make()->title('Verifikasi email dikosongkan')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePelamarUsers::route('/'),
        ];
    }
}
