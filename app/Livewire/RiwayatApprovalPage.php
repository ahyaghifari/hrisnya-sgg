<?php

namespace App\Livewire;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Attendance\AttendanceRequests;
use App\Livewire\Presensi\Concerns\FormatsShiftSwapRequest;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\Shift\ShiftSwapRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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
    use FormatsShiftSwapRequest { keterangan as keteranganSwap; }

    private const TYPE_SWAP = 'swap_shift';

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public string $search = '';

    #[Url]
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
     * @return LengthAwarePaginator<int, array{history: ApprovalHistories, request: AttendanceRequests|ShiftSwapRequests}>
     */
    public function getRowsProperty(): LengthAwarePaginator
    {
        // typeFilter 'swap_shift' = modul Tukar Shift (tabel terpisah dari attendance_requests);
        // leave/permission/overtime = attendance_request; kosong = dua-duanya.
        $modules = match (true) {
            $this->typeFilter === self::TYPE_SWAP => [ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value],
            $this->typeFilter !== '' => [ApprovalModuleEnum::ATTENDANCE_REQUEST->value],
            default => [ApprovalModuleEnum::ATTENDANCE_REQUEST->value, ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value],
        };

        $histories = ApprovalHistories::query()
            ->where('actor_id', Auth::id())
            ->whereIn('requestable_type', $modules)
            ->whereIn('action', ['approved', 'rejected'])
            ->orderByDesc('created_at')
            ->get();

        $idsOf = fn (ApprovalModuleEnum $m) => $histories->where('requestable_type', $m->value)->pluck('requestable_id')->unique()->values();

        $requests = AttendanceRequests::query()
            ->whereIn('id', $idsOf(ApprovalModuleEnum::ATTENDANCE_REQUEST))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->with(['user', 'leaveDetails', 'permissionDetails', 'overtimeDetails'])
            ->get()
            ->keyBy('id');

        $swaps = ShiftSwapRequests::query()
            ->whereIn('id', $idsOf(ApprovalModuleEnum::SWAP_SHIFT_REQUEST))
            ->with(['requester', 'target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->get()
            ->keyBy('id');

        $search = trim($this->search);

        $filtered = $histories
            ->map(fn (ApprovalHistories $history) => [
                'history' => $history,
                'request' => $history->requestable_type === ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value
                    ? $swaps->get($history->requestable_id)
                    : $requests->get($history->requestable_id),
            ])
            ->filter(fn (array $row) => $row['request'])
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains(mb_strtolower($this->pemohon($row['request'])), mb_strtolower($search))
            ))
            ->values();

        $page = $this->getPage();

        // request()->url()/query() TIDAK dipakai di sini - selama render lewat AJAX Livewire
        // (mis. filter search/typeFilter berubah), request() menunjuk endpoint internal
        // /livewire/update (bukan URL halaman ini), bikin link paginasi rusak & filter hilang.
        // Livewire::originalPath() balikin path halaman yang benar di kedua kondisi (full-load
        // maupun AJAX) - dipakai TANPA originalUrl() (sudah absolut lengkap scheme+host) supaya
        // formatnya konsisten dengan Paginator::currentPathResolver() bawaan Livewire (path
        // relatif tanpa leading slash), yang jadi asumsi normalisasi di
        // livewire/partials/pagination.blade.php. Query dibangun manual dari property #[Url]
        // milik komponen ini sendiri - itu sumber kebenaran yang selalu akurat, tidak bergantung
        // ambient request state.
        return new LengthAwarePaginator(
            $filtered->forPage($page, $this->perPage)->values(),
            $filtered->count(),
            $this->perPage,
            $page,
            [
                'path' => \Livewire\Livewire::originalPath(),
                'query' => [
                    'search' => $this->search,
                    'typeFilter' => $this->typeFilter,
                ],
            ]
        );
    }

    public function pemohon(AttendanceRequests|ShiftSwapRequests $record): string
    {
        return ($record instanceof ShiftSwapRequests ? $record->requester?->name : $record->user?->name) ?? '-';
    }

    public function jenis(AttendanceRequests|ShiftSwapRequests $record): string
    {
        if ($record instanceof ShiftSwapRequests) {
            return 'Tukar Shift';
        }

        return ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$record->type] ?? $record->type;
    }

    public function detailUrl(AttendanceRequests|ShiftSwapRequests $record): string
    {
        return $record instanceof ShiftSwapRequests
            ? route('tukar-shift.show', ['record' => $record->id])
            : route('my-attendance-requests.show', ['record' => $record->id]);
    }

    public function keteranganRow(AttendanceRequests|ShiftSwapRequests $record): string
    {
        return $record instanceof ShiftSwapRequests ? $this->keteranganSwap($record) : $this->keterangan($record);
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
