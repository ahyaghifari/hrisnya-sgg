<?php

namespace App\Livewire;

use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\OrgHierarchyService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\EditAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'MyStaff'])]
class MyEmployees extends Component implements HasTable, HasSchemas, HasActions
{
    use InteractsWithTable;
    use InteractsWithSchemas;
    use InteractsWithActions;

    public ?string $companyId = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->canAccessKaryawanMenu(), 403);
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    // Sumber data yang sama dipakai desktop (Filament Table) dan mobile (card manual).
    protected function baseQuery()
    {
        if (Auth::user()->canBrowseAllCompanies()) {
            if (! $this->companyId) {
                return User::query()->whereRaw('1 = 0');
            }

            return User::query()
                ->where('company_id', $this->companyId)
                ->with(['profile.jobPosition', 'profile.orgStructure.parent.parent', 'company', 'roles']);
        }

        return app(OrgHierarchyService::class)->subordinates(Auth::user())
            ->with(['profile.jobPosition', 'profile.orgStructure.parent.parent', 'company', 'roles']);
    }

    // Dipakai desktop (Filament Table).
    public function table(Table $table): Table
    {
        $canBrowseAllCompanies = Auth::user()->canBrowseAllCompanies();

        return EmployeesTable::configure($table)
            ->query(fn () => $this->baseQuery())
            ->filters([])
            ->recordActions([
                Action::make('statistik')
                    ->label('Lihat')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (User $record) => route('my-staff.show', ['record' => $record])),
                EditAction::make()
                    ->url(fn (User $record) => route('my-staff.edit', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->emptyStateHeading($canBrowseAllCompanies && ! $this->companyId
                ? 'Pilih perusahaan dulu'
                : 'Belum ada karyawan di bawah kamu');
    }

    // Dipakai mobile (card manual, lihat my-employees.blade.php).
    public function getEmployeesProperty()
    {
        return $this->baseQuery()
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.my-employees', [
            'canBrowseAllCompanies' => Auth::user()->canBrowseAllCompanies(),
        ]);
    }
}
