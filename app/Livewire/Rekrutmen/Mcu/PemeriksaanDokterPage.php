<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Rekrutmen\McuInvitee;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pemeriksaan dokter MCU rekrutmen — port dari McuStationPage::isiDokterAction(). Keputusan
 * final fit/unfit tetap oleh HRD di halaman MCU panel rekrutmen; di sini cuma catat hasil
 * pemeriksaan fisik + rekomendasi.
 */
#[Layout('components.layout', ['title' => 'Pemeriksaan Dokter (MCU Rekrutmen)'])]
class PemeriksaanDokterPage extends Component
{
    public string $inviteeId;

    public array $form = [
        'kulit' => null, 'rambut' => null, 'visus_mata' => null, 'buta_warna' => null, 'lapang_pandang' => null,
        'hidung' => null, 'telinga' => null, 'tonsil' => null, 'faring' => null, 'gigi' => null,
        'kelenjar_gondok' => null,
        'suara_pernafasan' => null, 'ronchi' => null, 'wheezing' => null, 'lainnya_paru' => null, 'jantung' => null,
        'hati' => null, 'limpa' => null, 'epigastrium' => null, 'ginjal' => null, 'lainnya_abdomen' => null,
        'ekstremitas' => null, 'neurologi' => null,
        'diagnosa' => null, 'rekomendasi' => null, 'catatan' => null,
    ];

    public function mount(string $invitee): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value), 403);

        $this->inviteeId = $invitee;
        $model = $this->invitee();

        if ($existing = $model->doctorExamination) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    public function getInviteeProperty(): McuInvitee
    {
        return $this->invitee();
    }

    private function invitee(): McuInvitee
    {
        $model = McuInvitee::with([
            'jobApplication.answers', 'nurseAssessment', 'labResult', 'radiologyResult', 'doctorExamination',
        ])->findOrFail($this->inviteeId);
        abort_unless($model->jobApplication?->status === 'mcu', 403, 'Pelamar ini tidak sedang dalam tahap MCU.');

        return $model;
    }

    public function save(): void
    {
        $model = $this->invitee();
        abort_unless(! $model->doctorExamination || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);

        $model->doctorExamination()->updateOrCreate(['mcu_invitee_id' => $model->id], $this->form);

        Notification::make()->title('Hasil pemeriksaan dokter tersimpan')->success()->send();

        $this->redirect(route('rekrutmen.mcu.queue'));
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.pemeriksaan-dokter-page');
    }
}
