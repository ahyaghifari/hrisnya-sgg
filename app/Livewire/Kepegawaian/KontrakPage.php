<?php

namespace App\Livewire\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\User;
use App\Models\User\UserContract;
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
 * Padanan portal dari admin UserContractResource — bisa EDIT lewat modal (koreksi tanggal/surat
 * yang salah/telat), TAPI TIDAK ada Tambah maupun Hapus (2026-08-22, instruksi user). Baris baru
 * SELALU lewat alur "Perpanjangan Kontrak" (Perubahan Status Karyawan -> UserTransitionService),
 * yang sudah divalidasi (resolveContractEndDate(): batas maks tahun kontrak, usia pensiun) &
 * tercatat approval-nya - modal Tambah generik di sini bakal jadi jalur kedua yang bisa
 * melewati validasi itu. Hapus juga sengaja tidak ada - baris ini nempel ke riwayat approval
 * transisi, penghapusan tetap lewat admin panel (UserContractResource) kalau memang perlu.
 * Otorisasi Edit tetap lewat UserContractPolicy (Shield Update:UserContract) yang sama dengan
 * admin panel.
 */
#[Layout('components.layout', ['title' => 'Kontrak Karyawan'])]
class KontrakPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    /** 'semua' | 'aktif' | 'berakhir' - berdasar contract_end_date terhadap hari ini. */
    #[Url]
    public string $status = 'semua';

    public bool $showFormModal = false;

    public ?string $editingId = null;

    public ?string $formCompanyId = null;
    public ?string $formUserId = null;
    public ?string $formEmployeeTypeId = null;
    public ?string $formStartDate = null;
    public ?string $formEndDate = null;
    public $formFile = null;

    /** file_path baris yang lagi diedit - ditampilkan sebagai link "surat saat ini" di modal. */
    public ?string $editingExistingFilePath = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', UserContract::class), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
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

    public function updatedFormCompanyId(): void
    {
        // Ganti perusahaan di modal -> pilihan karyawan/tipe karyawan lama tidak relevan lagi.
        $this->formUserId = null;
        $this->formEmployeeTypeId = null;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getFormUserOptionsProperty()
    {
        if (! $this->formCompanyId) {
            return collect();
        }

        return User::where('company_id', $this->formCompanyId)->orderBy('name')->pluck('name', 'id');
    }

    public function getFormEmployeeTypeOptionsProperty()
    {
        if (! $this->formCompanyId) {
            return collect();
        }

        return EmployeeTypes::where('company_id', $this->formCompanyId)->orderBy('name')->pluck('name', 'id');
    }

    public function getContractsProperty()
    {
        $today = Carbon::today()->toDateString();

        return UserContract::query()
            ->with(['user.company', 'employeeType'])
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status === 'aktif', fn ($q) => $q->where('contract_end_date', '>=', $today))
            ->when($this->status === 'berakhir', fn ($q) => $q->where('contract_end_date', '<', $today))
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);

                $q->whereHas('user', fn ($q2) => $q2
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"));
            })
            ->orderByDesc('contract_start_date')
            ->paginate(10)
            ->appends([
                'companyId' => $this->companyId,
                'search' => $this->search,
                'status' => $this->status,
            ]);
    }

    // ── Edit (satu-satunya aksi tulis di halaman ini) ───────────────────────

    public function openEditModal(string $id): void
    {
        $contract = UserContract::findOrFail($id);

        abort_unless(Auth::user()->can('update', $contract), 403);

        $this->editingId = $contract->id;
        $this->editingExistingFilePath = $contract->file_path;
        $this->formCompanyId = $contract->company_id;
        $this->formUserId = $contract->user_id;
        $this->formEmployeeTypeId = $contract->employee_type_id;
        $this->formStartDate = $contract->contract_start_date?->toDateString();
        $this->formEndDate = $contract->contract_end_date?->toDateString();
        $this->formFile = null;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    protected function formRules(): array
    {
        return [
            'formCompanyId' => ['required', 'exists:m_companies,id'],
            'formUserId' => ['required', 'exists:users,id'],
            'formEmployeeTypeId' => ['required', 'exists:m_employee_types,id'],
            'formStartDate' => ['required', 'date'],
            'formEndDate' => ['required', 'date', 'after_or_equal:formStartDate'],
            'formFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function saveForm(): void
    {
        abort_if($this->editingId === null, 400);

        $contract = UserContract::findOrFail($this->editingId);

        abort_unless(Auth::user()->can('update', $contract), 403);

        $this->validate($this->formRules(), [], [
            'formCompanyId' => 'perusahaan',
            'formUserId' => 'karyawan',
            'formEmployeeTypeId' => 'tipe karyawan',
            'formStartDate' => 'tanggal mulai',
            'formEndDate' => 'tanggal selesai',
            'formFile' => 'surat kontrak',
        ]);

        $data = [
            'company_id' => $this->formCompanyId,
            'user_id' => $this->formUserId,
            'employee_type_id' => $this->formEmployeeTypeId,
            'contract_start_date' => $this->formStartDate,
            'contract_end_date' => $this->formEndDate,
        ];

        if ($this->formFile) {
            if ($contract->file_path) {
                Storage::disk('public')->delete($contract->file_path);
            }

            $data['file_path'] = $this->formFile->store('user-contracts/' . $this->formUserId, 'public');
        }

        $contract->update($data);

        $this->showFormModal = false;

        Notification::make()
            ->title('Kontrak berhasil diperbarui')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.kepegawaian.kontrak-page', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
