<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\KoperasiExport;
use App\Imports\Payroll\KoperasiImport;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeKoperasi;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Roster anggota koperasi — dibaca LANGSUNG tiap Generate Payroll oleh KoperasiCalculator
 * (tidak lewat payroll_manual_lines, beda dari Bon/Insentif import - 2026-08-22). Cari/company lewat Livewire
 * (server-side) — SEBELUMNYA cari pakai List.js di browser, tapi List.js mengunci baris
 * tbody-nya sendiri sehingga tidak ikut ke-morph Livewire tiap company/data ganti (baris lama
 * nyangkut terus), jadi dicabut.
 */
#[Layout('components.layout', ['title' => 'Koperasi'])]
class KoperasiPage extends Component
{
    use GatesCompanyFilterBySystem;
    use WithFileUploads;

    public ?string $companyId = null;
    public string $search = '';

    public array $selected = [];

    public bool $showAddModal = false;
    public ?string $addEmployeeId = null;
    public ?string $addAmount = null;

    public bool $showImportModal = false;
    public $importFile = null;
    public array $importErrors = [];

    public bool $showEditModal = false;
    public array $editingIds = [];
    public ?string $editAmount = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', EmployeeKoperasi::class), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEUANGAN);
    }

    public function updatedCompanyId(): void
    {
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEUANGAN);

        $this->selected = [];
    }

    public function toggleSelectAll(bool $checked): void
    {
        $this->selected = $checked ? $this->members->pluck('id')->all() : [];
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getMembersProperty()
    {
        $search = trim($this->search);

        return EmployeeKoperasi::query()
            ->whereHas('employee', fn ($q) => $q->where('company_id', $this->companyId)
                ->when($search !== '', fn ($q2) => $q2->where(fn ($q3) => $q3->where('nama_lengkap', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"))))
            ->with('employee')
            ->get();
    }

    /**
     * Karyawan aktif di company terpilih yang belum jadi anggota koperasi DAN tipe
     * karyawannya terdaftar sebagai penerima Koperasi di halaman "Penerima Komponen Gaji" —
     * kalau tidak difilter di sini, admin bisa nambah anggota yang potongannya tidak akan
     * pernah muncul di payroll (PayrollService::runComponent() tetap gate berdasarkan
     * PayrollComponentRecipient, roster ini tidak bisa mem-bypass itu).
     */
    public function getAvailableEmployeesProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        $eligibleTypeIds = PayrollComponentRecipient::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::KOPERASI->value)
            ->pluck('employee_type_id');

        return Employee::query()
            ->where('company_id', $this->companyId)
            ->where('status', 'aktif')
            ->whereDoesntHave('koperasi')
            ->whereIn('employee_type_id', $eligibleTypeIds)
            ->orderBy('nama_lengkap')
            ->get();
    }

    // ── Tambah Anggota ──────────────────────────────────────────────────────

    public function getCanCreateKoperasiProperty(): bool
    {
        return Auth::user()->can('create', EmployeeKoperasi::class);
    }

    public function getCanUpdateKoperasiProperty(): bool
    {
        return Auth::user()->can('update', EmployeeKoperasi::class);
    }

    public function openAddModal(): void
    {
        abort_unless(Auth::user()->can('create', EmployeeKoperasi::class), 403);

        $this->addEmployeeId = null;
        $this->addAmount = null;
        $this->resetErrorBag();
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function saveAdd(): void
    {
        abort_unless(Auth::user()->can('create', EmployeeKoperasi::class), 403);

        $this->validate([
            'addEmployeeId' => 'required|exists:employees,id',
            'addAmount' => 'required|numeric|min:0.01',
        ], [], [
            'addEmployeeId' => 'karyawan',
            'addAmount' => 'nominal',
        ]);

        $employee = Employee::findOrFail($this->addEmployeeId);

        abort_unless(
            PayrollComponentRecipient::isEligible($employee->company_id, PayrollComponentEnum::KOPERASI->value, $employee->employee_type_id),
            403,
            'Tipe karyawan ini belum terdaftar sebagai penerima Koperasi di halaman Penerima Komponen Gaji.'
        );

        // Dropdown "availableUsers" sudah exclude karyawan yang sudah jadi anggota, tapi itu
        // cuma filter tampilan — bukan jaminan atomik (dua tab/klik ganda bisa lolos bareng
        // sebelum salah satu commit). Cek eksplisit di sini + constraint unique di DB
        // (employee_koperasi.employee_id) yang jadi jaminan sesungguhnya: SATU baris per karyawan,
        // tidak ditimpa diam-diam kalau ternyata sudah ada.
        abort_if(
            EmployeeKoperasi::where('employee_id', $this->addEmployeeId)->exists(),
            409,
            'Karyawan ini sudah jadi anggota koperasi — ubah nominalnya dari daftar, bukan tambah lagi.'
        );

        try {
            EmployeeKoperasi::create([
                'employee_id' => $this->addEmployeeId,
                'amount' => $this->addAmount,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Karyawan ini sudah jadi anggota koperasi — coba muat ulang halaman.');
        }

        $this->showAddModal = false;

        Notification::make()
            ->title('Anggota koperasi berhasil ditambahkan')
            ->body('Akan ikut ke payroll mulai Generate/Regenerate berikutnya.')
            ->success()
            ->send();
    }

    // ── Edit Amount (satu atau beberapa baris yang dicentang) ──────────────

    public function openEditModal(?string $id = null): void
    {
        abort_unless(Auth::user()->can('update', $id ? EmployeeKoperasi::findOrFail($id) : EmployeeKoperasi::class), 403);

        $this->editingIds = $id ? [$id] : $this->selected;

        if ($this->editingIds === []) {
            return;
        }

        $this->editAmount = null;
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
    }

    public function saveEdit(): void
    {
        abort_unless(Auth::user()->can('update', EmployeeKoperasi::class), 403);

        $this->validate([
            'editAmount' => 'required|numeric|min:0.01',
        ], [], [
            'editAmount' => 'nominal',
        ]);

        // Ambil dulu jadi instance model (bukan whereIn()->update() query builder langsung) -
        // 'amount' di-cast PayrollEncrypted, cuma jalan lewat ->save()/->update() PER INSTANCE.
        // Mass update query builder melewati cast sama sekali, nulis nominal PLAINTEXT mentah
        // ke kolom yang seharusnya terenkripsi (bug ditemukan 2026-08-20).
        EmployeeKoperasi::whereIn('id', $this->editingIds)->get()->each(
            fn (EmployeeKoperasi $member) => $member->update(['amount' => $this->editAmount])
        );

        $this->showEditModal = false;
        $this->selected = [];

        Notification::make()
            ->title('Amount berhasil diubah')
            ->body(count($this->editingIds) . ' anggota diperbarui.')
            ->success()
            ->send();
    }

    // ── Aktif/Nonaktif & Hapus ───────────────────────────────────────────────

    public function toggleActive(string $id): void
    {
        $member = EmployeeKoperasi::findOrFail($id);

        abort_unless(Auth::user()->can('update', $member), 403);

        $member->update(['is_active' => ! $member->is_active]);
    }

    public function removeMember(string $id): void
    {
        $member = EmployeeKoperasi::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $member), 403);

        $member->delete();
        $this->selected = array_values(array_diff($this->selected, [$id]));

        Notification::make()
            ->title('Anggota koperasi dihapus')
            ->success()
            ->send();
    }

    public function openImportModal(): void
    {
        abort_unless(Auth::user()->can('create', EmployeeKoperasi::class) && Auth::user()->can('update', EmployeeKoperasi::class), 403);

        $this->reset('importFile', 'importErrors');
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
    }

    public function import(): void
    {
        abort_unless(Auth::user()->can('create', EmployeeKoperasi::class) && Auth::user()->can('update', EmployeeKoperasi::class), 403);
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEUANGAN);

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120'], [], ['importFile' => 'file']);

        $import = new KoperasiImport($this->companyId);
        Excel::import($import, $this->importFile->getRealPath());

        $this->importErrors = $import->errors;
        $this->importFile = null;

        $notification = Notification::make()
            ->title("Import selesai: {$import->created} ditambah, {$import->updated} diperbarui")
            ->body($import->errors ? count($import->errors) . ' baris dilewati (lihat daftar).' : 'Akan ikut ke payroll mulai Generate/Regenerate berikutnya.');

        $import->errors ? $notification->warning()->send() : $notification->success()->send();
    }

    public function export()
    {
        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'semua-perusahaan';

        return Excel::download(
            new KoperasiExport($this->members),
            'koperasi-' . str($companyName)->slug() . '.xlsx'
        );
    }

    public function render()
    {
        return view('livewire.keuangan.koperasi-page');
    }
}
