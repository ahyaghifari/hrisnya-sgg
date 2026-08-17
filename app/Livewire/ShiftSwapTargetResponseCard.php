<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\Shift\ShiftSwapRequests;
use App\Services\Shift\ShiftSwapRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Card kecil berdiri sendiri (BUKAN nempel di halaman detail, instruksi user 2026-08-16) - daftar
 * pengajuan tukar shift orang lain yang jadwalnya mau ditukar dengan MILIK USER LOGIN (target_id),
 * tanggapan Setuju/Tolak 1-klik langsung dari card ini. Sifatnya murni informasi buat approver,
 * TIDAK PERNAH menggerbang alur approval - lihat App\Services\Shift\ShiftSwapRequestService.
 */
class ShiftSwapTargetResponseCard extends Component
{
    public function respond(string $id, string $response): void
    {
        $request = ShiftSwapRequests::findOrFail($id);

        try {
            app(ShiftSwapRequestService::class)->recordTargetResponse($request, Auth::user(), $response);

            Notification::make()
                ->title($response === 'accepted' ? 'Tanggapan "Setuju" terkirim' : 'Tanggapan "Tolak" terkirim')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal mengirim tanggapan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /** Setting OFF -> card selalu kosong (tidak pernah render), konsisten dengan "setting OFF =
     * tidak ada satupun perilaku yang berubah" - lihat planning/2026-08-16-fitur-tukar-shift.md. */
    public function getPendingResponsesProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        $enabled = Setting::resolve(
            ShiftSwapRequestService::ASK_TARGET_RESPONSE_SETTING_KEY,
            $user->company_id,
            default: false
        );

        if (! $enabled) {
            return collect();
        }

        return ShiftSwapRequests::where('target_id', $user->id)
            ->whereNull('target_response')
            ->with(['requester', 'reqSchedule.shiftType'])
            ->orderBy('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.shift-swap-target-response-card');
    }
}
