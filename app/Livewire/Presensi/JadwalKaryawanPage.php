<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
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
 * sama seperti SchedulesPage) buat cek jadwal siapa saja di perusahaannya, BUKAN cuma staff
 * langsungnya (beda dari MyStaff). Reuse trait ResolvesDayScheduleSummary supaya logic resolve
 * jadwal tidak dobel dengan JadwalSayaPage/Dashboard.
 */
#[Layout('components.layout', ['title' => 'Jadwal Karyawan'])]
class JadwalKaryawanPage extends Component
{
    use ResolvesDayScheduleSummary;

    public ?string $companyId = null;

    /** 'kalender' = kalender 1 karyawan (perilaku lama), 'belum-jadwal' = daftar karyawan yang
     * SAMA SEKALI tidak punya assignment jadwal (Fixed/Flexible/Shift, personal maupun lewat
     * jabatan) - lihat getUnassignedEmployeesProperty(). */
    #[Url]
    public string $view = 'kalender';

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
        return User::query()
            ->with('profile.jobPosition')
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId));
    }

    public function getEmployeesProperty()
    {
        return $this->employeesQuery()->orderBy('name')->get();
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['kalender', 'belum-jadwal'], true) ? $view : 'kalender';
    }

    /** Dipanggil dari tombol "Lihat Kalender" di daftar Belum Ada Jadwal - langsung pindah ke
     * tab Kalender dengan karyawan itu terpilih, bukan 2 aksi terpisah (pilih tab, baru pilih
     * karyawan manual dari dropdown). */
    public function viewCalendarFor(string $userId): void
    {
        $this->view = 'kalender';
        $this->userId = $userId;
        $this->month = now()->format('Y-m');
    }

    /** Karyawan yang SAMA SEKALI tidak punya assignment jadwal aktif hari ini - Fixed, Flexible,
     * ATAU Shift (personal maupun lewat jabatan, lihat ScheduleAssignment::resolveForUser()).
     * Sengaja BUKAN pakai ScheduleResolver::resolveForUserDate() penuh - itu buat karyawan Shift
     * yang SUDAH jadi anggota tapi rosternya belum diisi HARI INI (beda kasus, bukan "belum
     * punya jadwal" sama sekali). Ini murni cek keanggotaan, bukan status hari tertentu. */
    public function getUnassignedEmployeesProperty()
    {
        $today = now()->toDateString();

        return $this->employees
            ->reject(fn (User $employee) => (bool) ScheduleAssignment::resolveForUser($employee, $today))
            ->values();
    }

    public function getSelectedUserProperty(): ?User
    {
        if (! $this->userId) {
            return null;
        }

        $user = $this->employees->firstWhere('id', $this->userId);

        return $user?->loadMissing(['company', 'profile.jobPosition.orgStructure.parent.parent', 'profile.jobPosition.orgStructure.parent.parent']);
    }

    /** Jadwal yang berlaku hari ini untuk karyawan terpilih (buat kartu di atas kalender): assignment
     * Fixed/Flexible/Shift (personal maupun lewat jabatan); fallback ke jadwal dari roster shift
     * terakhirnya kalau karyawan shift tidak punya assignment aktif. Null = belum punya jadwal. */
    public function getSelectedScheduleProperty(): ?Schedule
    {
        $user = $this->selectedUser;

        if (! $user) {
            return null;
        }

        $assignment = ScheduleAssignment::resolveForUser($user, now()->toDateString());
        $schedule = $assignment?->schedule;

        if ($schedule) {
            return $schedule;
        }

        // Scope company dibuka - halaman ini boleh melihat karyawan perusahaan lain (izin filter company).
        return ScheduleShiftAssignment::withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->whereNotNull('shift_type_id')
            ->with('shiftType.schedule')
            ->latest('date')
            ->first()?->shiftType?->schedule;
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
