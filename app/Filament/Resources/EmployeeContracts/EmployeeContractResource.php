<?php

namespace App\Filament\Resources\EmployeeContracts;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\EmployeeContracts\Pages\ManageEmployeeContracts;
use App\Filament\Resources\EmployeeContracts\Tables\EmployeeContractsTable;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Master\EmployeeTypes;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Biasanya ledger ini ke-generate otomatis lewat UserTransitionService::approve() (Pengangkatan
 * Orientasi/Perpanjangan Kontrak) - resource ini nambah jalur MANUAL (mis. migrasi data lama,
 * koreksi baris yang salah/hilang) tanpa harus lewat alur approval transisi penuh.
 * source_transition_id sengaja tidak ada di form - baris manual dari sini memang tidak
 * bersumber dari transisi manapun (kolomnya nullable). Hanya baris type 'kontrak' di
 * employee_contracts; baris orientasi dikelola EmployeeOrientationResource.
 */
class EmployeeContractResource extends BaseCompanyResource
{
    protected static ?string $model = EmployeeContract::class;

    protected static ?string $slug = 'employee-contracts';

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Data Kontrak';

    protected static ?string $modelLabel = 'Kontrak';

    protected static ?string $pluralModelLabel = 'Data Kontrak';

    // Urutan grup Kepegawaian: Karyawan (1), Data Eksekutif (2), Data Orientasi (3), Data Kontrak (4),
    // Perubahan Status Karyawan (5), Permintaan Tenaga Kerja (6), Dokumen (7), Jabatan (8).
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->kontrak();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                Hidden::make('type')->default(EmployeeContract::TYPE_KONTRAK),

                Select::make('employee_id')
                    ->label('Karyawan')
                    ->options(fn () => Employee::query()->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id'))
                    ->searchable()
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

                DatePicker::make('start_date')
                    ->label('Mulai')
                    ->required(),

                DatePicker::make('end_date')
                    ->label('Sampai')
                    ->required()
                    ->afterOrEqual('start_date'),

                FileUpload::make('file_path')
                    ->label('Surat Kontrak')
                    ->columnSpanFull()
                    ->openable(true)
                    ->disk('public')
                    ->maxSize(10240)
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->directory(fn (Get $get) => 'user-contracts/' . ($get('employee_id') ?? 'unknown')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return EmployeeContractsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeContracts::route('/'),
        ];
    }
}
