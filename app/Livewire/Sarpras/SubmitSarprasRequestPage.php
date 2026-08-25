<?php

namespace App\Livewire\Sarpras;

use App\Enum\CustomPermissionEnum;
use App\Services\Sarpras\SarprasRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Form ajuan Sarana & Prasarana — multi-item (add/remove baris, pola sama
 * CreateTemplatePenilaianPage) + lampiran (WithFileUploads multi-file). Unit BUKAN input bebas —
 * diambil otomatis dari Divisi (OrgStructure level 2, UserProfiles::division) pemohon, cuma
 * ditampilkan read-only buat konfirmasi (keputusan user 2026-08-25: "tinggal ambil divisi dari
 * orang yang mengajukan"). Validasi keberadaan divisi ada di SarprasRequestService::submit().
 */
#[Layout('components.layout', ['title' => 'Ajukan Sarana & Prasarana'])]
class SubmitSarprasRequestPage extends Component
{
    use WithFileUploads;

    public ?string $unitDisplay = null;
    public string $jenis = 'perbaikan';
    public string $prioritas = 'normal';
    public string $perihal = '';
    public string $uraian = '';

    /** @var array<int, array{nama: string, spesifikasi: ?string, jumlah: int, satuan: string, harga_satuan: ?string}> */
    public array $items = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::AJUKAN_SARANA_PRASARANA->value), 403);

        $this->unitDisplay = Auth::user()->profile?->division?->name;
        $this->items = [$this->emptyItem()];
    }

    private function emptyItem(): array
    {
        return ['nama' => '', 'spesifikasi' => null, 'jumlah' => 1, 'satuan' => 'unit', 'harga_satuan' => null];
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    protected function rules(): array
    {
        return [
            'jenis' => ['required', 'in:perbaikan,pengadaan'],
            'prioritas' => ['required', 'in:urgent,normal,rendah'],
            'perihal' => ['required', 'string', 'max:180'],
            'uraian' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama' => ['required', 'string', 'max:150'],
            'items.*.spesifikasi' => ['nullable', 'string'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.satuan' => ['required', 'string', 'max:30'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        try {
            $request = app(SarprasRequestService::class)->submit([
                'jenis' => $data['jenis'],
                'prioritas' => $data['prioritas'],
                'perihal' => $data['perihal'],
                'uraian' => $data['uraian'],
                'items' => $data['items'],
                'attachments' => $this->attachments,
            ], Auth::user());
        } catch (RuntimeException $e) {
            Notification::make()->title('Gagal mengajukan')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Permohonan berhasil diajukan')->success()->send();

        $this->redirect(route('sarana-prasarana.show', $request), navigate: true);
    }

    public function render()
    {
        return view('livewire.sarpras.submit-sarpras-request-page');
    }
}
