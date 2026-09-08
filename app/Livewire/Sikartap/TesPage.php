<?php

namespace App\Livewire\Sikartap;

use App\Livewire\Sikartap\Concerns\InteractsWithSikartapJadwalList;
use App\Livewire\Sikartap\Concerns\ManagesSikartapTesWawancara;
use App\Models\Sikartap\Sikartap;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal Tes Karyawan Tetap lintas pengajuan (/sikartap/tes). Tampil: pengajuan yang berkasnya
 * lolos (siap dijadwalkan) + yang sudah punya jadwal tes. Penjadwalan bisa massal (centang
 * beberapa orang, satu form). Isi hasil tes tetap per orang. Semua ini juga masih bisa lewat
 * PengajuanDetailPage.
 */
#[Layout('components.layout', ['title' => 'Tes Karyawan Tetap'])]
class TesPage extends Component
{
    use InteractsWithSikartapJadwalList;
    use ManagesSikartapTesWawancara;
    use WithPagination;

    protected function tahapQuery()
    {
        return Sikartap::query()
            ->where(fn ($q) => $q->where('status_berkas', 'approved')->orWhereHas('testSchedule'));
    }

    // ── Penjadwalan (massal) ────────────────────────────────────────────

    public function openScheduleModal(): void
    {
        abort_unless($this->canUpdate, 403);

        $this->resetErrorBag();
        $this->activePanel = 'schedule';
        $this->testScheduleForm = ['tanggal_tes' => null, 'waktu_tes' => null, 'tempat_tes' => null, 'keterangan' => null];
    }

    public function scheduleRow(string $sikartapId): void
    {
        $this->selected = [$sikartapId];
        $this->openScheduleModal();
    }

    public function saveTestSchedule(): void
    {
        $this->applyScheduleToSelected(
            fn (Sikartap $s) => static::canScheduleTest($s),
            $this->testScheduleRules(),
            fn (Sikartap $s) => $this->applyTestSchedule($s, $this->testScheduleForm),
            'Jadwal tes',
        );
    }

    // ── Hasil (per baris) ──────────────────────────────────────────────

    public function openResultModal(string $sikartapId): void
    {
        abort_unless($this->canUpdate, 403);

        $this->resetErrorBag();
        $this->resultId = $sikartapId;
        $this->activePanel = 'result';
        $this->testResultForm = Sikartap::findOrFail($sikartapId)->only(['nilai_tes', 'nilai_psikotes', 'status_tes', 'catatan_tes']);
    }

    public function render()
    {
        return view('livewire.sikartap.tes-page', [
            'canFilterByCompany' => \Illuminate\Support\Facades\Auth::user()->canFilterByCompany(),
        ]);
    }
}
