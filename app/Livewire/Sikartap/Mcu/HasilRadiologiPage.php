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

/** Rewrite form_hasil_radiologi.php legacy - diisi staff radiologi (atau HRD). */
#[Layout('components.layout', ['title' => 'Hasil Radiologi'])]
class HasilRadiologiPage extends Component
{
    use WithFileUploads;

    public string $sikartapId;

    public array $form = [
        'jenis_pemeriksaan' => null,
        'foto_thorax' => null,
        'jantung' => null,
        'paru_kanan' => null,
        'paru_kiri' => null,
        'diafragma' => null,
        'tulang' => null,
        'kesimpulan' => null,
    ];

    public $file = null;

    public function mount(Sikartap $sikartap): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value), 403);
        abort_unless($sikartap->status_wawancara === 'disarankan', 403, 'MCU baru bisa diisi setelah wawancara disarankan.');
        abort_unless($sikartap->requiresMcu(), 403, 'Company ini tidak mensyaratkan MCU.');
        abort_unless($sikartap->ideInovasiApproved(), 403, 'MCU baru bisa diisi setelah ide inovasi disetujui atasan.');

        $this->sikartapId = $sikartap->id;

        if ($existing = $sikartap->mcuRadiologyResult) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    public function getSikartapProperty(): Sikartap
    {
        return Sikartap::with('mcuRadiologyResult')->findOrFail($this->sikartapId);
    }

    protected function rules(): array
    {
        return [
            'form.foto_thorax' => ['nullable', 'in:Normal,Abnormal'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $sikartap = $this->sikartap;
        $existing = $sikartap->mcuRadiologyResult;
        abort_unless(! $existing || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);
        $filePath = $existing?->file_path;

        if ($this->file) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $this->file->store('sikartap-mcu/' . $sikartap->id . '/radiologi', 'public');
        }

        $sikartap->mcuRadiologyResult()->updateOrCreate(
            ['sikartap_id' => $sikartap->id],
            [...$this->form, 'file_path' => $filePath],
        );

        $this->file = null;

        Notification::make()->title('Hasil radiologi tersimpan')->success()->send();

        $this->redirect(route('sikartap.dashboard'));
    }

    public function render()
    {
        return view('livewire.sikartap.mcu.hasil-radiologi-page');
    }
}
