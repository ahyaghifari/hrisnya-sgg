<?php

namespace App\Filament\Persuratan\Resources\Penggunas;

use App\Filament\Persuratan\Resources\Penggunas\Pages\ManagePenggunas;
use App\Models\Persuratan\User as Pengguna;
use App\Models\User as HrisUser;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Pengguna modul surat (tabel `users` di DB persuratan). `hris_user_id` = tautan opsional ke user HRIS —
 * kosong untuk orang di luar HRIS (mis. Koperasi). Perusahaan/unit/peran akan diatur lewat hak akses (belum ada);
 * kolom lama email/peran hanya ditampilkan (read-only) sebagai petunjuk sebelum dibuang.
 */
class PenggunaResource extends Resource
{
    protected static ?string $model = Pengguna::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        $labelHris = fn ($u) => trim("{$u->nip} — {$u->name}", ' —');
        $dariHris = fn (string $operation, Get $get) => $operation === 'create' && $get('sumber') === 'hris';

        return $schema->components([
            // Tab pilihan sumber — hanya saat membuat. Edit selalu menampilkan field lengkap.
            ToggleButtons::make('sumber')->label('Tambah pengguna dari')->inline()->grouped()->live()
                ->options(['hris' => 'Akun HRIS', 'manual' => 'Manual'])
                ->icons(['hris' => 'heroicon-m-building-office-2', 'manual' => 'heroicon-m-pencil-square'])
                ->default('hris')
                ->visible(fn (string $operation) => $operation === 'create')
                ->columnSpanFull(),

            Select::make('hris_user_id')->label('Akun HRIS')->searchable()->placeholder('Cari NIP atau nama…')
                ->required($dariHris)
                ->visible(fn (string $operation, Get $get) => $operation === 'edit' || $get('sumber') === 'hris')
                ->getSearchResultsUsing(fn (string $search, ?Pengguna $record) => HrisUser::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', Pengguna::whereNotNull('hris_user_id')->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->pluck('hris_user_id'))
                    ->searchNameOrNip($search)
                    ->orderByName()->limit(50)->get()
                    ->mapWithKeys(fn ($u) => [$u->id => $labelHris($u)]))
                ->getOptionLabelUsing(fn ($value) => ($u = HrisUser::find($value)) ? $labelHris($u) : null)
                ->rules([fn (string $operation) => function (string $attribute, $value, $fail) use ($operation) {
                    // Saat membuat dari HRIS: user belum tertaut, dan username turunannya (NIP) belum dipakai.
                    if ($operation !== 'create' || blank($value)) {
                        return;
                    }
                    if (Pengguna::where('hris_user_id', $value)->exists()) {
                        $fail('Akun HRIS ini sudah menjadi pengguna.');
                    } elseif (($u = HrisUser::find($value)) && Pengguna::where('username', static::usernameDariHris($u))->exists()) {
                        $fail('Username "'.static::usernameDariHris($u).'" sudah dipakai pengguna lain.');
                    }
                }])
                ->helperText(fn (string $operation) => $operation === 'create'
                    ? 'Nama dan username (NIP) diambil otomatis dari HRIS. Pengguna langsung dibuat.'
                    : 'Opsional. Kosongkan untuk orang di luar HRIS (mis. Koperasi).')
                ->columnSpanFull(),

            // Field manual: tampil saat edit, atau saat membuat dengan sumber "Manual".
            TextInput::make('nama_lengkap')->label('Nama Lengkap')->required()->maxLength(255)
                ->visible(fn (string $operation, Get $get) => $operation === 'edit' || $get('sumber') === 'manual'),

            TextInput::make('username')->label('Username')->required()->maxLength(50)
                ->unique(ignoreRecord: true)
                ->visible(fn (string $operation, Get $get) => $operation === 'edit' || $get('sumber') === 'manual')
                // 17 user lama belum punya username: sarankan dari bagian depan email (baru tersimpan kalau disimpan).
                ->afterStateHydrated(function (TextInput $component, $state, ?Pengguna $record) {
                    if (blank($state) && filled($record?->email)) {
                        $component->state(strtolower(strstr($record->email, '@', true) ?: ''));
                    }
                })
                ->helperText('Dipakai untuk login. Harus unik.'),

            TextInput::make('password')->label('Password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->visible(fn (string $operation, Get $get) => $operation === 'edit' || $get('sumber') === 'manual')
                ->minLength(8)
                ->dehydrated(fn ($state) => filled($state))
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 8 karakter.'),

            Select::make('status')->label('Status')->required()->default('aktif')
                ->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])
                ->helperText('Nonaktif = tidak bisa login, riwayat surat tetap tersimpan.'),
        ])->columns(2);
    }

    /** Username untuk user HRIS: NIP; cadangan bagian depan email. */
    public static function usernameDariHris(HrisUser $u): string
    {
        return filled($u->nip) ? (string) $u->nip : strtolower(strstr((string) $u->email, '@', true) ?: '');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_lengkap')->label('Nama')->searchable()->sortable(),
                TextColumn::make('username')->searchable()->sortable()->placeholder('— belum ada —'),
                TextColumn::make('status')->badge()->sortable()
                    ->color(fn (string $state) => $state === 'aktif' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('hris_user_id')->label('Akun HRIS')
                    ->formatStateUsing(fn ($state) => $state ? (HrisUser::find($state)?->name ?? 'tidak ditemukan') : '—'),
                TextColumn::make('surat_count')->label('Surat dibuat')->suffix(' surat')->numeric()->sortable(),
                TextColumn::make('peran')->label('Peran (lama)')->badge()->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->label('Email (lama)')->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCount('surat'))
            ->defaultSort('nama_lengkap')
            ->filters([
                SelectFilter::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']),
                TernaryFilter::make('hris')->label('Tertaut HRIS')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('hris_user_id'),
                        false: fn ($q) => $q->whereNull('hris_user_id'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Pengguna $record) => $record->surat_count > 0)
                    ->tooltip(fn (Pengguna $record) => $record->surat_count > 0
                        ? "Tidak bisa dihapus: pernah membuat {$record->surat_count} surat. Nonaktifkan saja."
                        : null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePenggunas::route('/')];
    }
}
