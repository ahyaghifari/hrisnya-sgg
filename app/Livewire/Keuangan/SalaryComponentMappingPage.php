<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Salary\CalculationTypeEnum;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Pangkats;
use App\Models\Payroll\SalaryComponentAssignments;
use App\Models\SalaryComponent\SalaryComponents;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Mapping Komponen Gaji" versi portal — BEDA dari admin (yang ringkasan per komponen
 * + drill-down RelationManager). Ini 1 halaman flat, di-scope ke 1 company: tabel gabungan
 * semua assignment company itu (langsung ke company, ke pangkat-pangkatnya, ke jabatan-
 * jabatannya). Lihat planning/2026-07-12-portal-menu-keuangan-payroll.md §D.
 */
#[Layout('components.layout', ['title' => 'Mapping Komponen Gaji'])]
class SalaryComponentMappingPage extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?string $companyId = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        if (! PermissionHelper::hasGlobalRole($user)) {
            $this->companyId = $user->company_id;
        }
    }

    public function canBrowseAllCompanies(): bool
    {
        return PermissionHelper::hasGlobalRole(Auth::user());
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::query()->orderBy('name')->pluck('name', 'id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->companyId
                ? self::scopeToCompany(SalaryComponentAssignments::query()->with(['salaryComponent', 'salaryable']), $this->companyId)
                : SalaryComponentAssignments::query()->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('salaryComponent.name')
                    ->label('Komponen')
                    ->searchable(),

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
                        $calcType = CalculationTypeEnum::tryFrom($record->salaryComponent?->calculation_type);

                        return match ($calcType) {
                            CalculationTypeEnum::FIXED => 'Rp ' . number_format((float) $record->amount, 0, ',', '.'),
                            CalculationTypeEnum::PERCENTAGE => number_format((float) $record->percentage, 0, ',', '.') . '%',
                            default => '-',
                        };
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth('lg')
                    ->disabled(! $this->companyId),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('lg'),

                DeleteAction::make(),
            ])
            ->emptyStateHeading($this->companyId ? 'Belum ada mapping komponen gaji' : 'Pilih perusahaan dulu');
    }

    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        $companyId = $this->companyId;

        return match (true) {
            $action instanceof CreateAction => fn (Schema $schema): Schema => $schema->components(self::createSchema($companyId)),
            $action instanceof EditAction => fn (Schema $schema): Schema => $schema->components(self::editSchema()),
            default => null,
        };
    }

    private static function createSchema(?string $companyId): array
    {
        return [
            Select::make('salary_component_id')
                ->label('Komponen Gaji')
                ->options(fn () => SalaryComponents::query()->where('company_id', $companyId)->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live(),

            Select::make('salaryable_type')
                ->label('Untuk')
                ->options([
                    Companies::class => 'Perusahaan (langsung)',
                    Pangkats::class => 'Pangkat',
                    JobPositions::class => 'Jabatan',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set, ?string $state) => $set(
                    'salaryable_id',
                    $state === Companies::class ? $companyId : null,
                )),

            Select::make('salaryable_id')
                ->label('Pilih')
                ->required()
                ->searchable()
                ->visible(fn (callable $get) => $get('salaryable_type') !== Companies::class)
                ->options(fn (callable $get) => match ($get('salaryable_type')) {
                    Pangkats::class => Pangkats::where('company_id', $companyId)->pluck('name', 'id'),
                    JobPositions::class => JobPositions::where('company_id', $companyId)->pluck('name', 'id'),
                    default => [],
                }),

            TextInput::make('amount')
                ->label('Nilai (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->visible(fn (callable $get) => self::calculationTypeOf($get('salary_component_id')) === CalculationTypeEnum::FIXED->value),

            TextInput::make('percentage')
                ->label('Persentase (%)')
                ->numeric()
                ->suffix('%')
                ->visible(fn (callable $get) => self::calculationTypeOf($get('salary_component_id')) === CalculationTypeEnum::PERCENTAGE->value),
        ];
    }

    private static function editSchema(): array
    {
        return [
            TextInput::make('amount')
                ->label('Nilai (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->visible(fn (SalaryComponentAssignments $record) => CalculationTypeEnum::tryFrom($record->salaryComponent?->calculation_type) === CalculationTypeEnum::FIXED),

            TextInput::make('percentage')
                ->label('Persentase (%)')
                ->numeric()
                ->suffix('%')
                ->visible(fn (SalaryComponentAssignments $record) => CalculationTypeEnum::tryFrom($record->salaryComponent?->calculation_type) === CalculationTypeEnum::PERCENTAGE),
        ];
    }

    private static function calculationTypeOf(?string $salaryComponentId): ?string
    {
        if (! $salaryComponentId) {
            return null;
        }

        return SalaryComponents::find($salaryComponentId)?->calculation_type;
    }

    private static function scopeToCompany(Builder $query, string $companyId): Builder
    {
        $pangkatIds = Pangkats::where('company_id', $companyId)->pluck('id');
        $jobPositionIds = JobPositions::where('company_id', $companyId)->pluck('id');

        return $query->where(function (Builder $q) use ($companyId, $pangkatIds, $jobPositionIds) {
            $q->where(fn (Builder $q2) => $q2->where('salaryable_type', Companies::class)->where('salaryable_id', $companyId))
                ->orWhere(fn (Builder $q2) => $q2->where('salaryable_type', Pangkats::class)->whereIn('salaryable_id', $pangkatIds))
                ->orWhere(fn (Builder $q2) => $q2->where('salaryable_type', JobPositions::class)->whereIn('salaryable_id', $jobPositionIds));
        });
    }

    public function render()
    {
        return view('livewire.keuangan.salary-component-mapping-page');
    }
}
