<?php

namespace App\Livewire;

use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Models\User;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Edit Karyawan'])]
class EditMyEmployee extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public User $record;

    public ?array $data = [];

    public function mount(User $record): void
    {
        abort_unless(Auth::user()->can('edit_karyawan'), 403);
        abort_unless($this->canManage($record), 403);

        $this->record = $record;

        $this->getSchema('editForm')->fill($record->load('profile')->attributesToArray());
    }

    protected function canManage(User $record): bool
    {
        $user = Auth::user();

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->id)
            ->exists();
    }

    public function editForm(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema)
            ->record($this->record)
            ->statePath('data')
            ->operation('edit');
    }

    public function save(): void
    {
        $data = $this->getSchema('editForm')->getState();

        $this->record->update($data);

        Notification::make()
            ->title('Karyawan berhasil diperbarui')
            ->success()
            ->send();

        $this->redirect(route('my-staff'), navigate: true);
    }

    public function render()
    {
        return view('livewire.edit-my-employee');
    }
}
