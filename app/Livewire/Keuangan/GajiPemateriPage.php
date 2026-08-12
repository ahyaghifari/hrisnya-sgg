<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\Trainer\TrainerAssessmentResults;
use App\Models\Master\Companies;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versi portal dari TrainerSalaryResource (/admin/trainer-salaries) — read-only sama seperti
 * admin-nya (EditAction/form di sana memang kosong), cuma tambah Hapus. Permission sengaja
 * manual sendiri (LIHAT_GAJI_PEMATERI/KELOLA_GAJI_PEMATERI), bukan reuse
 * ViewAny/Delete:TrainerAssessmentResults milik admin — pola sama LIHAT_OMSET/KELOLA_OMSET.
 */
#[Layout('components.layout', ['title' => 'Gaji Pemateri'])]
class GajiPemateriPage extends Component
{
    public ?string $companyId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_GAJI_PEMATERI->value), 403);

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

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_GAJI_PEMATERI->value);
    }

    public function getResultsProperty()
    {
        return TrainerAssessmentResults::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->with(['trainer', 'course', 'meeting'])
            ->orderByDesc('valid_until')
            ->get();
    }

    public function removeResult(string $id): void
    {
        abort_unless($this->canEdit, 403);

        TrainerAssessmentResults::whereKey($id)->delete();

        Notification::make()
            ->title('Data gaji pemateri berhasil dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.gaji-pemateri-page');
    }
}
