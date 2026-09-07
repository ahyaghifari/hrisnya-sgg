<?php

namespace App\Filament\Resources\UserOrientations;

use App\Enum\EmployeeTypeCategory;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\UserOrientations\Pages\ManageUserOrientations;
use App\Filament\Resources\UserOrientations\Tables\UserOrientationsTable;
use App\Models\User;
use App\Models\User\UserOrientation;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Biasanya baris ini ke-generate otomatis lewat CandidateOnboardingService (onboarding
 * Rekrutmen, source='recruitment') atau OrientationService::review() (perpanjangan,
 * source='extension') - resource ini nambah jalur MANUAL, karena integrasi onboarding
 * Rekrutmen->Orientasi belum jalan sepenuhnya sekarang (2026-09-05, instruksi user: "biar
 * fleksibel"). source/previous_orientation_id sengaja tidak ada di form - baris manual dari
 * sini memang tidak bersumber dari periode manapun (kolomnya nullable), sama pola
 * UserContractResource. Pilihan karyawan dibatasi kategori ORIENTASI (dropdown + rule).
 */
class UserOrientationResource extends BaseCompanyResource
{
    protected static ?string $model = UserOrientation::class;

    protected static string|UnitEnum|null $navigationGroup = 'Kontrak & Orientasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Data Orientasi';

    protected static ?string $modelLabel = 'Orientasi';

    protected static ?string $pluralModelLabel = 'Data Orientasi';

    protected static ?int $navigationSort = -2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                Select::make('user_id')
                    ->label('Karyawan')
                    ->relationship(
                        'user',
                        'name',
                        fn (Builder $query) => $query->whereHas(
                            'profile.employeeType',
                            fn ($q) => $q->where('category', EmployeeTypeCategory::ORIENTASI->value),
                        ),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Hanya karyawan berstatus Orientasi.')
                    ->rule(static function () {
                        return static function (string $attribute, $value, \Closure $fail) {
                            $isOrientasi = User::whereKey($value)
                                ->whereHas('profile.employeeType', fn ($q) => $q->where('category', EmployeeTypeCategory::ORIENTASI->value))
                                ->exists();

                            if (! $isOrientasi) {
                                $fail('Karyawan yang dipilih bukan berstatus Orientasi.');
                            }
                        };
                    }),

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
                    ->directory(fn (Get $get) => 'user-orientations/' . ($get('user_id') ?? 'unknown')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return UserOrientationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserOrientations::route('/'),
        ];
    }
}
