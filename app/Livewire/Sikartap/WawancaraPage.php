<?php

namespace App\Livewire\Sikartap;

use App\Livewire\Sikartap\Concerns\InteractsWithSikartapJadwalList;
use App\Livewire\Sikartap\Concerns\ManagesSikartapTesWawancara;
use App\Models\Sikartap\Sikartap;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal Wawancara Karyawan Tetap lintas pengajuan (/sikartap/wawancara). Tampil: pengajuan
 * yang tesnya lulus (siap dijadwalkan) + yang sudah punya jadwal wawancara. Penjadwalan bisa
 * massal (centang beberapa orang, satu form). Isi hasil wawancara tetap per orang. Semua ini
 * juga masih bisa lewat PengajuanDetailPage.
 */
#[Layout('components.layout', ['title' => 'Wawancara Karyawan Tetap'])]
class WawancaraPage extends Component
{
    use InteractsWithSikartapJadwalList;
    use ManagesSikartapTesWawancara;
    use WithPagination;

    protected function tahapQuery()
    {
        return Sikartap::query()
            ->where(fn ($q) => $q->where('status_tes', 'lulus')->orWhereHas('interviewSchedule'));
    }

    // ── Penjadwalan (massal) ────────────────────────────────────────────

    public function openScheduleModal(): void
    {
        abort_unless($this->canUpdate, 403);

        $this->resetErrorBag();
        $this->activePanel = 'schedule';
        $this->interviewScheduleForm = ['tanggal_wawancara' => null, 'waktu_wawancara' => null, 'tempat_wawancara' => null, 'pewawancara' => null, 'pewawancara_2' => null, 'keterangan' => null];
    }

    public function scheduleRow(string $sikartapId): void
    {
        $this->selected = [$sikartapId];
        $this->openScheduleModal();
    }

    public function saveInterviewSchedule(): void
    {
        $this->applyScheduleToSelected(
            fn (Sikartap $s) => static::canScheduleInterview($s),
            $this->interviewScheduleRules(),
            fn (Sikartap $s) => $this->applyInterviewSchedule($s, $this->interviewScheduleForm),
            'Jadwal wawancara',
        );
    }

    // ── Hasil (per baris) ──────────────────────────────────────────────

    public function openResultModal(string $sikartapId): void
    {
        abort_unless($this->canUpdate, 403);

        $this->resetErrorBag();
        $this->resultId = $sikartapId;
        $this->activePanel = 'result';
        $this->interviewResultForm = Sikartap::findOrFail($sikartapId)->only(['status_wawancara', 'catatan_wawancara']);
    }

    public function render()
    {
        return view('livewire.sikartap.wawancara-page', [
            'canFilterByCompany' => \Illuminate\Support\Facades\Auth::user()->canFilterByCompany(),
        ]);
    }
}
