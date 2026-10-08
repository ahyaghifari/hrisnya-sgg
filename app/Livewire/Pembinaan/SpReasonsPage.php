<?php

namespace App\Livewire\Pembinaan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Models\Sp\SpReason;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Master pelanggaran per perusahaan (instruksi user 2026-10-06) - dasar klarifikasi BA & poin SP
 * dipilih dari sini. Baris bawaan (company_id NULL) hanya bisa diaktif/nonaktifkan oleh admin
 * global; perusahaan mengelola barisnya sendiri. Gerbang sama dengan Pengaturan Pembinaan.
 */
#[Layout('components.layout', ['title' => 'Master Pelanggaran'])]
class SpReasonsPage extends Component
{
    #[Url]
    public string $kind = SpReason::KIND_PELANGGARAN;

    public ?string $editingId = null;

    public string $name = '';

    /** Level SP pelanggaran (hanya tab Pelanggaran). */
    public string $level = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);

        if (! in_array($this->kind, [SpReason::KIND_PELANGGARAN, SpReason::KIND_DASAR], true)) {
            $this->kind = SpReason::KIND_PELANGGARAN;
        }
    }

    public function updatedKind(): void
    {
        $this->cancel();
    }

    public function getReasonsProperty()
    {
        return SpReason::where('kind', $this->kind)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', Auth::user()->company_id))
            ->orderBy('name')->get();
    }

    /** Baris yang boleh diubah user ini: milik perusahaannya sendiri (admin global tanpa perusahaan mengubah baris bawaan). */
    private function findEditable(string $id): SpReason
    {
        return SpReason::where('kind', $this->kind)->where('company_id', Auth::user()->company_id)->findOrFail($id);
    }

    public function edit(string $id): void
    {
        $this->editingId = $id;
        $reason = $this->findEditable($id);
        $this->name = $reason->name;
        $this->level = $reason->sp_level?->value ?? '';
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'level');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $isPelanggaran = $this->kind === SpReason::KIND_PELANGGARAN;
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => $isPelanggaran ? ['required', 'in:' . implode(',', array_column(SpLevel::cases(), 'value'))] : ['nullable'],
        ], [], ['name' => 'Nama', 'level' => 'Level SP']);
        $level = $isPelanggaran ? $this->level : null;

        $companyId = Auth::user()->company_id;

        abort_if(
            SpReason::where('kind', $this->kind)->where('company_id', $companyId)->where('name', $this->name)->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists(),
            422,
            'Nama sudah ada.'
        );

        $this->editingId
            ? $this->findEditable($this->editingId)->update(['name' => $this->name, 'sp_level' => $level])
            : SpReason::create(['company_id' => $companyId, 'kind' => $this->kind, 'sp_level' => $level, 'name' => $this->name, 'is_active' => true]);

        $this->cancel();
    }

    public function toggle(string $id): void
    {
        $reason = $this->findEditable($id);
        $reason->update(['is_active' => ! $reason->is_active]);
    }

    public function render()
    {
        return view('livewire.pembinaan.sp-reasons-page');
    }
}
