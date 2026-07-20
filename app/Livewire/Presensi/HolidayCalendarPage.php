<?php

namespace App\Livewire\Presensi;

use App\Models\Master\Attendance\Holidays;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versi read-only dari halaman Hari Libur (HolidaysPage) untuk karyawan biasa — bukan reuse
 * HolidaysTable/HolidayForm Filament (itu untuk CRUD admin), query langsung ke model Holidays.
 * Tidak digerbangi permission Shield (siapa saja yang login boleh lihat, pola sama halaman
 * self-service lain) — belum ada link masuk di nav manapun, diakses lewat URL langsung dulu.
 * Lihat planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §9.3.
 */
#[Layout('components.layout', ['title' => 'Kalender Hari Libur'])]
class HolidayCalendarPage extends Component
{
    public function getHolidaysProperty()
    {
        return Holidays::query()
            ->whereBetween('date', [now()->startOfYear(), now()->endOfYear()])
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($holiday) => $holiday->date->translatedFormat('F Y'));
    }

    public function render()
    {
        return view('livewire.presensi.holiday-calendar-page');
    }
}
