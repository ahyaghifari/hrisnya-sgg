<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\GajiPokokKenaikanOverride;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Kenaikan Gaji Pokok" (2026-09-04, instruksi user) — master persen kenaikan Gaji
 * Pokok per user, pengganti App\Enum\Payroll\GajiPokokKenaikanOverrideEnum yang hardcode 1
 * user (ternyata bukan cuma 1 orang yang kenaikannya beda). Dicek GajiPokokCalculator, urutan
 * prioritas: Gaji Khusus (PayrollComponentOverridesPage, skip Calculator total) -> baris aktif
 * di sini -> Setting `gaji_pokok_kenaikan_per_kelas` per company (default 8%). Pola sama
 * PayrollComponentOverridesPage (company filter+search, modal tambah/edit) - beda cuma field
 * "rate" (persen, plain, bukan nominal) gantiin "amount"+"component".
 */
#[Layout('components.layout', ['title' => 'Kenaikan Gaji Pokok'])]
class GajiPokokKenaikanOverridesPage extends Component
{
    public ?string $companyId = null;
    public string $search = '';

    public bool $showAddModal = false;
    public ?string $addUserId = null;
    public ?string $addRate = null;
    public ?string $addNote = null;

    public ?string $editingId = null;
    public ?string $editRate = null;
    public ?string $editNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_KENAIKAN_GAJI_POKOK->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_KENAIKAN_GAJI_POKOK->value);
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getOverridesProperty()
    {
        $search = trim($this->search);

        return GajiPokokKenaikanOverride::query()
            ->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId)
                ->when($search !== '', fn ($q2) => $q2->where(fn ($q3) => $q3
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"))))
            ->with('user')
            ->latest()
            ->get();
    }

    public function getAvailableUsersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function openAddModal(): void
    {
        abort_unless($this->canEdit, 403);

        $this->addUserId = null;
        $this->addRate = null;
        $this->addNote = null;
        $this->resetErrorBag();
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function saveAdd(): void
    {
        abort_unless($this->canEdit, 403);

        $this->validate([
            'addUserId' => 'required|exists:users,id',
            'addRate' => 'required|numeric|min:0|max:100',
        ], [], [
            'addUserId' => 'karyawan',
            'addRate' => 'persen kenaikan',
        ]);

        abort_if(
            GajiPokokKenaikanOverride::where('user_id', $this->addUserId)->exists(),
            409,
            'Karyawan ini sudah punya baris Kenaikan Gaji Pokok — ubah persennya dari daftar, bukan tambah lagi.'
        );

        try {
            GajiPokokKenaikanOverride::create([
                'user_id' => $this->addUserId,
                'rate' => $this->addRate,
                'note' => $this->addNote,
                'is_active' => true,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            abort(409, 'Karyawan ini sudah punya baris Kenaikan Gaji Pokok — coba muat ulang halaman.');
        }

        $this->showAddModal = false;

        Notification::make()
            ->title('Kenaikan Gaji Pokok berhasil ditambahkan')
            ->body('Akan dipakai mulai Generate/Regenerate berikutnya, menggantikan persen default company untuk karyawan ini.')
            ->success()
            ->send();
    }

    public function openEditModal(string $id): void
    {
        abort_unless($this->canEdit, 403);

        $override = GajiPokokKenaikanOverride::findOrFail($id);

        $this->editingId = $id;
        $this->editRate = (string) $override->rate;
        $this->editNote = $override->note;
        $this->resetErrorBag();
    }

    public function closeEditModal(): void
    {
        $this->editingId = null;
    }

    public function saveEdit(): void
    {
        abort_unless($this->canEdit, 403);

        $this->validate([
            'editRate' => 'required|numeric|min:0|max:100',
        ], [], [
            'editRate' => 'persen kenaikan',
        ]);

        GajiPokokKenaikanOverride::findOrFail($this->editingId)->update([
            'rate' => $this->editRate,
            'note' => $this->editNote,
        ]);

        $this->editingId = null;

        Notification::make()->title('Kenaikan Gaji Pokok berhasil diubah')->success()->send();
    }

    public function toggleActive(string $id): void
    {
        abort_unless($this->canEdit, 403);

        $override = GajiPokokKenaikanOverride::findOrFail($id);
        $override->update(['is_active' => ! $override->is_active]);
    }

    public function removeOverride(string $id): void
    {
        abort_unless($this->canEdit, 403);

        GajiPokokKenaikanOverride::findOrFail($id)->delete();

        Notification::make()->title('Kenaikan Gaji Pokok dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.keuangan.gaji-pokok-kenaikan-overrides-page');
    }
}
