<?php

namespace App\Livewire;

use App\Models\Attendance\AttendanceRequests;
use App\Models\ApprovalManagement\ApprovalHistories;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat keputusan approval yang PERNAH dibuat user ini sebagai approver (approved/rejected) -
 * beda dari PendingApprovalsCard (yang masih menunggu diproses). 1 baris approval_histories
 * dengan actor_id=user ini dan action approved/rejected = 1 keputusan yang pernah dia buat, baik
 * di step tengah maupun step final chain.
 *
 * requestable_type di approval_histories cuma diskriminator string ('attendance_request'), BUKAN
 * FQCN asli - tidak ada morph map terdaftar, jadi requestable() morphTo tidak reliable dipakai.
 * Query manual ke AttendanceRequests, sama pola dengan PendingApprovalsCard.
 */
#[Layout('components.layout', ['title' => 'Riwayat Approval'])]
class RiwayatApprovalPage extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';

    protected int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array{history: ApprovalHistories, request: AttendanceRequests}>
     */
    public function getRowsProperty(): LengthAwarePaginator
    {
        $histories = ApprovalHistories::query()
            ->where('actor_id', Auth::id())
            ->where('requestable_type', 'attendance_request')
            ->whereIn('action', ['approved', 'rejected'])
            ->orderByDesc('created_at')
            ->get();

        $requests = AttendanceRequests::query()
            ->whereIn('id', $histories->pluck('requestable_id')->unique()->values())
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->with(['user', 'leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->get()
            ->keyBy('id');

        $search = trim($this->search);

        $filtered = $histories
            ->map(fn (ApprovalHistories $history) => [
                'history' => $history,
                'request' => $requests->get($history->requestable_id),
            ])
            ->filter(fn (array $row) => $row['request'])
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains(mb_strtolower($row['request']->user->name), mb_strtolower($search))
            ))
            ->values();

        $page = $this->getPage();

        return new LengthAwarePaginator(
            $filtered->forPage($page, $this->perPage)->values(),
            $filtered->count(),
            $this->perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function keterangan(AttendanceRequests $record): string
    {
        return match ($record->type) {
            'leave' => $record->leaveDetails->first()?->leave_type ?? 'Cuti',
            'permission' => $record->permissionDetails->first()?->permission_type ?? 'Izin',
            'overtime' => $record->overtimeDetails->first()?->overtime_type ?? 'Lembur',
            default => '-',
        };
    }

    public function render()
    {
        return view('livewire.riwayat-approval-page');
    }
}
