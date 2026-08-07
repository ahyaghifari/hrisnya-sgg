<?php

namespace App\Livewire\Presensi;

use App\Models\Master\Attendance\Holidays;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dibangun ulang total dari reuse HolidaysTable/HolidayForm Filament ke custom Livewire page
 * atas permintaan eksplisit user — 2 kolom: tabel (lebih lebar) di kiri, kalender bulan berjalan
 * di kanan buat visualisasi cepat tanggal mana yang libur. Cari/company lewat Livewire
 * (server-side) — SEBELUMNYA cari pakai List.js di browser, tapi List.js mengunci baris
 * tbody-nya sendiri sehingga tidak ikut ke-morph Livewire tiap company ganti (baris lama
 * nyangkut terus), jadi dicabut. HolidayResource Filament (`/admin/holidays`) tidak disentuh,
 * tetap ada buat admin panel.
 */
#[Layout('components.layout', ['title' => 'Hari Libur & Cuti Bersama'])]
class HolidaysPage extends Component
{
    public string $search = '';

    public string $calendarMonth;

    public bool $showFormModal = false;
    public ?string $editingId = null;
    public ?string $formName = null;
    public ?string $formDate = null;
    public bool $formIsCollectiveLeave = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', Holidays::class), 403);

        $this->calendarMonth = now()->format('Y-m');
    }

    public function getHolidaysProperty()
    {
        $search = trim($this->search);

        return Holidays::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('date')
            ->get();
    }

    public function getCalendarMonthLabelProperty(): string
    {
        return Carbon::parse($this->calendarMonth . '-01')->locale('id')->translatedFormat('F Y');
    }

    /**
     * Grid kalender bulan berjalan, Senin-Minggu — sel null buat padding sebelum tanggal 1 &
     * setelah tanggal terakhir, biar tabel HTML-nya selalu kelipatan 7 rapi.
     *
     * @return array<int, array{date: Carbon, holidays: \Illuminate\Support\Collection}|null>
     */
    public function getCalendarDaysProperty(): array
    {
        $start = Carbon::parse($this->calendarMonth . '-01');
        $end = $start->copy()->endOfMonth();

        $holidaysByDate = Holidays::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (Holidays $h) => $h->date->toDateString());

        $leadingBlanks = $start->dayOfWeekIso - 1; // Senin=1 -> 0 blank, Minggu=7 -> 6 blank
        $cells = array_fill(0, $leadingBlanks, null);

        for ($day = 1; $day <= $end->day; $day++) {
            $date = $start->copy()->day($day);
            $cells[] = ['date' => $date, 'holidays' => $holidaysByDate->get($date->toDateString(), collect())];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return $cells;
    }

    public function prevMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth . '-01')->addMonthNoOverflow()->format('Y-m');
    }

    // ── Tambah/Ubah ──────────────────────────────────────────────────────────

    public function getCanCreateHolidayProperty(): bool
    {
        return Auth::user()->can('create', Holidays::class);
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('create', Holidays::class), 403);

        $this->editingId = null;
        $this->formName = null;
        $this->formDate = null;
        $this->formIsCollectiveLeave = false;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(string $id): void
    {
        $holiday = Holidays::findOrFail($id);

        abort_unless(Auth::user()->can('update', $holiday), 403);

        $this->editingId = $holiday->id;
        $this->formName = $holiday->name;
        $this->formDate = $holiday->date->format('Y-m-d');
        $this->formIsCollectiveLeave = $holiday->is_collective_leave;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function saveForm(): void
    {
        $ability = $this->editingId ? 'update' : 'create';
        $subject = $this->editingId ? Holidays::findOrFail($this->editingId) : Holidays::class;
        abort_unless(Auth::user()->can($ability, $subject), 403);

        $this->validate([
            'formName' => 'required|string|max:255',
            'formDate' => 'required|date',
        ], [], [
            'formName' => 'nama',
            'formDate' => 'tanggal',
        ]);

        $data = [
            'name' => $this->formName,
            'date' => $this->formDate,
            'is_collective_leave' => $this->formIsCollectiveLeave,
        ];

        if ($this->editingId) {
            Holidays::whereKey($this->editingId)->update($data);
        } else {
            Holidays::create($data);
        }

        $this->showFormModal = false;
        $this->calendarMonth = Carbon::parse($this->formDate)->format('Y-m');

        Notification::make()
            ->title($this->editingId ? 'Hari libur berhasil diubah' : 'Hari libur baru berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function removeHoliday(string $id): void
    {
        $holiday = Holidays::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $holiday), 403);

        $holiday->delete();

        Notification::make()
            ->title('Hari libur berhasil dihapus')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.presensi.holidays-page');
    }
}
