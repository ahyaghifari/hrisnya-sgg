<?php

namespace App\Livewire\Dashboard;

use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use Livewire\Component;

/**
 * Widget "Attendance Hari Ini" di Dashboard (Beranda) - ringkasan agregat 1 company, BUKAN
 * panggil ResolvesTodayAttendanceStatus::todayAttendanceStatus() per user (itu me-resolve
 * jadwal tiap user lewat ScheduleResolver, terlalu berat dipanggil ratusan kali cuma buat 1
 * angka ringkasan). Simplifikasi: tidak membedakan hari libur/off-schedule seperti versi
 * per-user itu, cukup Hadir/Cuti/Izin/Belum Presensi.
 *
 * ponytail: agregat kasar, upgrade ke breakdown penuh (Sakit/Dinas/Alpa) kalau kategori itu
 * benar-benar dimodelkan nanti.
 *
 * Tiap kategori bisa diklik buat lihat daftar orangnya (modal) - instruksi user 2026-09-18,
 * pola sama openModal()/closeModal() yang sudah dipakai KepegawaianDashboardPage.
 *
 * Namespace App\Livewire\Dashboard - widget ini murni milik halaman Beranda (instruksi user
 * 2026-09-18), bukan bagian modul Presensi/Kepegawaian.
 *
 * Gate: sama seperti widget lain di dashboard ini, lihat
 * Dashboard::getCanKepegawaianWidgetsProperty().
 */
class TodayAttendanceSummaryWidget extends Component
{
    public string $companyId;

    /** 'hadir'|'cuti'|'izin'|'belum'|null - modal detail yang lagi kebuka. */
    public ?string $activeModal = null;

    public function openModal(string $type): void
    {
        $this->activeModal = $type;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    private function todayStr(): string
    {
        return now()->toDateString();
    }

    private function companyUserIds()
    {
        return User::query()->where('company_id', $this->companyId)->pluck('id');
    }

    public function getSummaryProperty(): array
    {
        $todayStr = $this->todayStr();
        $userIds = $this->companyUserIds();

        $hadir = AttendanceRecords::whereIn('user_id', $userIds)
            ->where('date', $todayStr)
            ->whereNotNull('check_in_time')
            ->count();

        $cuti = AttendanceRequests::whereIn('user_id', $userIds)
            ->where('type', 'leave')
            ->where('status', 'approved')
            ->whereHas('leaveDetails', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('end_date')->where('start_date', '<=', $todayStr)->where('end_date', '>=', $todayStr))
                ->orWhere(fn ($q2) => $q2->whereNull('end_date')->where('start_date', $todayStr)))
            ->count();

        $izin = AttendanceRequests::whereIn('user_id', $userIds)
            ->where('type', 'permission')
            ->where('status', 'approved')
            ->whereHas('permissionDetails', fn ($q) => $q->where('date', $todayStr))
            ->count();

        $total = $userIds->count();

        return [
            'hadir' => $hadir,
            'cuti' => $cuti,
            'izin' => $izin,
            'belum' => max($total - $hadir - $cuti - $izin, 0),
            'total' => $total,
        ];
    }

    /** Buat modal "Hadir" - nama + jam masuk, ditandai kalau terlambat (late_minutes > 0). */
    public function getHadirDetailProperty()
    {
        return AttendanceRecords::whereIn('user_id', $this->companyUserIds())
            ->where('date', $this->todayStr())
            ->whereNotNull('check_in_time')
            ->whereHas('user')
            ->with('user')
            ->orderByDesc('late_minutes')
            ->get();
    }

    /** Buat modal "Cuti" - nama + jenis cuti yang aktif hari ini. */
    public function getCutiDetailProperty()
    {
        $todayStr = $this->todayStr();

        return AttendanceRequests::whereIn('user_id', $this->companyUserIds())
            ->where('type', 'leave')
            ->where('status', 'approved')
            ->whereHas('leaveDetails', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('end_date')->where('start_date', '<=', $todayStr)->where('end_date', '>=', $todayStr))
                ->orWhere(fn ($q2) => $q2->whereNull('end_date')->where('start_date', $todayStr)))
            ->whereHas('user')
            ->with(['user', 'leaveDetails'])
            ->get();
    }

    /** Buat modal "Izin" - nama + jenis izin + jam yang diajukan hari ini. */
    public function getIzinDetailProperty()
    {
        $todayStr = $this->todayStr();

        return AttendanceRequests::whereIn('user_id', $this->companyUserIds())
            ->where('type', 'permission')
            ->where('status', 'approved')
            ->whereHas('permissionDetails', fn ($q) => $q->where('date', $todayStr))
            ->whereHas('user')
            ->with(['user', 'permissionDetails' => fn ($q) => $q->where('date', $todayStr)])
            ->get();
    }

    /** Buat modal "Belum Presensi" - karyawan yang tidak masuk kategori Hadir/Cuti/Izin. */
    public function getBelumDetailProperty()
    {
        $hadirIds = $this->hadirDetail->pluck('user_id');
        $cutiIds = $this->cutiDetail->pluck('user_id');
        $izinIds = $this->izinDetail->pluck('user_id');

        return User::query()
            ->where('company_id', $this->companyId)
            ->whereNotIn('id', $hadirIds->merge($cutiIds)->merge($izinIds))
            ->with('profile.jobPosition')
            ->orderBy('name')
            ->get();
    }

    /** Config Chart.js utuh (type/data/options) - dikirim ke blade sebagai JSON, diparse &
     * di-instantiate oleh initCharts() di resources/js/app.js. */
    public function getChartConfigProperty(): array
    {
        $today = $this->summary;

        return [
            'type' => 'bar',
            'data' => [
                'labels' => ['Hadir', 'Cuti', 'Izin', 'Belum Presensi'],
                'datasets' => [[
                    'data' => [$today['hadir'], $today['cuti'], $today['izin'], $today['belum']],
                    'backgroundColor' => ['#047C54', '#1a56c4', '#a16207', '#94a3b8'],
                    'borderRadius' => 6,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => [
                    'x' => ['ticks' => ['font' => ['size' => 11]]],
                    'y' => ['beginAtZero' => true, 'ticks' => ['font' => ['size' => 11], 'precision' => 0]],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.today-attendance-summary-widget');
    }
}
