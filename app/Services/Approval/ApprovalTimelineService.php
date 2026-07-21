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
            'actor' => $request->user->name,
            'date' => $request->created_at,
            'state' => 'done',
        ]];

        foreach ($steps as $step) {
            $history = $historyByStep->get($step['order']);

            if ($history) {
                $timeline[] = [
                    'label' => $this->stepLabel($step),
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

        return $timeline;
    }

    private function stepLabel(array $step): string
    {
        return match ($step['approver_type'] ?? null) {
            'user' => User::find($step['approver_ref_id'])?->name ?? 'Approval',
            'role' => Role::find($step['approver_ref_id'])?->name ?? 'Approval',
            'job_position' => JobPositions::find($step['approver_ref_id'])?->name ?? 'Approval',
            default => 'Approval',
        };
    }
}
