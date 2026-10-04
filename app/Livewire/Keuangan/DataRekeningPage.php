<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman "Data Rekening" — kelola bank_name/bank_account_number/bank_account_name
 * (UserProfiles) khusus buat finance/payroll, TERPISAH dari Update:User (2026-08-26, permintaan
 * user) - supaya bisa dikasih ke role yang tidak punya akses edit seluruh data karyawan.
 * Pola list sama KaryawanPage (company+search+paginate), edit modal sama KoperasiPage.
 */
#[Layout('components.layout', ['title' => 'Data Rekening'])]
class DataRekeningPage extends Component
{
    use WithPagination;

    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public ?string $editingUserId = null;
    public ?string $editBankName = null;
    public ?string $editBankAccountNumber = null;
    public ?string $editBankAccountName = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_DATA_REKENING->value), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
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

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_DATA_REKENING->value);
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getEmployeesProperty()
    {
        $query = User::query()->where('is_active', true)->with('profile');

        if ($this->companyId) {
            $query->whereCompany($this->companyId);
        } elseif (! Auth::user()->canFilterByCompany()) {
            $query->whereCompany(Auth::user()->company_id);
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->searchNameOrNip($search);
        }

        return $query->orderByName()->paginate(15)->appends([
            'companyId' => $this->companyId,
            'search' => $this->search,
        ]);
    }

    public function openEditModal(string $userId): void
    {
        abort_unless($this->canEdit, 403);

        $user = User::with('profile')->findOrFail($userId);

        $this->editingUserId = $userId;
        $this->editBankName = $user->profile?->bank_name;
        $this->editBankAccountNumber = $user->profile?->bank_account_number;
        $this->editBankAccountName = $user->profile?->bank_account_name;
        $this->resetErrorBag();
    }

    public function closeEditModal(): void
    {
        $this->editingUserId = null;
    }

    public function save(): void
    {
        abort_unless($this->canEdit, 403);

        $this->validate([
            'editBankName' => ['nullable', 'string', 'max:100'],
            'editBankAccountNumber' => ['nullable', 'string', 'max:50'],
            'editBankAccountName' => ['nullable', 'string', 'max:150'],
        ], [], [
            'editBankName' => 'nama bank',
            'editBankAccountNumber' => 'no. rekening',
            'editBankAccountName' => 'atas nama rekening',
        ]);

        $user = User::with('profile')->findOrFail($this->editingUserId);

        $user->profile()->updateOrCreate(['user_id' => $user->id], [
            'bank_name' => $this->editBankName,
            'bank_account_number' => $this->editBankAccountNumber,
            'bank_account_name' => $this->editBankAccountName,
        ]);

        $this->editingUserId = null;

        Notification::make()
            ->title('Data rekening berhasil disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.data-rekening-page', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
