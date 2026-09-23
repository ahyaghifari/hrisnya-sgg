<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Sp\EmployeeSp;
use App\Services\Sp\SpPdfService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail SP — sisi HC, read-only. Padanan CoachingDetailPage. Restrukturisasi 2026-09-25:
 * tampilkan sp_items (poin pelanggaran) + sp_reports (Berita Acara, internal HR).
 */
#[Layout('components.layout', ['title' => 'Detail Surat Peringatan'])]
class SpDetailPage extends Component
{
    public EmployeeSp $record;

    public function mount(EmployeeSp $record): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('View:EmployeeSp'), 403);
        abort_unless($record->company_id === $user->company_id || PermissionHelper::hasGlobalRole($user), 403);

        $record->load(['user', 'items', 'report', 'createdBy', 'knowsBy', 'coaching']);

        $this->record = $record;
    }

    public function downloadPdf()
    {
        return app(SpPdfService::class)->download($this->record);
    }

    public function render()
    {
        return view('livewire.sp.sp-detail-page');
    }
}
