<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Payroll\PayrollPeriods\Schemas\PayrollPeriodForm;
use App\Filament\Resources\Payroll\PayrollPeriods\Schemas\PayrollPeriodInfolist;
use App\Filament\Resources\Payroll\PayrollPeriods\Tables\PayrollPeriodsTable;
use App\Models\Payroll\PayrollPeriod;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versi portal dari PayrollPeriodResource (/admin/payroll-periods) — reuse Table/Form/Infolist
 * class Filament yang sama (pola sama IncentivesPage.php). Scoping company otomatis lewat
 * BelongsToCompany trait di model PayrollPeriod (bukan filter manual di halaman ini) — user
 * biasa cuma lihat company sendiri, Super Admin lihat semua.
 */
#[Layout('components.layout', ['title' => 'Periode Gaji'])]
class PayrollPeriodsPage extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);
    }

    public function table(Table $table): Table
    {
        return PayrollPeriodsTable::configure($table)
            ->query(PayrollPeriod::query())
            ->headerActions([
                CreateAction::make()
                    ->authorize('create', PayrollPeriod::class)
                    ->modalHeading('Tambah Periode Gaji')
                    ->modalWidth('md'),
            ]);
    }

    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        return match (true) {
            $action instanceof CreateAction, $action instanceof EditAction => fn (Schema $schema): Schema => PayrollPeriodForm::configure($schema),
            $action instanceof ViewAction => fn (Schema $schema): Schema => PayrollPeriodInfolist::configure($schema),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.keuangan.payroll-periods-page');
    }
}
