<?php

namespace App\Livewire\Task;

use App\Models\SuratTugas\SuratTugas;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Detail Surat Perintah Tugas terbit untuk karyawan yang ditugaskan (dari halaman Tugas). */
#[Layout('components.layout', ['title' => 'Surat Perintah Tugas'])]
class SuratTugasDetailPage extends Component
{
    public SuratTugas $surat;

    public function mount(SuratTugas $surat): void
    {
        abort_unless(
            $surat->isTerbit() && $surat->peserta()->where('user_id', Auth::id())->exists(),
            403
        );

        $this->surat = $surat->load(['peserta', 'periode']);
    }

    public function render()
    {
        return view('livewire.task.surat-tugas-detail-page');
    }
}
