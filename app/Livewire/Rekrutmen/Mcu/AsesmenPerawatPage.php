<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Rekrutmen\McuInvitee;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Asesmen perawat MCU rekrutmen — port dari McuStationPage::isiPerawatAction(). */
#[Layout('components.layout', ['title' => 'Asesmen Perawat (MCU Rekrutmen)'])]
class AsesmenPerawatPage extends Component
{
    public string $inviteeId;

    public array $form = [
        'tinggi_badan' => null, 'berat_badan' => null, 'tekanan_darah' => null,
        'nadi' => null, 'suhu' => null, 'pernafasan' => null, 'keluhan_kesehatan' => null,
        'rpk_hipertensi' => false, 'rpk_stroke' => false, 'rpk_jantung' => false,
        'rpk_diabetes' => false, 'rpk_lainnya' => null,
        'riwayat_penyakit_dahulu' => null, 'riwayat_operasi' => null,
        'alergi_makanan' => false, 'alergi_obat' => false, 'alergi_debu' => false, 'alergi_lainnya' => null,
        'kebiasaan_olahraga' => false, 'kebiasaan_merokok' => false, 'kebiasaan_alkohol' => false,
        'kebiasaan_obat' => false, 'kebiasaan_lainnya' => null,
        'hasil_ekg' => null, 'keterangan_ekg' => null, 'catatan' => null,
    ];

    public function mount(string $invitee): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value), 403);

        $this->inviteeId = $invitee;
        $model = $this->invitee();

        if ($existing = $model->nurseAssessment) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    protected function rules(): array
    {
        return [
            'form.tinggi_badan' => ['required', 'numeric'],
            'form.berat_badan' => ['required', 'numeric'],
            'form.tekanan_darah' => ['required', 'string'],
            'form.nadi' => ['required', 'integer'],
            'form.suhu' => ['required', 'numeric'],
            'form.pernafasan' => ['required', 'integer'],
            'form.hasil_ekg' => ['nullable', 'in:Normal,Abnormal'],
        ];
    }

    public function getInviteeProperty(): McuInvitee
    {
        return $this->invitee();
    }

    private function invitee(): McuInvitee
    {
        $model = McuInvitee::with(['jobApplication.answers', 'nurseAssessment'])->findOrFail($this->inviteeId);
        abort_unless($model->jobApplication?->status === 'mcu', 403, 'Pelamar ini tidak sedang dalam tahap MCU.');

        return $model;
    }

    public function save(): void
    {
        $this->validate();

        $model = $this->invitee();
        abort_unless(! $model->nurseAssessment || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);

        $model->nurseAssessment()->updateOrCreate(['mcu_invitee_id' => $model->id], $this->form);

        Notification::make()->title('Asesmen perawat tersimpan')->success()->send();

        $this->redirect(route('rekrutmen.mcu.queue'));
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.asesmen-perawat-page');
    }
}
