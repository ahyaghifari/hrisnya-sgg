<?php

namespace App\Livewire\Master;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Master\Holidays\Schemas\HolidayForm;
use App\Filament\Resources\Master\Holidays\Tables\HolidaysTable;
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
 * Versi portal dari HolidayResource (/admin/holidays) — reuse Table & Form class Filament yang
 * sama, cuma dibungkus di luar panel. Lihat planning/2026-07-16-master-hari-libur-cuti-bersama.md.
 */
#[Layout('components.layout', ['title' => 'Hari Libur & Cuti Bersama'])]
class HolidaysPage extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_MASTER->value), 403);
    }

    public function table(Table $table): Table
    {
        return HolidaysTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        return match (true) {
            $action instanceof CreateAction, $action instanceof EditAction => fn (Schema $schema): Schema => HolidayForm::configure($schema),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.master.holidays-page');
    }
}
