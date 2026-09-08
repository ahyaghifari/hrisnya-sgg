<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Rekrutmen\McuInvitee;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Hasil radiologi MCU rekrutmen — port dari McuStationPage::isiRadiologiAction(). */
#[Layout('components.layout', ['title' => 'Hasil Radiologi (MCU Rekrutmen)'])]
class HasilRadiologiPage extends Component
{
    public string $inviteeId;

    public array $form = [
        'jenis_pemeriksaan' => null, 'foto_thorax' => null, 'jantung' => null,
        'paru_kanan' => null, 'paru_kiri' => null, 'diafragma' => null,
        'tulang' => null, 'kesimpulan' => null,
    ];

    public function mount(string $invitee): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value), 403);

        $this->inviteeId = $invitee;
        $model = $this->invitee();

        if ($existing = $model->radiologyResult) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    protected function rules(): array
    {
        return [
            'form.foto_thorax' => ['nullable', 'in:Normal,Abnormal'],
        ];
    }

    public function getInviteeProperty(): McuInvitee
    {
        return $this->invitee();
    }

    private function invitee(): McuInvitee
    {
        $model = McuInvitee::with(['jobApplication.answers', 'radiologyResult'])->findOrFail($this->inviteeId);
        abort_unless($model->jobApplication?->status === 'mcu', 403, 'Pelamar ini tidak sedang dalam tahap MCU.');

        return $model;
    }

    public function save(): void
    {
        $this->validate();

        $model = $this->invitee();
        abort_unless(! $model->radiologyResult || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);

        $model->radiologyResult()->updateOrCreate(['mcu_invitee_id' => $model->id], $this->form);

        Notification::make()->title('Hasil radiologi tersimpan')->success()->send();

        $this->redirect(route('rekrutmen.mcu.queue'));
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.hasil-radiologi-page');
    }
}
