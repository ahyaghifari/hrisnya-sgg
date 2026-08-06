<?php

namespace App\Filament\Pages\PayrollComponents;

use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PayrollComponents extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.payroll-components.payroll-components';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Komponen Gaji';

    protected static ?string $title = 'Komponen Gaji';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->companiesQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payroll_components_count')
                    ->label('Jumlah Komponen Aktif')
                    ->counts(['payrollComponents' => fn (Builder $query) => $query->where('is_active', true)])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('akses')
                    ->label('Akses')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Companies $record) => PayrollComponentDetail::getUrl(['company' => $record->id])),
            ]);
    }

    private function companiesQuery(): Builder
    {
        $user = Auth::user();

        $query = Companies::query()->orderBy('name');

        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            $query->where('id', $user->company_id);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('View:PayrollComponents');
    }
}
