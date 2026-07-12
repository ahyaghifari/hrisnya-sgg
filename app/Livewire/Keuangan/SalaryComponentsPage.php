<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\SalaryComponents\Schemas\SalaryComponentForm;
use App\Filament\Resources\SalaryComponents\Tables\SalaryComponentsTable;
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
 * Versi portal dari SalaryComponentResource (/admin/salary-components) — reuse Table & Form
 * class Filament yang sama, cuma dibungkus di luar panel. Lihat
 * planning/2026-07-12-portal-menu-keuangan-payroll.md.
 */
#[Layout('components.layout', ['title' => 'Komponen Gaji'])]
class SalaryComponentsPage extends Component implements HasActions, HasSchemas, HasTable
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
        return SalaryComponentsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    // Livewire biasa (bukan Resource Page) tidak otomatis tahu form mana yang dipakai
    // Create/Edit-nya — di Resource Filament ini di-resolve otomatis lewat ListRecords, di sini
    // harus disuntik manual.
    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        return match (true) {
            $action instanceof CreateAction, $action instanceof EditAction => fn (Schema $schema): Schema => SalaryComponentForm::configure($schema),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.keuangan.salary-components-page');
    }
}
