<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\ResolvesShiftTeamMembers;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Modal "Extra Shift" instan nempel di widget "Jadwal Shift Saya" (Dashboard) - buat atasan
 * berjadwal shift (mis. Kepala Ruangan) assign extra shift ke bawahannya sendiri tanpa lewat
 * approval, pola sama ExtraShiftPage (halaman admin berdiri sendiri, company-wide) tapi
 * scope karyawannya dipersempit ke tim (ResolvesShiftTeamMembers - bawahan struktur jabatan +
 * karyawan lain yang pakai Schedule sama, lihat catatan di trait itu) + diri sendiri - instruksi
 * user 2026-08-18. Selalu source=assigned/status=approved, sama seperti ExtraShiftPage::assign()
 * - jalur "requested" (pengajuan + approval) belum ada di manapun.
 *
 * Role/permission (Create:ExtraShiftAssignment, Shield) belum di-assign ke role manapun saat
 * fitur ini dibuat - user yang akan menyiapkan sendiri lewat /hak-akses. Tombol pembuka di
 * home-cards.blade.php sudah gerbang Dashboard::canCreateExtraShift, method di sini abort_unless
 * ulang (defense-in-depth kalau method dipanggil langsung).
 */
class ExtraShiftRequestModal extends Component
{
    use ResolvesShiftTeamMembers;

    public bool $showModal = false;

    public ?string $user_id = null;
    public ?string $covering_user_id = null;
    public ?string $roster_id = null;
    public ?string $shift_type_id = null;
    public ?string $date = null;
    public ?string $reason = null;

    public function openModal(): void
    {
        abort_unless(Auth::user()->can('create', ExtraShiftAssignment::class), 403);

        $this->reset(['user_id', 'covering_user_id', 'roster_id', 'shift_type_id', 'date', 'reason']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    /** Tim (lihat ResolvesShiftTeamMembers) + diri sendiri, yang beneran berjadwal shift - beda
     * dari TeamShiftScheduleCard (widget lihat-lihat, semua bawahan tampil apa adanya), di sini
     * TETAP difilter hasShiftSchedule() karena yang dipilih harus punya roster nyata buat
     * di-assign/di-cover, bukan sekadar "ada" di daftar tim. */
    public function getEligibleEmployeesProperty()
    {
        $user = Auth::user();

        return $this->subordinatesWithSharedSchedule($user)
            ->push($user)
            ->unique('id')
            ->filter(fn (User $u) => $u->hasShiftSchedule())
            ->sortBy('name')
            ->values();
    }

    public function updatedUserId(): void
    {
        $this->reset(['covering_user_id', 'roster_id', 'shift_type_id', 'date']);
    }

    /** Karyawan yang shift-nya diambil - daftar sama dengan eligibleEmployees, minus yang sudah
     * dipilih di atas (tidak masuk akal cover shift sendiri). */
    public function getCoveringOptionsProperty()
    {
        return $this->eligibleEmployees->reject(fn ($emp) => $emp->id === $this->user_id)->values();
    }

    public function updatedCoveringUserId(): void
    {
        $this->reset(['roster_id', 'shift_type_id', 'date']);
    }

    /** Roster REAL (tanggal + shift yang benar-benar dijadwalkan) milik karyawan yang di-cover,
     * dari hari ini s/d roster yang sudah di-generate - sama pola ExtraShiftPage, memastikan
     * shift yang diambil itu benar-benar ada di jadwal, bukan tanggal/jenis shift bebas. */
    public function getRosterOptionsProperty()
    {
        if (! $this->covering_user_id) {
            return collect();
        }

        return ScheduleShiftAssignment::where('user_id', $this->covering_user_id)
            ->where('off_day', false)
            ->whereNotNull('shift_type_id')
            ->whereDate('date', '>=', now()->toDateString())
            ->with('shiftType')
            ->orderBy('date')
            ->get();
    }

    public function updatedRosterId(): void
    {
        $roster = $this->rosterOptions->firstWhere('id', $this->roster_id);

        $this->shift_type_id = $roster?->shift_type_id;
        $this->date = $roster?->date?->toDateString();
    }

    public function submit(): void
    {
        abort_unless(Auth::user()->can('create', ExtraShiftAssignment::class), 403);

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'covering_user_id' => ['required', 'exists:users,id', 'different:user_id'],
            'roster_id' => ['required', 'exists:schedule_shift_assignments,id'],
            'shift_type_id' => ['required', 'exists:schedule_shift_types,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], ['roster_id' => 'shift yang diambil']);

        $user = User::findOrFail($data['user_id']);

        ExtraShiftAssignment::create([
            'company_id' => $user->company_id,
            'user_id' => $data['user_id'],
            'covering_user_id' => $data['covering_user_id'],
            'shift_type_id' => $data['shift_type_id'],
            'date' => $data['date'],
            'status' => 'approved',
            'source' => 'assigned',
            'reason' => $data['reason'] ?: null,
            'created_by' => Auth::id(),
        ]);

        Notification::make()->title('Extra shift berhasil ditambahkan')->success()->send();

        $this->showModal = false;
        $this->reset(['user_id', 'covering_user_id', 'roster_id', 'shift_type_id', 'date', 'reason']);
    }

    public function render()
    {
        return view('livewire.presensi.extra-shift-request-modal');
    }
}
