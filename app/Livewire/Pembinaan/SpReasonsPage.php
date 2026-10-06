<?php

namespace App\Livewire\Pembinaan;

use App\Enum\CustomPermissionEnum;
use App\Models\Sp\SpReason;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Master pelanggaran per perusahaan (instruksi user 2026-10-06) - dasar klarifikasi BA & poin SP
 * dipilih dari sini. Baris bawaan (company_id NULL) hanya bisa diaktif/nonaktifkan oleh admin
 * global; perusahaan mengelola barisnya sendiri. Gerbang sama dengan Pengaturan Pembinaan.
 */
#[Layout('components.layout', ['title' => 'Master Pelanggaran'])]
class SpReasonsPage extends Component
{
    public ?string $editingId = null;

    public string $name = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
    }

    public function getReasonsProperty()
    {
        return SpReason::where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', Auth::user()->company_id))
            ->orderBy('name')->get();
    }

    /** Baris yang boleh diubah user ini: milik perusahaannya sendiri (admin global tanpa perusahaan mengubah baris bawaan). */
    private function findEditable(string $id): SpReason
    {
        return SpReason::where('company_id', Auth::user()->company_id)->findOrFail($id);
    }

    public function edit(string $id): void
    {
        $this->editingId = $id;
        $this->name = $this->findEditable($id)->name;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255']], [], ['name' => 'Nama pelanggaran']);

        $companyId = Auth::user()->company_id;

        abort_if(
            SpReason::where('company_id', $companyId)->where('name', $this->name)->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists(),
            422,
            'Nama pelanggaran sudah ada.'
        );

        $this->editingId
            ? $this->findEditable($this->editingId)->update(['name' => $this->name])
            : SpReason::create(['company_id' => $companyId, 'name' => $this->name, 'is_active' => true]);

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
