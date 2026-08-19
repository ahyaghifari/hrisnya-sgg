<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Livewire\Concerns\ResolvesShiftTeamMembers;
use App\Models\Master\Attendance\Holidays;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Jadwal Shift Tim" di Dashboard - buat atasan (mis. Kepala Ruangan) sekilas lihat
 * jadwal BAWAHANNYA per hari (beda dari widget "Jadwal Shift Saya" yang isinya jadwal sendiri)
 * - instruksi user 2026-08-18. Scope bawahan sama dengan ExtraShiftRequestModal
 * (OrgHierarchyService::subordinates(), rekursif lewat struktur jabatan), tanpa diri sendiri
 * (widget ini murni lihat tim).
 *
 * SEMUA bawahan ditampilkan, TIDAK difilter berdasarkan hasShiftSchedule()/tipe karyawan
 * (instruksi user 2026-08-18: "seluruh karyawan dibawah kepala ruangan itu masuk, tidak peduli
 * tipe karyawan apa" - awalnya sempat difilter ke yang beneran berjadwal shift, tapi itu bikin
 * karyawan baru/orientasi yang belum sempat di-assign jadwal jadi hilang dari daftar padahal
 * atasannya justru perlu tahu itu). Yang belum punya jadwal apa pun tampil dengan status
 * "Belum ada jadwal" (lihat resolveDaySchedule() state 'none'), bukan disembunyikan.
 *
 * Read-only. Kemunculannya digerbangi permission `WidgetPermissionEnum::JADWAL_SHIFT_TIM`
 * (2026-08-19, sebelumnya otomatis tampil untuk siapa pun yang punya bawahan tanpa permission
 * terpisah - diganti eksplisit atas instruksi user, biar lebih gampang diatur siapa yang lihat)
 * - gate-nya ada di `Dashboard::getCanJadwalShiftTimProperty()` + `@if` yang membungkus tag
 * `<livewire:presensi.team-shift-schedule-card>` di home-cards.blade.php, BUKAN di class ini
 * sendiri (widget ini tidak punya route/entry point lain yang bisa diakses langsung selain
 * lewat include itu, jadi tidak perlu abort_unless ulang di sini).
 */
class TeamShiftScheduleCard extends Component
{
    use ResolvesDayScheduleSummary;
    use ResolvesShiftTeamMembers;

    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function goToToday(): void
    {
        $this->date = now()->toDateString();
    }

    public function getDateLabelProperty(): string
    {
        return Carbon::parse($this->date)->translatedFormat('l, d M Y');
    }

    /** Bawahan struktur jabatan + karyawan lain yang pakai Schedule sama (lihat
     * ResolvesShiftTeamMembers), tanpa diri sendiri, TIDAK difilter hasShiftSchedule() lagi -
     * lihat catatan class-level. */
    public function getTeamMembersProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        return $this->subordinatesWithSharedSchedule($user);
    }

    /** Schedule (shift) yang dipakai tim ini, buat link "Detail Jadwal" ke ScheduleDetailPage -
     * sengaja diambil dari jadwal TIM (bukan jadwal pribadi atasan lewat
     * Dashboard::getMyScheduleIdProperty()), karena atasan sendiri bisa saja tidak punya roster
     * shift apa pun (mis. Kepala Ruangan yang jadwal pribadinya sudah tidak ada), padahal
     * timnya tetap perlu dilihat detail rosternya. Null kalau tidak ada satu pun anggota tim
     * yang berjadwal shift - link "Detail Jadwal" ikut tersembunyi di kasus itu. */
    public function getTeamScheduleIdProperty(): ?string
    {
        $memberIds = $this->teamMembers->pluck('id');

        if ($memberIds->isEmpty()) {
            return null;
        }

        return ScheduleShiftAssignment::whereIn('user_id', $memberIds)
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->get()
            ->pluck('shiftType.schedule_id')
            ->filter()
            ->first();
    }

    /** Tombol "Extra Shift" instan - dipindah dari widget "Jadwal Shift Saya" ke sini
     * (2026-08-18): sebelumnya nempel di widget jadwal PRIBADI atasan, jadi ikut hilang kalau
     * atasan sendiri tidak punya jadwal shift (mis. Kepala Ruangan yang jadwal pribadinya sudah
     * tidak ada, tapi timnya tetap ada) - padahal aksinya soal tim, bukan jadwal sendiri, jadi
     * lebih pas nempel di widget tim ini yang independen dari hasShiftSchedule() atasan. */
    public function getCanCreateExtraShiftProperty(): bool
    {
        return (bool) Auth::user()?->can('create', ExtraShiftAssignment::class);
    }

    /** @return array<int, array{user: User, schedule: array}> */
    public function getTeamScheduleProperty(): array
    {
        $resolver = app(ScheduleResolver::class);
        $holidayName = Holidays::whereDate('date', $this->date)->value('name');

        return $this->teamMembers
            ->map(fn (User $u) => [
                'user' => $u,
                'schedule' => $this->resolveDaySchedule($resolver, $u, $this->date, $holidayName),
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.presensi.team-shift-schedule-card');
    }
}
