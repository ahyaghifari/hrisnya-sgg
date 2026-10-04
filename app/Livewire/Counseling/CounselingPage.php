<?php

namespace App\Livewire\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use App\Models\User;
use App\Services\Counseling\CounselingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Counseling (personal) — riwayat keluhan milik sendiri + form ajukan baru lewat modal
 * ("Konsul"). Reuse App\Services\Counseling\CounselingService::create() — sama service yang
 * dipakai App\Http\Controllers\Api\Counseling\CounselingController (mobile).
 *
 * Karyawan menunjuk sendiri "Konselor" (rekan kerja mana pun di company yang sama) saat
 * konsul, dan pilih sendiri apakah is_secret (default true) — kalau true, isi keluhan+tanggapan
 * cuma dia & konselor yang ditunjuk yang boleh baca; kalau false, siapa pun yang punya akses
 * modul Coaching & Counseling juga boleh baca (lihat Counseling::isContentVisibleTo()).
 */
#[Layout('components.layout', ['title' => 'Counseling'])]
class CounselingPage extends Component
{
    public bool $showConsultModal = false;
    public string $issue = '';
    public string $counselorId = '';
    public bool $isSecret = true;

    public function openConsultModal(): void
    {
        $this->issue = '';
        $this->counselorId = '';
        $this->isSecret = true;
        $this->showConsultModal = true;
    }

    public function closeConsultModal(): void
    {
        $this->showConsultModal = false;
    }

    public function getCounselorOptionsProperty()
    {
        return User::whereCompany(Auth::user()->company_id)
            ->where('id', '!=', Auth::id())
            ->where('is_active', true)
            ->orderByName()
            ->get()->pluck('name', 'id');
    }

    /**
     * Tombol "Konsul ke Saya" — cuma buat karyawan BIASA (bukan atasan, tidak lihat dropdown
     * "Coaching & Counseling" di row 2 karena itu sekarang gerbangnya murni "punya staff")
     * dan cuma kalau ADA konsul yang masih PENDING (nunggu ditanggapi) — bukan sekadar pernah
     * jadi konselor. Yang sudah DONE semua tidak perlu tombolnya lagi.
     */
    public function getShowInboxButtonProperty(): bool
    {
        return ! Auth::user()->canAccessKaryawanMenu()
            && Counseling::where('counselor_id', Auth::id())
                ->where('status', CounselingStatus::PENDING->value)
                ->exists();
    }

    public function submitConsult(): void
    {
        $data = $this->validate([
            'issue' => ['required', 'string', 'max:2000'],
            // 'exists' saja tidak cukup — dropdown UI sudah exclude diri sendiri, tapi payload
            // Livewire bisa ditembak manual. Tanpa Rule::notIn ini, karyawan bisa jadi konselor
            // buat dirinya sendiri (lolos ke DB, cuma keliru di sisi UI).
            'counselorId' => ['required', 'exists:users,id', Rule::notIn([Auth::id()])],
        ], [], ['counselorId' => 'konselor']);

        app(CounselingService::class)->create((object) [
            'issue' => $data['issue'],
            'counselor_id' => $data['counselorId'],
            'is_secret' => $this->isSecret,
        ]);

        $this->showConsultModal = false;
        Notification::make()->title('Konsul berhasil diajukan')->success()->send();
    }

    public function render()
    {
        $counselings = Counseling::where('user_id', Auth::id())
            ->with(['respondedBy', 'counselor'])
            ->latest()
            ->get();

        return view('livewire.counseling.counseling-page', [
            'counselings' => $counselings,
        ]);
    }
}
