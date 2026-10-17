<?php

namespace App\Livewire\Kepegawaian;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;

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
 * Padanan portal dari admin EmployeeContractResource — bisa EDIT lewat modal (koreksi tanggal/surat
 * yang salah/telat), TAPI TIDAK ada Tambah maupun Hapus (2026-08-22, instruksi user). Baris baru
 * SELALU lewat alur "Perpanjangan Kontrak" (Perubahan Status Karyawan -> UserTransitionService),
 * yang sudah divalidasi (resolveContractEndDate(): batas maks tahun kontrak, usia pensiun) &
 * tercatat approval-nya - modal Tambah generik di sini bakal jadi jalur kedua yang bisa
 * melewati validasi itu. Hapus juga sengaja tidak ada - baris ini nempel ke riwayat approval
 * transisi, penghapusan tetap lewat admin panel (EmployeeContractResource) kalau memang perlu.
 * Otorisasi Edit tetap lewat EmployeeContractPolicy (Shield Update:EmployeeContract) yang sama dengan
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

    /** 'semua' | 'aktif' | 'berakhir' - berdasar end_date terhadap hari ini. */
    #[Url]
    public string $status = 'semua';

    public bool $showFormModal = false;

    public ?string $editingId = null;

    public ?string $formCompanyId = null;
    public ?string $formEmployeeId = null;
    public ?string $formEmployeeTypeId = null;
    public ?string $formStartDate = null;
    public ?string $formEndDate = null;
    public $formFile = null;

    /** file_path baris yang lagi diedit - ditampilkan sebagai link "surat saat ini" di modal. */
    public ?string $editingExistingFilePath = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', EmployeeContract::class), 403);

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
        // Re-kunci - formCompanyId property Livewire biasa (bukan #[Locked]), bisa di-$set
        // langsung dari request yang di-craft, tidak cukup percaya <select> company disembunyikan
        // di blade buat user non-global (pola sama DocumentFormPage::updatedCompanyId()).
        if (! Auth::user()->canFilterByCompany()) {
            $this->formCompanyId = Auth::user()->company_id;
        }

        // Ganti perusahaan di modal -> pilihan karyawan/tipe karyawan lama tidak relevan lagi.
        $this->formEmployeeId = null;
        $this->formEmployeeTypeId = null;
    }

    /** Non-global cuma boleh kelola kontrak karyawan company sendiri - permission Shield
     * Update:EmployeeContract sendirian tidak cek batas company sama sekali (pola sama
     * DocumentFormPage::canManage()). */
    protected function canManage(EmployeeContract $contract): bool
    {
        if (Auth::user()->canFilterByCompany()) {
            return true;
        }

        return $contract->company_id === Auth::user()->company_id;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getFormEmployeeOptionsProperty()
    {
        if (! $this->formCompanyId) {
            return collect();
        }

        return Employee::where('company_id', $this->formCompanyId)->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id');
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

        return EmployeeContract::query()
            ->kontrak()
            ->with(['employee.company', 'employeeType'])
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($this->status === 'aktif', fn ($q) => $q->where('end_date', '>=', $today))
            ->when($this->status === 'berakhir', fn ($q) => $q->where('end_date', '<', $today))
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);

                $q->whereHas('employee', fn ($q2) => $q2->where(fn ($q3) => $q3->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%")));
            })
            ->orderByDesc('start_date')
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
        $contract = EmployeeContract::findOrFail($id);

        abort_unless(Auth::user()->can('update', $contract), 403);
        abort_unless($this->canManage($contract), 403);

        $this->editingId = $contract->id;
        $this->editingExistingFilePath = $contract->file_path;
        $this->formCompanyId = $contract->company_id;
        $this->formEmployeeId = $contract->employee_id;
        $this->formEmployeeTypeId = $contract->employee_type_id;
        $this->formStartDate = $contract->start_date?->toDateString();
        $this->formEndDate = $contract->end_date?->toDateString();
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
            // exists:users,id doang cuma cek karyawannya ada, TIDAK cek company_id-nya - closure
            // ini mencegah user non-global nempelin kontrak ke karyawan perusahaan lain lewat
            // formEmployeeId yang di-tamper (pola sama DocumentFormPage::rules()).
            'formEmployeeId' => ['required', 'exists:employees,id', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Auth::user()->canFilterByCompany()) {
                    return;
                }

                $belongsToOwnCompany = Employee::whereKey($value)
                    ->where('company_id', Auth::user()->company_id)
                    ->exists();

                if (! $belongsToOwnCompany) {
                    $fail('Karyawan tidak ditemukan di perusahaan Anda.');
                }
            }],
            'formEmployeeTypeId' => ['required', 'exists:m_employee_types,id'],
            'formStartDate' => ['required', 'date'],
            'formEndDate' => ['required', 'date', 'after_or_equal:formStartDate'],
            'formFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function saveForm(): void
    {
        abort_if($this->editingId === null, 400);

        $contract = EmployeeContract::findOrFail($this->editingId);

        abort_unless(Auth::user()->can('update', $contract), 403);
        abort_unless($this->canManage($contract), 403);

        // Re-kunci sebelum validate() - saveForm() dipanggil langsung lewat request Livewire,
        // tidak lewat updatedFormCompanyId() dulu kalau formCompanyId di-tamper di request yang
        // sama (pola sama DocumentFormPage::save()).
        if (! Auth::user()->canFilterByCompany()) {
            $this->formCompanyId = Auth::user()->company_id;
        }

        $this->validate($this->formRules(), [], [
            'formCompanyId' => 'perusahaan',
            'formEmployeeId' => 'karyawan',
            'formEmployeeTypeId' => 'tipe karyawan',
            'formStartDate' => 'tanggal mulai',
            'formEndDate' => 'tanggal selesai',
            'formFile' => 'surat kontrak',
        ]);

        $data = [
            'company_id' => $this->formCompanyId,
            'employee_id' => $this->formEmployeeId,
            'employee_type_id' => $this->formEmployeeTypeId,
            'start_date' => $this->formStartDate,
            'end_date' => $this->formEndDate,
        ];

        if ($this->formFile) {
            if ($contract->file_path) {
                Storage::disk('public')->delete($contract->file_path);
            }

            $data['file_path'] = $this->formFile->store('user-contracts/' . $this->formEmployeeId, 'public');
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
