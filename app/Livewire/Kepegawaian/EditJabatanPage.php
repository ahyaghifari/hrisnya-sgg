<?php

namespace App\Livewire\Kepegawaian;

use App\Livewire\Kepegawaian\Concerns\HasJabatanForm;
use App\Models\Master\JobPositions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Edit Jabatan portal - padanan EditJobPosition admin. Field terkunci total begitu status
 * bukan lagi draft (getIsLockedProperty(), lihat HasJabatanForm) - pengajuan yang sudah
 * diajukan/disetujui/ditolak tidak boleh diam-diam diubah lewat sini, cuma bisa dilihat.
 * Kuota (planned_manpower) juga cuma bisa diisi saat pengajuan baru (lihat
 * jabatan-form-fields.blade.php) - TIDAK bisa diedit lewat sini sama sekali, perubahan kuota
 * cuma lewat tombol "Ajukan Penambahan Kuota" di tabel Jabatan (JabatanPage).
 */
#[Layout('components.layout', ['title' => 'Edit Jabatan'])]
class EditJabatanPage extends Component
{
    use HasJabatanForm;
    use WithFileUploads;

    public function mount(JobPositions $record): void
    {
        abort_unless(
            Auth::user()->canFilterByCompany() || Auth::user()->company_id === $record->company_id,
            403
        );
        abort_unless(Auth::user()->can('update', $record), 403);

        $this->fillFromRecord($record);
    }

    public function save(): void
    {
        abort_unless(! $this->isLocked, 403);

        $data = $this->validate($this->jabatanRules());
        unset($data['department_id'], $data['division_id'], $data['sub_division_id']);

        try {
            $this->record->update($this->jabatanPayload());

            Notification::make()->title('Jabatan berhasil diperbarui')->success()->send();

            $this->redirect(route('kepegawaian.jabatan'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.edit-jabatan-page');
    }
}
