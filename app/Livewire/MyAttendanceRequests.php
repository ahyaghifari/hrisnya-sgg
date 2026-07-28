<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat pengajuan absensi milik sendiri — REDESIGN dari reuse Filament Table (yang sebelumnya
 * cuma dipakai desktop, ditambah query manual terpisah buat mobile, dua sumber data beda buat
 * tampilan yang sama) jadi custom table polos, pola sama seperti UserTransitionsPage/SchedulesPage:
 * satu computed property dipakai bareng oleh mobile (card) & desktop (tabel), filter Jenis +
 * Status lewat pill button (wire:click, bukan <select>).
 */
#[Layout('components.layout', ['title' => 'Pengajuan Absensi'])]
class MyAttendanceRequests extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    /** @return array<string, string> */
    public function getTypeOptionsProperty(): array
    {
        return ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'];
    }

    /** @return AttendanceReqStatus[] */
    public function getStatusOptionsProperty(): array
    {
        return AttendanceReqStatus::cases();
    }

    protected function baseQuery()
    {
        return AttendanceRequests::query()
            ->where('user_id', Auth::id())
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['leaveDetails', 'permissionDetails', 'overtimeDetails']);
    }

    public function getRequestsProperty()
    {
        return $this->baseQuery()->orderByDesc('created_at')->paginate(10);
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

    public function typeLabel(string $type): string
    {
        return $this->typeOptions[$type] ?? $type;
    }

    public function typeIcon(string $type): string
    {
        return match ($type) {
            'leave' => 'fa-umbrella-beach',
            'permission' => 'fa-clock',
            'overtime' => 'fa-briefcase-clock',
            default => 'fa-file-lines',
        };
    }

    public function typeTile(string $type): string
    {
        return match ($type) {
            'leave' => 'neutral',
            'permission' => 'warn',
            'overtime' => 'done',
            default => 'neutral',
        };
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'approved' => 'badge-approved',
            'in_review', 'submitted' => 'badge-pending',
            'rejected', 'failed' => 'badge-rejected',
            default => 'badge-cancelled',
        };
    }

    public function render()
    {
        return view('livewire.my-attendance-requests');
    }
}
