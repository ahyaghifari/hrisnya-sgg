<?php

namespace App\Livewire;

use App\Enum\WidgetPermissionEnum;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Dashboard Anda" (route `dashboard-anda`) - dipisah dari Beranda (App\Livewire\Dashboard,
 * instruksi user 2026-09-18) supaya Beranda tetap ringan buat semua karyawan; halaman ini
 * isinya widget monitoring per role/jabatan (sekarang: HC Manager - Summary, Status
 * Kepegawaian, Attendance Hari Ini, Tren Kehadiran, Coaching, Manpower Planning), nanti bisa
 * nambah widget buat role lain tanpa bikin halaman baru lagi (model Level x Domain, lihat
 * planning/2026-07-03-arsitektur-dashboard-per-role.md & planning/dashboard/dashboard-hc-manager.html).
 *
 * Gate halaman: User::canAccessDashboardAnda() (minimal 1 dari permission
 * WidgetPermissionEnum::DASHBOARD_HC_*) - dicek middleware EnsurePortalPageAccess lewat
 * config/portal-pages.php DAN di mount() ini (defense in depth, pola sama halaman lain).
 * Widget individual tetap punya gate sendiri-sendiri di dalam halaman (lihat getCanDashboardHc*
 * Property() di bawah), supaya admin bisa matikan widget tertentu tanpa menutup akses halaman.
 */
#[Layout('components.layout', ['title' => 'Dashboard'])]
class DashboardAndaPage extends Component
{
    /** Filter company - cuma dipakai user yang pegang LIHAT_PERUSAHAAN_LAIN, selain itu selalu
     * company sendiri. Dipassing sebagai prop biasa ke tiap widget child (lihat blade-nya) -
     * wire:key komponennya ikut disisipi companyId, jadi ganti company = component di-remount
     * total (bukan #[Reactive] partial update), otomatis mereset state lokal widget (mis.
     * filter departemen di ManpowerGapWidget) tanpa perlu hook updatedCompanyId() manual di
     * tiap widget. */
    public ?string $companyId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->canAccessDashboardAnda(), 403);

        $this->companyId = Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()?->canFilterByCompany() || ! $this->companyId) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCanFilterByCompanyProperty(): bool
    {
        return (bool) Auth::user()?->canFilterByCompany();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    /** Widget-widget HC Manager - permission SENDIRI-SENDIRI per widget (pola sama
     * LIHAT_DASHBOARD_SIKARTAP_STATISTIK/_ANTRIAN_TAHAP di DashboardSikartapPage, bukan 1 gate
     * gabungan), supaya admin bisa atur tiap widget terpisah per role lewat form Role
     * (Filament). */
    public function getCanDashboardHcSummaryProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::DASHBOARD_HC_SUMMARY->value);
    }

    public function getCanDashboardHcEmployeeTypeProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::DASHBOARD_HC_EMPLOYEE_TYPE->value);
    }

    public function getCanDashboardHcAttendanceTodayProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::DASHBOARD_HC_ATTENDANCE_TODAY->value);
    }

    public function getCanDashboardHcAttendanceTrendProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::DASHBOARD_HC_ATTENDANCE_TREND->value);
    }

    public function getCanDashboardHcCoachingProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::DASHBOARD_HC_COACHING->value);
    }

    public function getCanDashboardHcManpowerProperty(): bool
    {
        return (bool) Auth::user()?->can(WidgetPermissionEnum::DASHBOARD_HC_MANPOWER->value);
    }

    public function render()
    {
        return view('livewire.dashboard-anda-page');
    }
}
