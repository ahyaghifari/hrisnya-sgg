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
 * Detail Counseling — dua lapis hak (instruksi user 2026-08-17, redesign dari gerbang biner
 * lama): siapa pun yang punya akses modul Coaching & Counseling (SISTEM_PEMBINAAN_KARYAWAN)
 * boleh BUKA halaman ini (Counseling::isAccessibleTo()), tapi isi issue/response/solution cuma
 * kebaca kalau identity match (user_id/counselor_id) atau is_secret=false
 * (Counseling::isContentVisibleTo()) — selain itu di-redact "🔒 Rahasia", bukan 403 total.
 * Konselor bisa balas (response+solution) kalau masih PENDING & isCounselor - itu SELALU
 * identity match, jadi otomatis subset dari isContentVisibleTo, tidak perlu gate tambahan.
 */
#[Layout('components.layout', ['title' => 'Detail Counseling'])]
class CounselingDetailPage extends Component
{
    public Counseling $record;
    public string $response = '';
    public string $solution = '';

    public function mount(Counseling $record): void
    {
        abort_unless($record->isAccessibleTo(Auth::user()), 403);

        $record->load(['user', 'counselor', 'respondedBy']);

        $this->record = $record;

        // Field respond form ini di-prefill dari record - kalau kontennya tidak boleh dibaca
        // viewer ini (redacted), JANGAN prefill sama sekali, biar ciphertext-decrypted content
        // tidak nyangkut di state Livewire (bisa ke-expose ke HTML) buat viewer yang tidak berhak.
        if ($record->isContentVisibleTo(Auth::user())) {
            $this->response = (string) $record->response;
            $this->solution = (string) $record->solution;
        }
    }

    public function getIsContentVisibleProperty(): bool
    {
        return $this->record->isContentVisibleTo(Auth::user());
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
        return $this->isCounselor ? route('counseling.kelola') : route('pembinaan.saya');
    }

    public function submitResponse(): void
    {
        // Cek langsung ke record (BUKAN lewat $this->canRespond) - computed property getX...
        // Property Livewire di-memoize sekali per request begitu diakses; kalau guard ini pakai
        // $this->canRespond, nilainya (true, dihitung dari status masih PENDING) ke-cache dan
        // dipakai ulang saat render() nanti walau status sudah diubah jadi DONE di bawah -
        // tombol "Kirim Balasan" jadi nyangkut kelihatan padahal sudah kepencet.
        abort_unless(
            $this->record->status === CounselingStatus::PENDING->value && $this->isCounselor,
            403
        );

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
