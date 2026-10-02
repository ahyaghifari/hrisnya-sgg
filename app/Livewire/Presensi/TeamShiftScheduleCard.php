<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesDayScheduleSummary;
use App\Livewire\Concerns\ResolvesShiftTeamMembers;
use App\Models\Master\Attendance\Holidays;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Jadwal Shift Tim" di Dashboard - buat atasan (mis. Kepala Ruangan) sekilas lihat
 * jadwal STAFF-NYA per hari (beda dari widget "Jadwal Shift Saya" yang isinya jadwal sendiri)
 * - instruksi user 2026-08-18. Scope staff sama dengan ExtraShiftRequestModal
 * (OrgHierarchyService::subordinates(), rekursif lewat struktur jabatan), tanpa diri sendiri
 * (widget ini murni lihat tim).
 *
 * SEMUA staff ditampilkan, TIDAK difilter berdasarkan hasShiftSchedule()/tipe karyawan
 * (instruksi user 2026-08-18: "seluruh karyawan dibawah kepala ruangan itu masuk, tidak peduli
 * tipe karyawan apa" - awalnya sempat difilter ke yang beneran berjadwal shift, tapi itu bikin
 * karyawan baru/orientasi yang belum sempat di-assign jadwal jadi hilang dari daftar padahal
 * atasannya justru perlu tahu itu). Yang belum punya jadwal apa pun tampil dengan status
 * "Belum ada jadwal" (lihat resolveDaySchedule() state 'none'), bukan disembunyikan.
 *
 * Read-only. Kemunculannya digerbangi permission `WidgetPermissionEnum::JADWAL_SHIFT_TIM`
 * (2026-08-19, sebelumnya otomatis tampil untuk siapa pun yang punya staff tanpa permission
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

    /** Staff struktur jabatan + karyawan lain yang pakai Schedule sama (lihat
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
     * timnya tetap perlu dilihat detail rosternya.
     *
     * Prioritas 1 (2026-08-19): kalau atasan ini terdaftar sebagai `head_user_id` di 1 Schedule
     * tertentu, link WAJIB ke situ - tanpa ini, atasan yang staff-nya kepencar di lebih dari 1
     * Schedule/unit bisa ke-link ke Schedule unit yang salah (asal Schedule pertama yang ketemu
     * dari roster siapapun, urutan tidak dijamin). Fallback ke logic lama (Schedule pertama dari
     * roster anggota tim) kalau atasan ini belum di-set sebagai kepala ruangan manapun - null
     * kalau dua-duanya tidak ketemu, link "Detail Jadwal" ikut tersembunyi di kasus itu. */
    public function getTeamScheduleIdProperty(): ?string
    {
        $user = Auth::user();
        $headScheduleId = $user ? Schedule::where('head_user_id', $user->id)->value('id') : null;

        if ($headScheduleId) {
            return $headScheduleId;
        }

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

    /** ID anggota tim yang punya pengajuan tukar shift (in_review/approved) menyentuh tanggal yang
     * sedang ditampilkan - dipakai blade buat naruh ikon "tukar" emas di baris orang itu.
     *
     * @return array<int, string>
     */
    public function getSwapMemberIdsProperty(): array
    {
        $memberIds = $this->teamMembers->pluck('id')->all();

        if (! $memberIds) {
            return [];
        }

        return ShiftSwapRequests::whereIn('status', ['in_review', 'approved'])
            ->where(function ($q) use ($memberIds) {
                $q->where(fn ($qq) => $qq->where(fn ($u) => $u->whereIn('requester_id', $memberIds)->orWhereIn('target_id', $memberIds))
                    ->where(fn ($d) => $d->whereDate('req_date', $this->date)->orWhereDate('target_date', $this->date)));
            })
            ->get(['requester_id', 'req_date', 'target_id', 'target_date'])
            // ponytail: approved beda tanggal -> shift pindah silang (requester pegang target_date, target
            // pegang req_date), jadi kedua pihak ditandai di kedua tanggal, bukan cuma tanggal asal.
            ->flatMap(fn ($s) => (
                $s->req_date->toDateString() === $this->date || $s->target_date?->toDateString() === $this->date
            ) ? [$s->requester_id, $s->target_id] : [])
            ->filter()
            ->intersect($memberIds)
            ->unique()
            ->values()
            ->all();
    }

    /** user_id anggota tim yang punya Extra/Double Shift APPROVED di tanggal yang sedang
     * ditampilkan - dipakai blade buat naruh badge "Extra Shift"/"Double Shift" di sebelah chip
     * jadwal orang itu (instruksi user 2026-10-17), sama pola dengan $swapMemberIds.
     *
     * @return array<string, bool> user_id => is_double_shift
     */
    public function getExtraShiftMemberIdsProperty(): array
    {
        $memberIds = $this->teamMembers->pluck('id')->all();

        if (! $memberIds) {
            return [];
        }

        return ExtraShiftAssignment::whereIn('user_id', $memberIds)
            ->whereDate('date', $this->date)
            ->where('status', 'approved')
            ->get(['user_id', 'category'])
            ->mapWithKeys(fn ($e) => [$e->user_id => $e->category === ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT])
            ->all();
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
