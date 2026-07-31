<?php

namespace App\Filament\Resources\UserKoperasis;

use App\Filament\Resources\UserKoperasis\Pages\ManageUserKoperasis;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Sederhana kayak master data (mis. Divisi): 1 file Resource, form/table inline, create/edit
 * lewat modal (ManageRecords) — bukan pola slide-over view+edit terpisah kayak Incentive.
 */
class UserKoperasiResource extends Resource
{
    protected static ?string $model = UserKoperasi::class;

    protected static ?string $navigationLabel = 'Koperasi';

    protected static ?string $modelLabel = 'Koperasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // company_id cuma dipakai buat nyaring pilihan Karyawan — bukan kolom asli
                // di tabel user_koperasi (company diturunkan dari user->company_id).
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()->pluck('name', 'id'))
                    ->default(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (callable $set) => $set('user_id', null)),

                Select::make('user_id')
                    ->label('Karyawan')
                    ->options(fn (callable $get) => $get('company_id')
                        ? User::query()
                            ->where('company_id', $get('company_id'))
                            ->whereHas('profile')
                            ->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Karyawan ini sudah jadi anggota koperasi — ubah dari daftar, bukan tambah lagi.',
                    ]),

                TextInput::make('amount')
                    ->label('Potongan Per Bulan')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0.01)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.nip')
                    ->label('NIP')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Potongan Per Bulan')
                    ->money('IDR', true)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()->pluck('name', 'id'))
                    ->preload()
                    ->searchable()
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $companyId) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $companyId)),
                    ))
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (UserKoperasi $record) => [
                        ...$record->attributesToArray(),
                        'company_id' => $record->user?->company_id,
                    ]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserKoperasis::route('/'),
        ];
    }
}
