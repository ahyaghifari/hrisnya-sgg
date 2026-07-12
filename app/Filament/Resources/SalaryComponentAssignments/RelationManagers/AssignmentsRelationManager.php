<?php

namespace App\Filament\Resources\SalaryComponentAssignments\RelationManagers;

use App\Enum\Salary\CalculationTypeEnum;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Pangkats;
use App\Models\Payroll\SalaryComponentAssignments;
use App\Models\SalaryComponent\SalaryComponents;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Daftar Mapping';

    // Filament defaultnya bikin relation manager read-only (sembunyiin Buat/Ubah/Hapus) kalau
    // ditaruh di halaman View — di sini justru situ tempat CRUD-nya, jadi harus dimatiin.
    public function isReadOnly(): bool
    {
        return false;
    }

    // Default-nya lazy-load (baru render pas discroll ke viewport) — di halaman View ini
    // relation manager-nya konten utama, jadi harus langsung render.
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('salaryable'))
            ->columns([
                TextColumn::make('peruntukan')
                    ->label('Peruntukan')
                    ->getStateUsing(fn (SalaryComponentAssignments $record) => $record->salaryable?->name ?? '-')
                    ->description(fn (SalaryComponentAssignments $record) => match ($record->salaryable_type) {
                        Companies::class => 'Company',
                        Pangkats::class => 'Pangkat',
                        JobPositions::class => 'Jabatan',
                        default => null,
                    }),

                TextColumn::make('nilai')
                    ->label('Nilai')
                    ->getStateUsing(function (SalaryComponentAssignments $record) {
                        $calcType = CalculationTypeEnum::tryFrom($this->getOwnerRecord()->calculation_type);

                        return match ($calcType) {
                            CalculationTypeEnum::FIXED => 'Rp ' . number_format((float) $record->amount, 0, ',', '.'),
                            CalculationTypeEnum::PERCENTAGE => number_format((float) $record->percentage, 0, ',', '.') . '%',
                            default => '-',
                        };
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Mapping')
                    ->modalHeading('Tambah Mapping Komponen Gaji')
                    ->slideOver()
                    ->modalWidth('lg')
                    ->schema(fn () => self::formSchema($this->getOwnerRecord()))
                    ->mutateDataUsing(fn (array $data) => self::mutateSalaryableId($data, $this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah')
                    ->modalHeading('Ubah Mapping Komponen Gaji')
                    ->slideOver()
                    ->modalWidth('lg')
                    ->schema(fn () => self::formSchema($this->getOwnerRecord(), isEdit: true)),

                DeleteAction::make()
                    ->label('Hapus'),
            ]);
    }

    /**
     * Jaring pengaman server-side — afterStateUpdated() di salaryable_type cuma jalan pas
     * user beneran ganti pilihan di browser, jadi kalau form disubmit dengan salaryable_type
     * = Company tanpa lewat interaksi itu (mis. data di-set langsung), salaryable_id bisa
     * kosong. Dipaksa benar di sini sebelum disimpan.
     */
    private static function mutateSalaryableId(array $data, SalaryComponents $ownerRecord): array
    {
        if (($data['salaryable_type'] ?? null) === Companies::class) {
            $data['salaryable_id'] = $ownerRecord->company_id;
        }

        return $data;
    }

    private static function formSchema(SalaryComponents $ownerRecord, bool $isEdit = false): array
    {
        $calcType = CalculationTypeEnum::tryFrom($ownerRecord->calculation_type);
        $companyId = $ownerRecord->company_id;

        return [
            Select::make('salaryable_type')
                ->label('Untuk')
                ->options([
                    Companies::class => 'Perusahaan (langsung)',
                    Pangkats::class => 'Pangkat',
                    JobPositions::class => 'Jabatan',
                ])
                ->required()
                ->live()
                ->disabled($isEdit)
                ->dehydrated(! $isEdit)
                ->afterStateUpdated(fn (callable $set, ?string $state) => $set(
                    'salaryable_id',
                    $state === Companies::class ? $companyId : null,
                )),

            // Kalau "Untuk" = Perusahaan langsung, nilainya sudah pasti company_id milik
            // komponen ini sendiri (di-set otomatis di atas) — nggak perlu dipilih manual.
            Select::make('salaryable_id')
                ->label('Pilih')
                ->required()
                ->searchable()
                ->visible(fn (callable $get) => $get('salaryable_type') !== Companies::class)
                ->disabled($isEdit)
                ->dehydrated(! $isEdit)
                ->options(function (callable $get) use ($companyId) {
                    return match ($get('salaryable_type')) {
                        Pangkats::class => Pangkats::where('company_id', $companyId)->pluck('name', 'id'),
                        JobPositions::class => JobPositions::where('company_id', $companyId)->pluck('name', 'id'),
                        default => [],
                    };
                }),

            TextInput::make('amount')
                ->label('Nilai (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->visible($calcType === CalculationTypeEnum::FIXED),

            TextInput::make('percentage')
                ->label('Persentase (%)')
                ->numeric()
                ->suffix('%')
                ->visible($calcType === CalculationTypeEnum::PERCENTAGE),
        ];
    }
}
