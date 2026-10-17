<?php

namespace App\Filament\Resources\EmployeeKoperasis;

use App\Filament\Resources\EmployeeKoperasis\Pages\ManageEmployeeKoperasis;
use App\Helpers\PermissionHelper;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeKoperasi;
use App\Models\Master\Companies;
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
class EmployeeKoperasiResource extends Resource
{
    protected static ?string $model = EmployeeKoperasi::class;

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
                // di tabel employee_koperasi (company diturunkan dari employee->company_id).
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()->pluck('name', 'id'))
                    ->default(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (callable $set) => $set('employee_id', null)),

                Select::make('employee_id')
                    ->label('Karyawan')
                    ->options(fn (callable $get) => $get('company_id')
                        ? Employee::query()
                            ->where('company_id', $get('company_id'))
                            ->orderBy('nama_lengkap')
                            ->pluck('nama_lengkap', 'id')
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
                TextColumn::make('employee.company.name')
                    ->label('Perusahaan')
                    ->sortable(),
                TextColumn::make('employee.nama_lengkap')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.nip')
                    ->label('NIP')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Potongan Per Bulan')
                    ->money('IDR', true),
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
                        fn ($q, $companyId) => $q->whereHas('employee', fn ($q2) => $q2->where('company_id', $companyId)),
                    ))
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (EmployeeKoperasi $record) => [
                        ...$record->attributesToArray(),
                        'company_id' => $record->employee?->company_id,
                    ]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeKoperasis::route('/'),
        ];
    }
}
