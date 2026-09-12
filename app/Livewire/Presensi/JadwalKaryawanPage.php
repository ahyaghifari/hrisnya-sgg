<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kalender bulanan jadwal (Fixed/Flexible/Shift) milik SATU karyawan pilihan - padanan admin
 * dari JadwalSayaPage, dipakai orang yang punya hak kelola jadwal (permission 'ViewAny:Schedule',
 * sama seperti SchedulesPage) buat cek jadwal siapa saja di perusahaannya, BUKAN cuma bawahan
 * langsungnya (beda dari MyStaff). Reuse trait ResolvesDayScheduleSummary supaya logic resolve
 * jadwal tidak dobel dengan JadwalSayaPage/Dashboard.
 */
#[Layout('components.layout', ['title' => 'Jadwal Karyawan'])]
class JadwalKaryawanPage extends Component
{
    use ResolvesDayScheduleSummary;

    public ?string $companyId = null;

    #[Url]
    public ?string $userId = null;

    #[Url]
    public string $month;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:Schedule'), 403);

        $this->month = $this->month ?? now()->format('Y-m');

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        // userId dari URL bisa dioprek user - validasi tetap harus lewat scope company yang
        // sama, supaya tidak bisa intip karyawan perusahaan lain lewat query string.
        if ($this->userId && ! $this->employeesQuery()->where('id', $this->userId)->exists()) {
            $this->userId = null;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->userId = null;
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    protected function employeesQuery()
    {
        return User::query()->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId));
    }

    public function getEmployeesProperty()
    {
        return $this->employeesQuery()->orderBy('name')->get();
    }

    public function getSelectedUserProperty(): ?User
    {
        if (! $this->userId) {
            return null;
        }

        return $this->employees->firstWhere('id', $this->userId);
    }

    public function updatedUserId(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonthNoOverflow()->format('Y-m');
    }

    public function goToToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function getMonthLabelProperty(): string
    {
        return Carbon::createFromFormat('Y-m', $this->month)->translatedFormat('F Y');
    }

    /**
     * @return array<int, array<int, array{date: Carbon, is_current_month: bool, is_today: bool, schedule: ?array}>>
     */
    public function getWeeksProperty(): array
    {
        $user = $this->selectedUser;

        if (! $user) {
            return [];
        }

        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $holidays = Holidays::query()
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->pluck('name', 'date')
            ->mapWithKeys(fn ($name, $date) => [Carbon::parse($date)->toDateString() => $name]);

        $resolver = app(ScheduleResolver::class);
        $today = now()->toDateString();

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dateStr = $cursor->toDateString();
                $isCurrentMonth = $cursor->month === $start->month;

                $week[] = [
                    'date' => $cursor->copy(),
                    'is_current_month' => $isCurrentMonth,
                    'is_today' => $dateStr === $today,
                    'schedule' => $isCurrentMonth
                        ? $this->resolveDaySchedule($resolver, $user, $dateStr, $holidays->get($dateStr))
                        : null,
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    public function render()
    {
        return view('livewire.presensi.jadwal-karyawan-page');
    }
}
