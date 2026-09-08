<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Rekrutmen\McuInvitee;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Hasil laboratorium MCU rekrutmen — port dari McuStationPage::isiLabAction(). */
#[Layout('components.layout', ['title' => 'Hasil Laboratorium (MCU Rekrutmen)'])]
class HasilLaboratoriumPage extends Component
{
    public string $inviteeId;

    public array $form = [
        'hemoglobin' => null, 'leukosit' => null, 'eritrosit' => null, 'trombosit' => null,
        'hematokrit' => null, 'basofil' => null, 'eosinofil' => null, 'stab' => null,
        'segmen' => null, 'limfosit' => null, 'monosit' => null, 'mcv' => null,
        'mch' => null, 'mchc' => null, 'gds' => null, 'gda' => null,
        'hbsag' => null, 'hiv' => null, 'kesimpulan' => null,
    ];

    public function mount(string $invitee): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value), 403);

        $this->inviteeId = $invitee;
        $model = $this->invitee();

        if ($existing = $model->labResult) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    protected function rules(): array
    {
        return [
            'form.hbsag' => ['nullable', 'in:Reaktif,Non Reaktif'],
            'form.hiv' => ['nullable', 'in:Reaktif,Non Reaktif'],
        ];
    }

    public function getInviteeProperty(): McuInvitee
    {
        return $this->invitee();
    }

    private function invitee(): McuInvitee
    {
        $model = McuInvitee::with(['jobApplication.answers', 'labResult'])->findOrFail($this->inviteeId);
        abort_unless($model->jobApplication?->status === 'mcu', 403, 'Pelamar ini tidak sedang dalam tahap MCU.');

        return $model;
    }

    public function save(): void
    {
        $this->validate();

        $model = $this->invitee();
        abort_unless(! $model->labResult || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);

        $model->labResult()->updateOrCreate(['mcu_invitee_id' => $model->id], $this->form);

        Notification::make()->title('Hasil laboratorium tersimpan')->success()->send();

        $this->redirect(route('rekrutmen.mcu.queue'));
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.hasil-laboratorium-page');
    }
}
