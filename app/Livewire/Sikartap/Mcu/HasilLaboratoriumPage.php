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

/** Rewrite form_hasil_laboratorium.php legacy - diisi staff lab (atau HRD). */
#[Layout('components.layout', ['title' => 'Hasil Laboratorium'])]
class HasilLaboratoriumPage extends Component
{
    use WithFileUploads;

    public string $sikartapId;

    public array $form = [
        'hemoglobin' => null, 'leukosit' => null, 'eritrosit' => null, 'trombosit' => null,
        'hematokrit' => null, 'basofil' => null, 'eosinofil' => null, 'stab' => null,
        'segmen' => null, 'limfosit' => null, 'monosit' => null, 'mcv' => null,
        'mch' => null, 'mchc' => null, 'gds' => null, 'gda' => null,
        'hbsag' => null, 'hiv' => null, 'kesimpulan' => null,
    ];

    public $file = null;

    public function mount(Sikartap $sikartap): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value), 403);
        abort_unless($sikartap->status_wawancara === 'disarankan', 403, 'MCU baru bisa diisi setelah wawancara disarankan.');
        abort_unless($sikartap->requiresMcu(), 403, 'Company ini tidak mensyaratkan MCU.');
        abort_unless($sikartap->ideInovasiApproved(), 403, 'MCU baru bisa diisi setelah ide inovasi disetujui atasan.');

        $this->sikartapId = $sikartap->id;

        if ($existing = $sikartap->mcuLabResult) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    public function getSikartapProperty(): Sikartap
    {
        return Sikartap::with('mcuLabResult')->findOrFail($this->sikartapId);
    }

    protected function rules(): array
    {
        return [
            'form.hbsag' => ['nullable', 'in:Reaktif,Non Reaktif'],
            'form.hiv' => ['nullable', 'in:Reaktif,Non Reaktif'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $sikartap = $this->sikartap;
        $existing = $sikartap->mcuLabResult;
        abort_unless(! $existing || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);
        $filePath = $existing?->file_path;

        if ($this->file) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $this->file->store('sikartap-mcu/' . $sikartap->id . '/laboratorium', 'public');
        }

        $sikartap->mcuLabResult()->updateOrCreate(
            ['sikartap_id' => $sikartap->id],
            [...$this->form, 'file_path' => $filePath],
        );

        $this->file = null;

        Notification::make()->title('Hasil laboratorium tersimpan')->success()->send();

        $this->redirect(route('sikartap.dashboard'));
    }

    public function render()
    {
        return view('livewire.sikartap.mcu.hasil-laboratorium-page');
    }
}
