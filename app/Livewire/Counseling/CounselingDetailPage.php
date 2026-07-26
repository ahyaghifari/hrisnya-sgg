<?php

namespace App\Livewire\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use App\Services\Counseling\CounselingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Counseling — dua sisi beda hak (instruksi user 2026-07-26): konselor bisa balas
 * (response+solution) kalau masih PENDING, karyawan yang konsul (user_id) cuma bisa lihat
 * read-only (issue miliknya + status/tanggapan begitu sudah dibalas) — dia sebelumnya sama
 * sekali tidak punya halaman detail, cuma preview terpotong di list. Gate BUKAN lagi permission
 * Shield `View:Counseling`/company — murni ikut Counseling::isVisibleTo() (user_id ATAU
 * counselor_id), termasuk Super Admin tidak bisa buka punya orang lain.
 */
#[Layout('components.layout', ['title' => 'Detail Counseling'])]
class CounselingDetailPage extends Component
{
    public Counseling $record;
    public string $response = '';
    public string $solution = '';

    public function mount(Counseling $record): void
    {
        abort_unless($record->isVisibleTo(Auth::id()), 403);

        $record->load(['user', 'counselor', 'respondedBy']);

        $this->record = $record;
        $this->response = (string) $record->response;
        $this->solution = (string) $record->solution;
    }

    public function getIsCounselorProperty(): bool
    {
        return $this->record->counselor_id === Auth::id();
    }

    public function getCanRespondProperty(): bool
    {
        return $this->record->status === CounselingStatus::PENDING->value
            && $this->isCounselor;
    }

    /** Tombol "Kembali" beda tujuan tergantung yang buka: konselor -> inbox-nya, karyawan yang
     * konsul -> riwayat konsul pribadinya. */
    public function getBackRouteProperty(): string
    {
        return $this->isCounselor ? route('counseling.kelola') : route('counseling');
    }

    public function submitResponse(): void
    {
        abort_unless($this->canRespond, 403);

        $data = $this->validate([
            'response' => ['required', 'string'],
            'solution' => ['required', 'string'],
        ]);

        app(CounselingService::class)->respond($this->record, $data['response'], $data['solution']);

        $this->record->refresh();

        Notification::make()->title('Counseling berhasil dibalas')->success()->send();
    }

    public function render()
    {
        return view('livewire.counseling.counseling-detail-page');
    }
}
