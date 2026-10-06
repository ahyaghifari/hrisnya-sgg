<?php

namespace App\Services\Approval;

use App\Models\Master\JobPositions;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalTimelineService
{
    /**
     * Bangun timeline visual: Diajukan -> tiap step approval -> selesai. Generik lintas modul —
     * cuma butuh relasi approvalSnapshot()/approvalHistories()/user() dan kolom created_at
     * (dipakai attendance_request maupun user_transition), bukan spesifik ke 1 model.
     * Step yang sudah lewat diisi nama+tanggal dari approvalHistories, step yang
     * belum dilewati dibiarkan kosong (belum ada aktor/tanggal).
     *
     * @return array<int, array{label: string, actor: ?string, date: mixed, state: string}>
     */
    public function build(Model $request): array
    {
        $request->loadMissing(['approvalSnapshot.activity', 'approvalHistories', 'user']);

        $snapshot = $request->approvalSnapshot;

        if (! $snapshot) {
            return [];
        }

        $steps = collect($snapshot->steps_snapshot ?? [])->sortBy('order')->values();

        $historyByStep = $request->approvalHistories
            ->whereIn('action', ['approved', 'rejected', 'cancelled'])
            ->keyBy(fn ($history) => $history->metadata['step_order'] ?? null);

        $timeline = [[
            'label' => 'Diajukan',
            // Nullable - user pengaju bisa sudah dihapus (lihat pola sama di
            // ApprovalFlowService::advanceStep()), jangan sampai timeline gagal render.
            'actor' => $request->user?->name ?? 'Seseorang',
            'date' => $request->created_at,
            'state' => 'done',
        ]];

        foreach ($steps as $step) {
            $history = $historyByStep->get($step['order']);

            if ($history) {
                $timeline[] = [
                    // 'cancelled' BUKAN aksi step ini - approver step ini belum sempat
                    // bertindak sama sekali, yang cancel bisa siapa saja (pengaju sendiri, dst).
                    // Jangan pakai stepLabel() di sini, nanti kelihatan seolah pembatalnya
                    // adalah pemegang jabatan approver step ini.
                    'label' => $history->action === 'cancelled' ? 'Dibatalkan' : $this->stepLabel($step),
                    'actor' => $history->actor_name,
                    'date' => $history->created_at,
                    'state' => $history->action,
                ];

                continue;
            }

            $isCurrent = (int) $snapshot->current_step_order === (int) $step['order']
                && $snapshot->status === 'in_review';

            $timeline[] = [
                'label' => $this->stepLabel($step),
                'actor' => null,
                'date' => null,
                'state' => $isCurrent ? 'current' : 'pending',
            ];
        }

        // Pembatalan oleh admin (panel admin) tidak membawa step_order - dia bukan aksi step
        // approval manapun, jadi dipasang sebagai entri penutup supaya tetap kelihatan di portal.
        $adminCancel = $request->approvalHistories
            ->where('action', 'cancelled')
            ->filter(fn ($history) => ! isset($history->metadata['step_order']))
            ->last();

        if ($adminCancel) {
            $timeline[] = [
                'label' => 'Dibatalkan',
                'actor' => $adminCancel->actor_name,
                'date' => $adminCancel->created_at,
                'state' => 'cancelled',
            ];
        }

        return $timeline;
    }

    private function stepLabel(array $step): string
    {
        return match ($step['approver_type'] ?? null) {
            'user' => User::find($step['approver_ref_id'])?->name ?? 'Approval',
            'role' => Role::find($step['approver_ref_id'])?->name ?? 'Approval',
            'job_position' => JobPositions::find($step['approver_ref_id'])?->name ?? 'Approval',
            'supervisor' => 'Atasan Pengaju',
            default => 'Approval',
        };
    }
}
