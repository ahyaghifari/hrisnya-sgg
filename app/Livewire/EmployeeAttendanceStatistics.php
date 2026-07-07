<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Exports\Attendance\AttendanceHistoryExport;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeAttendanceStatistics extends Component
{
    public User $record;

    public ?string $date_from = null;
    public ?string $date_to = null;
    public string $status = 'approved';

    public function mount(?User $record = null): void
    {
        // Route /data-diri tidak punya segment {record} — otomatis pakai user yang login.
        $record ??= Auth::user();

        abort_unless($this->canManage($record), 403);

        $this->record = $record->load([
            'company',
            'roles',
            'profile.religion',
            'profile.bank',
            'profile.orgStructure.parent.parent',
            'profile.jobPosition',
            'profile.pangkat',
            'profile.golongan',
            'profile.kelas',
            'profile.employeeType',
        ]);
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to = now()->endOfMonth()->toDateString();
    }

    protected function canManage(User $record): bool
    {
        $user = Auth::user();

        // Karyawan boleh lihat data dirinya sendiri lewat halaman ini juga (tombol "Data Diri"
        // di Dashboard) — bukan cuma atasan yang lihat bawahannya.
        if ($record->id === $user->id) {
            return true;
        }

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'status' => $this->status !== 'all' ? $this->status : null,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ];
    }

    public function getRequestsProperty()
    {
        return app(AttendaceReqService::class)
            ->getListAttendanceRequestsByUserId($this->record->id, $this->filters());
    }

    /**
     * @return array{leave: int, permission: int, overtime: int}
     */
    public function getStatsProperty(): array
    {
        $requests = $this->requests;

        return [
            'leave' => $requests->where('type', 'leave')->count(),
            'permission' => $requests->where('type', 'permission')->count(),
            'overtime' => $requests->where('type', 'overtime')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getPresenceStatsProperty(): array
    {
        $counts = AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $case) => [$case->value => $counts[$case->value] ?? 0])
            ->toArray();
    }

    public function getTodayPresenceProperty(): ?AttendanceRecords
    {
        return AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->where('date', now()->toDateString())
            ->first();
    }

    public function getPresenceRecordsProperty()
    {
        return AttendanceRecords::query()
            ->where('user_id', $this->record->id)
            ->whereBetween('date', [$this->date_from, $this->date_to])
            ->orderByDesc('date')
            ->get();
    }

    private function keterangan(AttendanceRequests $record): string
    {
        return match ($record->type) {
            'leave' => $record->leaveDetails->first()?->leave_type ?? 'Cuti',
            'permission' => $record->permissionDetails->first()?->permission_type ?? 'Izin',
            'overtime' => $record->overtimeDetails->first()?->overtime_type ?? 'Lembur',
            default => '-',
        };
    }

    public function export()
    {
        $rows = $this->requests->map(fn (AttendanceRequests $r) => [
            'date' => $r->created_at->translatedFormat('d M Y H:i'),
            'type' => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$r->type] ?? $r->type,
            'keterangan' => $this->keterangan($r),
            'status' => AttendanceReqStatus::from($r->status)->getLabel(),
        ])->toArray();

        $periodLabel = "{$this->date_from} s/d {$this->date_to}";

        return Excel::download(
            new AttendanceHistoryExport($rows, $this->record->name, $periodLabel),
            'riwayat-absensi-' . str($this->record->name)->slug() . '.xlsx'
        );
    }

    public function render()
    {
        $title = $this->record->id === Auth::id()
            ? 'Data Diri'
            : "Karyawanku - {$this->record->name}";

        return view('livewire.employee-attendance-statistics')
            ->layout('components.layout', ['title' => $title]);
    }
}
