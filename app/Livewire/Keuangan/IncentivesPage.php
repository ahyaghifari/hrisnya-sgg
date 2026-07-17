<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Incentives\Schemas\IncentiveForm;
use App\Filament\Resources\Incentives\Tables\IncentivesTable;
use App\Models\Incentive\Incentive;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
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
 * Versi portal dari IncentiveResource (/admin/incentives) — reuse Table & Form class Filament
 * yang sama. Lihat planning/2026-07-12-portal-menu-keuangan-payroll.md.
 */
#[Layout('components.layout', ['title' => 'Insentif'])]
class IncentivesPage extends Component implements HasActions, HasSchemas, HasTable
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
        return IncentivesTable::configure($table)
            ->query(Incentive::query())
            ->headerActions([
                CreateAction::make()
                    ->modalWidth('md'),
            ]);
    }

    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        return match (true) {
            $action instanceof CreateAction, $action instanceof EditAction => fn (Schema $schema): Schema => IncentiveForm::configure($schema),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.keuangan.incentives-page');
    }
}
