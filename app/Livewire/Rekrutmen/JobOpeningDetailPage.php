<?php

namespace App\Livewire\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Services\Job\RekrutmenSyncService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Lowongan Kerja + daftar pelamar — padanan ViewJobOpening + ApplicantRelationManager
 * admin. Edit lowongan diarahkan ke EditJobOpeningPage, lihat kandidat ke
 * CandidateApplicationDetailPage. Tidak ada tombol create manual — lowongan cuma muncul dari
 * approval Permintaan Tenaga Kerja.
 */
#[Layout('components.layout', ['title' => 'Detail Lowongan Kerja'])]
class JobOpeningDetailPage extends Component
{
    public JobOpenings $record;

    public function mount(JobOpenings $record): void
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403
        );

        $record->load(['jobPosition.orgStructure', 'company', 'applicants.candidate']);

        $this->record = $record;
    }

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can('Update:JobOpenings');
    }

    /** Sync manual ke sistem Rekrutmen eksternal - normalnya otomatis tiap Simpan/Publish/Tutup
     * (JobOpeningService, lihat RekrutmenSyncService::sync()) tapi gagalnya cuma dicatat log,
     * tidak pernah kelihatan user (sengaja non-fatal). Tombol ini buat re-trigger manual kalau
     * curiga sync sebelumnya gagal (mis. Rekrutmen sempat down), hasilnya kali ini DITAMPILKAN
     * ke user - beda dari sync otomatis yang diam-diam. Gerbang sama dengan edit (aksi
     * maintenance, bukan sekadar lihat).
     */
    public function syncToRekrutmen(): void
    {
        abort_unless($this->canEdit, 403);

        $result = app(RekrutmenSyncService::class)->sync($this->record);

        Notification::make()
            ->title($result['ok'] ? 'Berhasil disinkronkan ke Rekrutmen' : 'Gagal sinkronisasi')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    public function render()
    {
        return view('livewire.rekrutmen.job-opening-detail-page');
    }
}
