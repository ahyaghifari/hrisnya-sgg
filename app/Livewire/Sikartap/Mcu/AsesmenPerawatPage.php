<?php

namespace App\Livewire\Sikartap\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Sikartap\Sikartap;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Rewrite form_asesmen_perawat.php legacy - diisi staff perawat (atau HRD). */
#[Layout('components.layout', ['title' => 'Asesmen Perawat & EKG'])]
class AsesmenPerawatPage extends Component
{
    use WithFileUploads;

    public string $sikartapId;

    public array $form = [
        'tinggi_badan' => null,
        'berat_badan' => null,
        'tekanan_darah' => null,
        'nadi' => null,
        'suhu' => null,
        'pernafasan' => null,
        'keluhan_kesehatan' => null,
        'rpk_hipertensi' => false,
        'rpk_stroke' => false,
        'rpk_jantung' => false,
        'rpk_diabetes' => false,
        'rpk_lainnya' => null,
        'riwayat_penyakit_dahulu' => null,
        'riwayat_operasi' => null,
        'alergi_makanan' => false,
        'alergi_obat' => false,
        'alergi_debu' => false,
        'alergi_lainnya' => null,
        'kebiasaan_olahraga' => false,
        'kebiasaan_merokok' => false,
        'kebiasaan_alkohol' => false,
        'kebiasaan_obat' => false,
        'kebiasaan_lainnya' => null,
        'hasil_ekg' => null,
        'keterangan_ekg' => null,
        'catatan' => null,
    ];

    public $fileEkg = null;

    public function mount(Sikartap $sikartap): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value), 403);
        abort_unless($sikartap->status_wawancara === 'disarankan', 403, 'MCU baru bisa diisi setelah wawancara disarankan.');
        abort_unless($sikartap->requiresMcu(), 403, 'Company ini tidak mensyaratkan MCU.');
        abort_unless($sikartap->ideInovasiApproved(), 403, 'MCU baru bisa diisi setelah ide inovasi disetujui atasan.');

        $this->sikartapId = $sikartap->id;

        if ($existing = $sikartap->mcuNurseAssessment) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    public function getSikartapProperty(): Sikartap
    {
        return Sikartap::with('mcuNurseAssessment')->findOrFail($this->sikartapId);
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
            'fileEkg' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function removeEkgFile(): void
    {
        $sikartap = $this->sikartap;
        $existing = $sikartap->mcuNurseAssessment;

        if ($existing?->file_ekg) {
            Storage::disk('public')->delete($existing->file_ekg);
            $existing->update(['file_ekg' => null]);
        }
    }

    public function save(): void
    {
        $this->validate();

        $sikartap = $this->sikartap;
        $existing = $sikartap->mcuNurseAssessment;
        abort_unless(! $existing || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);
        $filePath = $existing?->file_ekg;

        if ($this->fileEkg) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $this->fileEkg->store('sikartap-mcu/' . $sikartap->id . '/ekg', 'public');
        }

        $sikartap->mcuNurseAssessment()->updateOrCreate(
            ['sikartap_id' => $sikartap->id],
            [...$this->form, 'file_ekg' => $filePath],
        );

        $this->fileEkg = null;

        Notification::make()->title('Asesmen perawat tersimpan')->success()->send();

        $this->redirect(route('sikartap.dashboard'));
    }

    public function render()
    {
        return view('livewire.sikartap.mcu.asesmen-perawat-page');
    }
}
