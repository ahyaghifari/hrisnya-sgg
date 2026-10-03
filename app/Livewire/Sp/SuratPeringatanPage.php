<?php

namespace App\Livewire\Sp;

use App\Enum\Sp\SpReportStatus;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use App\Services\Sp\SpCaseList;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Surat Peringatan Saya" — riwayat LENGKAP SP milik user_id = auth()->id(), termasuk yang sudah
 * expired (dijawab user 2026-09-23, planning/2026-09-23-sp-dan-merit-point.md §5.2) - bukan cuma
 * yang aktif. Route baru, terpisah dari Coaching Saya (§2.1).
 */
#[Layout('components.layout', ['title' => 'Surat Peringatan'])]
class SuratPeringatanPage extends Component
{
    public function render()
    {
        // SP yang belum diterbitkan (draft) belum terlihat karyawan (instruksi user 2026-10-03).
        $sps = EmployeeSp::issued()->where('user_id', Auth::id())->with(['user', 'report'])->get();

        // Berita Acara alur baru (instruksi user 2026-10-01) - draft HC belum terlihat karyawan.
        $reports = SpReport::where('user_id', Auth::id())
            ->whereNotNull('status')
            ->where('status', '!=', SpReportStatus::DRAFT->value)
            ->with(['user', 'sp.document'])
            ->get();

        // SP dari BA ini yang masih draft dianggap belum ada: baris tetap di tahap sebelumnya.
        $reports->each(fn ($r) => $r->sp && ! $r->sp->isIssued() ? $r->setRelation('sp', null) : null);

        return view('livewire.sp.surat-peringatan-page', ['cases' => SpCaseList::merge($reports, $sps)]);
    }
}
