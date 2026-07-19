<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\UserKoperasi;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Roster anggota koperasi (portal, custom List.js — pola sama PayrollPeriodsPage) — sumber
 * yang otomatis di-sync ke payroll_manual_lines tiap Generate Payroll, lihat
 * PayrollService::syncKoperasiManualLines().
 */
#[Layout('components.layout', ['title' => 'Koperasi'])]
class KoperasiPage extends Component
{
    public ?string $companyId = null;

    public array $selected = [];

    public bool $showAddModal = false;
    public ?string $addUserId = null;
    public ?string $addAmount = null;

    public bool $showEditModal = false;
    public array $editingIds = [];
    public ?string $editAmount = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    public function updatedCompanyId(): void
    {
        $this->selected = [];
        $this->dispatch('list-table:refresh');
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
        return UserKoperasi::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId))
            ->with('user')
            ->get();
    }

    /**
     * Karyawan aktif di company terpilih yang belum jadi anggota koperasi DAN tipe
     * karyawannya terdaftar sebagai penerima Koperasi di halaman "Penerima Komponen Gaji" —
     * kalau tidak difilter di sini, admin bisa nambah anggota yang potongannya tidak akan
     * pernah muncul di payroll (PayrollService::runComponent() tetap gate berdasarkan
     * PayrollComponentRecipient, roster ini tidak bisa mem-bypass itu).
     */
    public function getAvailableUsersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        $eligibleTypeIds = PayrollComponentRecipient::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::KOPERASI->value)
            ->pluck('employee_type_id');

        return User::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->whereDoesntHave('koperasi')
            ->whereHas('profile', fn ($q) => $q->whereIn('employee_type_id', $eligibleTypeIds))
            ->orderBy('name')
            ->get();
    }

    // ── Tambah Anggota ──────────────────────────────────────────────────────

    public function openAddModal(): void
    {
        $this->addUserId = null;
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
        $this->validate([
            'addUserId' => 'required|exists:users,id',
            'addAmount' => 'required|numeric|min:0.01',
        ], [], [
            'addUserId' => 'karyawan',
            'addAmount' => 'nominal',
        ]);

        $user = User::findOrFail($this->addUserId);

        abort_unless(
            PayrollComponentRecipient::isEligible($user->company_id, PayrollComponentEnum::KOPERASI->value, $user->profile?->employee_type_id),
            403,
            'Tipe karyawan ini belum terdaftar sebagai penerima Koperasi di halaman Penerima Komponen Gaji.'
        );

        // Dropdown "availableUsers" sudah exclude karyawan yang sudah jadi anggota, tapi itu
        // cuma filter tampilan — bukan jaminan atomik (dua tab/klik ganda bisa lolos bareng
        // sebelum salah satu commit). Cek eksplisit di sini + constraint unique di DB
        // (user_koperasi.user_id) yang jadi jaminan sesungguhnya: SATU baris per karyawan,
        // tidak ditimpa diam-diam kalau ternyata sudah ada.
        abort_if(
            UserKoperasi::where('user_id', $this->addUserId)->exists(),
            409,
            'Karyawan ini sudah jadi anggota koperasi — ubah nominalnya dari daftar, bukan tambah lagi.'
        );

        try {
            UserKoperasi::create([
                'user_id' => $this->addUserId,
                'amount' => $this->addAmount,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Karyawan ini sudah jadi anggota koperasi — coba muat ulang halaman.');
        }

        $this->showAddModal = false;
        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Anggota koperasi berhasil ditambahkan')
            ->body('Akan ikut ke payroll mulai Generate/Regenerate berikutnya.')
            ->success()
            ->send();
    }

    // ── Edit Amount (satu atau beberapa baris yang dicentang) ──────────────

    public function openEditModal(?string $id = null): void
    {
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
        $this->validate([
            'editAmount' => 'required|numeric|min:0.01',
        ], [], [
            'editAmount' => 'nominal',
        ]);

        UserKoperasi::whereIn('id', $this->editingIds)->update(['amount' => $this->editAmount]);

        $this->showEditModal = false;
        $this->selected = [];
        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Amount berhasil diubah')
            ->body(count($this->editingIds) . ' anggota diperbarui.')
            ->success()
            ->send();
    }

    // ── Aktif/Nonaktif & Hapus ───────────────────────────────────────────────

    public function toggleActive(string $id): void
    {
        $member = UserKoperasi::findOrFail($id);
        $member->update(['is_active' => ! $member->is_active]);

        $this->dispatch('list-table:refresh');
    }

    public function removeMember(string $id): void
    {
        UserKoperasi::whereKey($id)->delete();
        $this->selected = array_values(array_diff($this->selected, [$id]));

        $this->dispatch('list-table:refresh');

        Notification::make()
            ->title('Anggota koperasi dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.koperasi-page');
    }
}
