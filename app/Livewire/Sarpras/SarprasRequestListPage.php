<?php

namespace App\Livewire\Sarpras;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Sarpras\SarprasRequest;
use App\Services\Approval\ApprovalPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Daftar Sarana & Prasarana — 2 seksi: "Permohonan Saya" (yang diajukan sendiri) dan "Menunggu
 * Tindakan Saya" (antrean approval, lewat ApprovalPermissionService::pendingApprovalsFor() yang
 * sudah generic lintas modul, bukan query manual). Tidak dipaginasi — volume modul baru ini
 * masih kecil, tambahkan WithPagination kalau nanti kebukti perlu.
 */
#[Layout('components.layout', ['title' => 'Sarana & Prasarana'])]
class SarprasRequestListPage extends Component
{
    public function getMineProperty()
    {
        return SarprasRequest::where('user_id', Auth::id())->latest()->get();
    }

    public function getQueueProperty()
    {
        $activities = app(ApprovalPermissionService::class)
            ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::SARPRAS_REQUEST->value);

        $moduleIds = $activities->pluck('snapshot.module_id')->filter();

        return SarprasRequest::whereIn('id', $moduleIds)->latest()->get();
    }

    public function render()
    {
        return view('livewire.sarpras.sarpras-request-list-page');
    }
}
