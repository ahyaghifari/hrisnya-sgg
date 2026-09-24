<?php

namespace App\Livewire\Sp;

use App\Models\Sp\EmployeeSp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail 1 SP milik karyawan sendiri - read-only, lihat isi surat (poin pelanggaran) lengkap.
 * Cuma pemilik SP yang boleh buka - SP bukan data yang dilihat atasan lewat halaman ini (HR
 * pakai EmployeeSpResource Filament / SpDetailPage portal HC).
 *
 * Restrukturisasi 2026-09-25 (instruksi user, lihat diagram ER): form "isi tanggapan" DIHAPUS -
 * klarifikasi karyawan sekarang diambil SAAT proses investigasi HR (sp_reports.subject_clarification,
 * Berita Acara, HR-internal), BUKAN self-service setelah SP terbit. Berita Acara sengaja TIDAK
 * ditampilkan di sini (internal HR).
 */
#[Layout('components.layout', ['title' => 'Detail Surat Peringatan'])]
class SuratPeringatanDetailPage extends Component
{
    public EmployeeSp $record;

    public function mount(EmployeeSp $record): void
    {
        abort_unless($record->user_id === Auth::id(), 403);

        $record->load(['items', 'createdBy']);

        $this->record = $record;
    }

    public function render()
    {
        return view('livewire.sp.surat-peringatan-detail-page');
    }
}
