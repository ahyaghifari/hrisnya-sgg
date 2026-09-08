<?php

namespace App\Filament\Resources\UserContracts;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\UserContracts\Pages\ManageUserContracts;
use App\Filament\Resources\UserContracts\Tables\UserContractsTable;
use App\Models\Master\EmployeeTypes;
use App\Models\User\UserContract;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Biasanya ledger ini ke-generate otomatis lewat UserTransitionService::approve() (Pengangkatan
 * Orientasi/Perpanjangan Kontrak) - resource ini nambah jalur MANUAL (mis. migrasi data lama,
 * koreksi baris yang salah/hilang) tanpa harus lewat alur approval transisi penuh.
 * source_transition_id sengaja tidak ada di form - baris manual dari sini memang tidak
 * bersumber dari transisi manapun (kolomnya nullable).
 */
class UserContractResource extends BaseCompanyResource
{
    protected static ?string $model = UserContract::class;

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Data Kontrak';

    protected static ?string $modelLabel = 'Kontrak';

    protected static ?string $pluralModelLabel = 'Data Kontrak';

    // ManpowerRequestResource tidak set navigationSort (default -1) - dipaksa di bawah -1 biar
    // selalu di atasnya, bukan angka acak berdasar urutan discovery.
    protected static ?int $navigationSort = -2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                Select::make('user_id')
                    ->label('Karyawan')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('employee_type_id')
                    ->label('Tipe Karyawan')
                    ->options(fn (Select $component) => EmployeeTypes::query()
                        ->when(
                            ! static::canBrowseAllCompanies(),
                            fn ($q) => $q->where('company_id', static::companyId())
                        )
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('contract_start_date')
                    ->label('Mulai')
                    ->required(),

                DatePicker::make('contract_end_date')
                    ->label('Sampai')
                    ->required()
                    ->afterOrEqual('contract_start_date'),

                FileUpload::make('file_path')
                    ->label('Surat Kontrak')
                    ->columnSpanFull()
                    ->openable(true)
                    ->disk('public')
                    ->maxSize(10240)
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->directory(fn (Get $get) => 'user-contracts/' . ($get('user_id') ?? 'unknown')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return UserContractsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserContracts::route('/'),
        ];
    }
}
