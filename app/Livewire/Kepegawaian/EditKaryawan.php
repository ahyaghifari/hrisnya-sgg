<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Edit dari halaman Karyawan (semua karyawan company, bukan cuma bawahan) — beda dari
 * EditMyEmployee (khusus My Staff, permission subordinate-based). Scoping akses di sini
 * disamakan dengan KaryawanPage::baseQuery(): SISTEM_KEPEGAWAIAN + company sendiri, atau
 * canFilterByCompany() buat yang boleh lintas company.
 */
#[Layout('components.layout', ['title' => 'Edit Karyawan'])]
class EditKaryawan extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public User $record;

    public ?array $data = [];

    public function mount(User $record): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);
        abort_unless(
            Auth::user()->canFilterByCompany() || Auth::user()->company_id === $record->company_id,
            403
        );

        $this->record = $record;

        $this->getSchema('editForm')->fill($record->load('profile')->attributesToArray());
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

        $this->redirect(route('karyawan.show', ['record' => $this->record, 'from' => 'karyawan']), navigate: true);
    }

    public function render()
    {
        return view('livewire.kepegawaian.edit-karyawan');
    }
}
