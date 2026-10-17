<?php

namespace App\Filament\Resources\EmployeeOrientations;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\EmployeeOrientations\Pages\ManageEmployeeOrientations;
use App\Filament\Resources\EmployeeOrientations\Tables\EmployeeOrientationsTable;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Biasanya baris ini ke-generate otomatis lewat CandidateOnboardingService (onboarding
 * Rekrutmen, source='recruitment') atau OrientationService::review() (perpanjangan,
 * source='extension') - resource ini nambah jalur MANUAL, karena integrasi onboarding
 * Rekrutmen->Orientasi belum jalan sepenuhnya sekarang (2026-09-05, instruksi user: "biar
 * fleksibel"). source/previous_contract_id sengaja tidak ada di form - baris manual dari
 * sini memang tidak bersumber dari periode manapun (kolomnya nullable), sama pola
 * EmployeeContractResource. Hanya baris type 'orientasi' di employee_contracts. Model dan policy
 * dipakai bersama EmployeeContractResource; izinnya tetap terpisah (`*:EmployeeOrientation`).
 */
class EmployeeOrientationResource extends BaseCompanyResource
{
    protected static ?string $model = EmployeeContract::class;

    protected static ?string $slug = 'employee-orientations';

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Data Orientasi';

    protected static ?string $modelLabel = 'Orientasi';

    protected static ?string $pluralModelLabel = 'Data Orientasi';

    // Urutan grup Kepegawaian: Karyawan (1), Data Eksekutif (2), Data Orientasi (3), Data Kontrak (4),
    // Perubahan Status Karyawan (5), Permintaan Tenaga Kerja (6), Dokumen (7), Jabatan (8).
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orientasi();
    }

    // Policy dipakai bersama kontrak; di tingkat kelas (tanpa baris) izin orientasi dicek eksplisit.
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:EmployeeOrientation');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('Create:EmployeeOrientation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                Hidden::make('type')->default(EmployeeContract::TYPE_ORIENTASI),

                Select::make('employee_id')
                    ->label('Karyawan')
                    ->options(fn () => Employee::query()->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id'))
                    ->searchable()
                    ->required(),

                DatePicker::make('start_date')
                    ->label('Mulai')
                    ->required(),

                DatePicker::make('end_date')
                    ->label('Sampai')
                    ->required()
                    ->afterOrEqual('start_date'),

                TextInput::make('duration_months')
                    ->label('Durasi (bulan)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                FileUpload::make('file_path')
                    ->label('Surat Orientasi')
                    ->columnSpanFull()
                    ->openable(true)
                    ->disk('public')
                    ->maxSize(10240)
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->directory(fn (Get $get) => 'user-orientations/' . ($get('employee_id') ?? 'unknown')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return EmployeeOrientationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeOrientations::route('/'),
        ];
    }
}
