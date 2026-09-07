<?php

namespace App\Livewire\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\User\UserOrientation;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Padanan portal dari admin UserOrientationResource — daftar periode orientasi karyawan
 * (ledger user_orientations). Bisa EDIT tanggal/durasi lewat modal (koreksi data yang
 * salah/telat), TAPI TIDAK ada Tambah maupun Hapus — pola sama KontrakPage. Baris baru lahir
 * dari onboarding Rekrutmen atau perpanjangan penilaian orientasi (sikartap.orientasi).
 * Otorisasi Edit lewat UserOrientationPolicy (Shield Update:UserOrientation).
 */
#[Layout('components.layout', ['title' => 'Orientasi Karyawan'])]
class OrientasiKaryawanPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** 'semua' | 'aktif' | 'berakhir' — berdasar end_date terhadap hari ini. */
    #[Url]
    public string $status = 'semua';

    public bool $showFormModal = false;

    public ?string $editingId = null;
    public ?string $editingUserName = null;
    public ?string $formStartDate = null;
    public ?string $formEndDate = null;
    public ?int $formDurationMonths = null;
    public $formFile = null;
    public ?string $editingExistingFilePath = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', UserOrientation::class), 403);

        if (! Auth::user()->canFilterByCompany() || $this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::orderBy('name')->pluck('name', 'id');
    }

    public function getOrientationsProperty()
    {
        $today = Carbon::today()->toDateString();

        return UserOrientation::query()
            ->with(['user.company'])
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status === 'aktif', fn ($q) => $q->where('end_date', '>=', $today))
            ->when($this->status === 'berakhir', fn ($q) => $q->where('end_date', '<', $today))
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);
                $q->whereHas('user', fn ($q2) => $q2
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"));
            })
            ->orderByDesc('start_date')
            ->paginate(10)
            ->appends([
                'companyId' => $this->companyId,
                'search' => $this->search,
                'status' => $this->status,
            ]);
    }

    /** Non-global cuma boleh kelola baris company sendiri (permission Shield sendirian tidak
     * cek batas company — pola sama KontrakPage::canManage()). */
    protected function canManage(UserOrientation $orientation): bool
    {
        return Auth::user()->canFilterByCompany()
            || $orientation->company_id === Auth::user()->company_id;
    }

    public function openEditModal(string $id): void
    {
        $orientation = UserOrientation::with('user')->findOrFail($id);

        abort_unless(Auth::user()->can('update', $orientation), 403);
        abort_unless($this->canManage($orientation), 403);

        $this->editingId = $orientation->id;
        $this->editingUserName = $orientation->user?->name;
        $this->formStartDate = $orientation->start_date?->toDateString();
        $this->formEndDate = $orientation->end_date?->toDateString();
        $this->formDurationMonths = $orientation->duration_months;
        $this->editingExistingFilePath = $orientation->file_path;
        $this->formFile = null;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        abort_if($this->editingId === null, 400);

        $orientation = UserOrientation::findOrFail($this->editingId);

        abort_unless(Auth::user()->can('update', $orientation), 403);
        abort_unless($this->canManage($orientation), 403);

        $this->validate([
            'formStartDate' => ['required', 'date'],
            'formEndDate' => ['required', 'date', 'after_or_equal:formStartDate'],
            'formDurationMonths' => ['required', 'integer', 'min:1', 'max:24'],
            'formFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ], [], [
            'formStartDate' => 'tanggal mulai',
            'formEndDate' => 'tanggal selesai',
            'formDurationMonths' => 'durasi',
            'formFile' => 'surat orientasi',
        ]);

        $data = [
            'start_date' => $this->formStartDate,
            'end_date' => $this->formEndDate,
            'duration_months' => $this->formDurationMonths,
        ];

        if ($this->formFile) {
            if ($orientation->file_path) {
                Storage::disk('public')->delete($orientation->file_path);
            }

            $data['file_path'] = $this->formFile->store('user-orientations/' . $orientation->user_id, 'public');
        }

        $orientation->update($data);

        $this->showFormModal = false;

        Notification::make()->title('Data orientasi berhasil diperbarui')->success()->send();
    }

    public function render()
    {
        return view('livewire.kepegawaian.orientasi-karyawan-page', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
