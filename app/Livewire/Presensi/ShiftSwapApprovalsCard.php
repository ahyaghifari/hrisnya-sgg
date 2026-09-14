<?php

namespace App\Livewire\Presensi;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Shift\ShiftSwapRequests;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Shift\ShiftSwapRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget KHUSUS buat rekan yang jadwalnya diminta ditukar - dia approver STEP 1 sungguhan
 * (2026-08-20, lihat ShiftSwapRequestService::createApprovalSnapshot()), TERPISAH dari
 * "Butuh Persetujuan Anda" (PendingApprovalsCard) - instruksi user: widget itu murni buat
 * approval berbasis manajemen/atasan, bukan sesama rekan kerja. PendingApprovalsCard sendiri
 * sudah mengecualikan step 1 modul ini (current_step_order !== 1) supaya tidak dobel tampil.
 * Approve/reject langsung 1-klik di sini (beda dari halaman Detail yang wajib isi catatan) -
 * konsisten sama pola card ringkas lain (mis. ShiftSwapTargetResponseCard versi lama,
 * sudah tidak dipakai lagi karena fitur ini menggantikannya).
 */
class ShiftSwapApprovalsCard extends Component
{
    /** Keterangan versi rekan - BUKAN reuse FormatsShiftSwapRequest::keterangan() (itu selalu
     * nyebut nama target, di sini pembacanya justru si target sendiri, jadi nama pemohon sudah
     * cukup jadi judul kartu). */
    public function keterangan(ShiftSwapRequests $record): string
    {
        $req = $record->reqSchedule?->shiftType?->name . ' (' . $record->req_date->translatedFormat('d M Y') . ')';

        if ($record->targetSchedule) {
            $target = $record->targetSchedule->shiftType?->name . ' (' . $record->target_date->translatedFormat('d M Y') . ')';

            return "Mengajukan tukar shift {$req} miliknya dengan shift Anda {$target}.";
        }

        return "Mau menyerahkan shift {$req} miliknya ke Anda.";
    }

    /** Penjelasan efek kalau DISETUJUI - level "hasil akhir", bukan mekanisme (tanggal sama
     * nuker kategori shift, tanggal beda nuker pemilik baris, atau fallback Extra Shift kalau
     * ternyata bentrok - detail itu baru dicek ulang & dieksekusi pas approval final, lihat
     * ShiftSwapRequestService::executeApprovedSwap()). Rekan cukup tahu jadwalnya jadi apa.
     * Return-nya HTML (bagian yang BERUBAH di-bold) - blade render pakai {!! !!}, semua
     * potongan dinamis di-escape lewat e() dulu. */
    public function efek(ShiftSwapRequests $record): string
    {
        $reqType = e($record->reqSchedule?->shiftType?->name);
        $reqDate = e($record->req_date->translatedFormat('d M Y'));
        $requesterName = e($record->requester?->name ?? 'Pemohon');

        if (! $record->targetSchedule) {
            return "Jadwal shift Anda tanggal {$reqDate} akan terisi <strong>{$reqType}</strong>, menggantikan {$requesterName}.";
        }

        $targetType = e($record->targetSchedule->shiftType?->name);
        $targetDate = e($record->target_date->translatedFormat('d M Y'));

        if ($record->req_date->equalTo($record->target_date)) {
            return "Shift Anda tanggal {$targetDate} berubah dari {$targetType} jadi <strong>{$reqType}</strong>. Shift {$requesterName} berubah jadi <strong>{$targetType}</strong>.";
        }

        return "Anda pindah tugas ke shift <strong>{$reqType} tanggal {$reqDate}</strong>. {$requesterName} pindah ke shift Anda (<strong>{$targetType} tanggal {$targetDate}</strong>).";
    }

    /** null kalau tidak ada konflik - rekan TIDAK akan double shift kalau disetujui. Lihat
     * ShiftSwapRequestService::targetDoubleShiftWarning() (instruksi user 2026-09-14: widget ini
     * sebelumnya diam sama sekali soal kemungkinan double shift, padahal itu efek nyata buat
     * rekan kalau dia approve). */
    public function doubleShiftWarning(ShiftSwapRequests $record): ?string
    {
        return app(ShiftSwapRequestService::class)->targetDoubleShiftWarning($record);
    }

    public function getPendingProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        $ids = app(ApprovalPermissionService::class)
            ->pendingApprovalsFor($user, ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value)
            ->filter(fn ($activity) => (int) $activity->current_step_order === 1)
            ->pluck('snapshot.module_id')->filter()->values();

        return ShiftSwapRequests::whereIn('id', $ids)
            ->with(['requester', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->orderBy('created_at')
            ->get();
    }

    public function approve(string $id): void
    {
        $this->act($id, 'approved', 'Rekan menyetujui tukar shift');
    }

    public function reject(string $id): void
    {
        $this->act($id, 'rejected', 'Rekan menolak tukar shift');
    }

    private function act(string $id, string $action, string $note): void
    {
        $record = ShiftSwapRequests::with('approvalSnapshot.activity')->findOrFail($id);

        abort_unless($record->target_id === Auth::id(), 403);

        // target_id doang tidak cukup - begitu step 1 (giliran rekan) selesai, snapshot maju ke
        // step atasan tapi target_id di record TETAP sama, jadi rekan yang tahu $id request
        // miliknya sendiri bisa manggil act() lagi dan membajak approval atasan lewat
        // advanceStep() (yang sendiri tidak validasi identitas aktor). Cek current_step_order
        // masih 1 di sini - JANGAN lewat computed property $this->pending yang bisa basi.
        abort_unless(
            (int) ($record->approvalSnapshot?->activity?->current_step_order) === 1,
            403,
            'Giliran approval sudah lewat dari Anda.'
        );

        try {
            app(ApprovalFlowService::class)->advanceStep(
                $record->approvalSnapshot,
                Auth::id(),
                $note,
                $action,
            );

            // Rekan = approver step 1, sekalian isi kolom "Tanggapan Rekan" biar konsisten.
            app(ShiftSwapRequestService::class)->syncTargetResponseFromApprovalStep(
                $record,
                Auth::id(),
                1,
                $action,
            );

            Notification::make()
                ->title($action === 'approved' ? 'Tukar shift disetujui' : 'Tukar shift ditolak')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal memproses: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.presensi.shift-swap-approvals-card');
    }
}
