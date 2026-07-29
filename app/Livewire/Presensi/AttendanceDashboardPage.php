<?php

namespace App\Livewire\Presensi;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Dashboard Presensi'])]
class AttendanceDashboardPage extends Component
{
    public ?string $companyId = null;

    public ?string $activeModal = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function openModal(string $modal): void
    {
        $this->activeModal = $modal;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    protected function scopedUsers()
    {
        return User::query()->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId));
    }

    protected function scopedRecordsForDate(string $date)
    {
        return AttendanceRecords::query()
            ->where('date', $date)
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)));
    }

    protected function scopedPendingRequests()
    {
        return AttendanceRequests::query()
            ->whereIn('status', [AttendanceReqStatus::SUBMITTED->value, AttendanceReqStatus::IN_REVIEW->value])
            ->when($this->companyId, fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $this->companyId)));
    }

    public function getEmployeeCountProperty(): int
    {
        return $this->scopedUsers()->count();
    }

    public function getTodayCountsProperty(): array
    {
        $counts = $this->scopedRecordsForDate(now()->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn ($case) => [$case->value => [
                'label' => $case->getLabel(),
                'count' => (int) ($counts[$case->value] ?? 0),
            ]])
            ->all();
    }

    public function getPresentTodayCountProperty(): int
    {
        return $this->todayCounts[AttendanceStatus::PRESENT->value]['count'] ?? 0;
    }

    public function getLateTodayCountProperty(): int
    {
        return $this->todayCounts[AttendanceStatus::LATE->value]['count'] ?? 0;
    }

    public function getAbsentTodayCountProperty(): int
    {
        return $this->todayCounts[AttendanceStatus::ABSENT->value]['count'] ?? 0;
    }

    public function getLateTodayListProperty()
    {
        return $this->scopedRecordsForDate(now()->toDateString())
            ->where('status', AttendanceStatus::LATE->value)
            ->with('user')
            ->orderByDesc('late_minutes')
            ->limit(20)
            ->get();
    }

    public function getAbsentTodayListProperty()
    {
        return $this->scopedRecordsForDate(now()->toDateString())
            ->where('status', AttendanceStatus::ABSENT->value)
            ->with('user')
            ->limit(20)
            ->get();
    }

    public function getPendingApprovalCountProperty(): int
    {
        return $this->scopedPendingRequests()->count();
    }

    public function getPendingApprovalsListProperty()
    {
        return $this->scopedPendingRequests()
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * Pola jam masuk hari ini, dibucket per jam 05:00-11:00 (jendela masuk kerja umum) + "Lainnya"
     * buat di luar itu - jadi signature chart dashboard ini (histogram jam datang), beda dari
     * donut Kepegawaian & ledger Keuangan, dan lebih mewakili "presensi" daripada rekap harian biasa.
     */
    public function getArrivalHistogramProperty(): array
    {
        $checkIns = $this->scopedRecordsForDate(now()->toDateString())
            ->whereNotNull('check_in_time')
            ->pluck('check_in_time');

        $buckets = collect(range(5, 10))->mapWithKeys(fn ($h) => [(string) $h => 0])->all();
        $buckets['other'] = 0;

        foreach ($checkIns as $time) {
            $hour = (int) substr($time, 0, 2);
            $key = ($hour >= 5 && $hour <= 10) ? (string) $hour : 'other';
            $buckets[$key]++;
        }

        $max = max(1, max($buckets));

        return collect($buckets)->map(fn ($count, $key) => [
            'label' => $key === 'other' ? 'Lainnya' : sprintf('%02d:00', (int) $key),
            'count' => $count,
            'percent' => (int) round($count / $max * 100),
        ])->values()->all();
    }

    /**
     * Semua status attendance_records hari ini (Hadir/Terlambat/Izin/Cuti/Sakit/Tidak Hadir/
     * Lembur) yang count-nya > 0, dipakai bar tersegmentasi "kapasitas roster hari ini" - jadi
     * dashboard ini benar2 mencakup absensi (izin/cuti/sakit), bukan cuma presensi masuk-pulang.
     */
    public function getStatusSegmentsProperty(): array
    {
        $colors = [
            AttendanceStatus::PRESENT->value => '#047C54',
            AttendanceStatus::LATE->value => '#b45309',
            AttendanceStatus::PERMIT->value => '#1d4ed8',
            AttendanceStatus::LEAVE->value => '#0f766e',
            AttendanceStatus::SICK->value => '#be185d',
            AttendanceStatus::OVERTIME->value => '#6b7280',
            AttendanceStatus::ABSENT->value => '#dc2626',
        ];

        $rows = collect($this->todayCounts)->filter(fn ($row) => $row['count'] > 0);
        $total = max(1, $rows->sum('count'));

        return $rows->map(fn ($row, $status) => $row + [
            'status' => $status,
            'color' => $colors[$status] ?? '#94a3b8',
            'percent' => (int) round($row['count'] / $total * 100),
        ])->values()->all();
    }

    public function getNextHolidayProperty(): ?Holidays
    {
        return Holidays::query()
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->first();
    }
}
