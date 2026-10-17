<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Employee\Employee;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman "Data Rekening" — kelola bank_name/bank_account_number/bank_account_name
 * (employee_identities) khusus buat finance/payroll, TERPISAH dari Update:User (2026-08-26, permintaan
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

    public ?string $editingEmployeeId = null;
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
        $query = Employee::query()->where('status', 'aktif')->with('identity')
            // Employee tidak punya scope 'non_karyawan' seperti User - dikecualikan manual.
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', User::NON_KARYAWAN_IDS));

        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        } elseif (! Auth::user()->canFilterByCompany()) {
            $query->where('company_id', Auth::user()->company_id);
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"));
        }

        return $query->orderBy('nama_lengkap')->paginate(15)->appends([
            'companyId' => $this->companyId,
            'search' => $this->search,
        ]);
    }

    public function openEditModal(string $employeeId): void
    {
        abort_unless($this->canEdit, 403);

        $employee = Employee::with('identity')->findOrFail($employeeId);

        $this->editingEmployeeId = $employeeId;
        $this->editBankName = $employee->identity?->bank_name;
        $this->editBankAccountNumber = $employee->identity?->bank_account_number;
        $this->editBankAccountName = $employee->identity?->bank_account_name;
        $this->resetErrorBag();
    }

    public function closeEditModal(): void
    {
        $this->editingEmployeeId = null;
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

        $employee = Employee::findOrFail($this->editingEmployeeId);

        $bank = [
            'bank_name' => $this->editBankName,
            'bank_account_number' => $this->editBankAccountNumber,
            'bank_account_name' => $this->editBankAccountName,
        ];

        $employee->identity()->updateOrCreate([], $bank);

        // user_profiles masih sumber salinan ke employee_identities (EmployeeSyncService menimpa
        // identitas dari profil tiap sinkron) - tulis juga ke sana supaya rekening tidak kembali lama.
        if ($employee->user_id) {
            UserProfiles::updateOrCreate(['user_id' => $employee->user_id], $bank);
        }

        $this->editingEmployeeId = null;

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
