<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman pertama restrukturisasi Jadwal (Registry+Strategy baru, ganti "Shift" lama — lihat
 * planning/2026-07-18-restrukturisasi-jadwal-schedules.md). Filter company lewat Livewire
 * (server-side) — SEBELUMNYA pakai List.js di browser, tapi List.js kedapatan mengunci baris
 * tbody-nya sendiri sehingga tidak ikut ke-morph Livewire begitu companyId ganti (baris
 * perusahaan lama nyangkut terus), jadi dicabut. Tidak ada kotak cari, filter perusahaan sudah
 * cukup (jumlah jadwal per perusahaan kecil). "Tambah Jadwal" mengarah ke halaman terpisah
 * (CreateSchedulePage), bukan modal.
 */
#[Layout('components.layout', ['title' => 'Jadwal'])]
class SchedulesPage extends Component
{
    use GatesCompanyFilterBySystem;

    public ?string $companyId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:Schedule'), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_ABSENSI);
    }

    public function updatedCompanyId(): void
    {
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_ABSENSI);
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getSchedulesProperty()
    {
        return Schedule::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->with('head')
            ->orderBy('name')
            ->get();
    }

    public function scheduleTypeLabel(string $value): string
    {
        return ScheduleType::tryFrom($value)?->getLabel() ?? $value;
    }

    /** @return array{bg: string, text: string} Warna badge beda per tipe jadwal. */
    public function scheduleTypeColor(string $value): array
    {
        return match (ScheduleType::tryFrom($value)) {
            ScheduleType::FIXED => ['bg' => 'rgba(4,44,108,0.1)', 'text' => '#042c6c'],
            ScheduleType::FLEXIBLE => ['bg' => 'rgba(4,124,84,0.1)', 'text' => '#047C54'],
            ScheduleType::SHIFT => ['bg' => 'rgba(217,119,6,0.1)', 'text' => '#d97706'],
            default => ['bg' => 'var(--info-soft-bg)', 'text' => 'var(--info-soft-text)'],
        };
    }

    /**
     * Tombol Hapus cuma tampil kalau jadwal ini belum ada jabatan yang dipasangkan
     * (schedule_assignments) — dipakai sebagai proxy "riwayat/pemakaian" karena jadwal baru
     * ini (restrukturisasi 2026-07-18) belum tersambung ke perhitungan absensi aktual sama
     * sekali (attendance_records.schedule_id itu FK sistem shift LAMA, bukan tabel `schedules`
     * ini — beda konsep, tidak bisa dipakai buat cek jadwal ini).
     */
    public function hasAttendanceHistory(Schedule $schedule): bool
    {
        return ScheduleAssignment::where('schedule_id', $schedule->id)->exists();
    }

    public function getCanCreateScheduleProperty(): bool
    {
        return Auth::user()->can('create', Schedule::class);
    }

    public function deleteSchedule(string $id): void
    {
        $schedule = Schedule::findOrFail($id);

        abort_unless(Auth::user()->can('delete', $schedule), 403);

        abort_if(
            $this->hasAttendanceHistory($schedule),
            403,
            'Jadwal ini masih punya jabatan yang di-assign, tidak bisa dihapus.'
        );

        // forceDelete (bukan soft delete biasa) supaya cascadeOnDelete di tabel anak
        // (schedule_fixed_days/flexible_days/shift_types/shift_templates/assignments) ikut
        // kehapus bersih di DB — soft delete (UPDATE deleted_at) tidak memicu FK cascade.
        $schedule->forceDelete();

        Notification::make()
            ->title('Jadwal berhasil dihapus')
            ->success()
            ->send();
    }

    /**
     * Duplikat 1 jadwal beserta "peraturannya" — header + hari fixed/flexible + tipe shift.
     * Yang TIDAK ikut: penempatan jabatan (schedule_assignments), rotasi per-orang
     * (schedule_shift_rotations), roster harian, dan Kepala Ruangan (head_user_id) — itu
     * menempel ke orang/ruangan tertentu, bukan aturan jadwal. Hasilnya jadwal kosong-anggota
     * yang tinggal di-assign.
     */
    public function duplicateSchedule(string $id): void
    {
        $source = Schedule::with(['fixedDays', 'flexibleDays', 'shiftTypes'])->findOrFail($id);

        abort_unless(Auth::user()->can('duplicate', $source), 403);

        DB::transaction(function () use ($source) {
            $copy = $source->replicate(['head_user_id']);
            $copy->name = $this->uniqueCopyName($source->name, $source->company_id);
            $copy->save();

            foreach (['fixedDays', 'flexibleDays', 'shiftTypes'] as $relation) {
                foreach ($source->{$relation} as $child) {
                    $new = $child->replicate();
                    $new->schedule_id = $copy->id;
                    $new->save();
                }
            }
        });

        Notification::make()
            ->title('Jadwal berhasil diduplikat')
            ->success()
            ->send();
    }

    /** "Nama (Salinan)", lalu "Nama (Salinan 2)" dst kalau sudah ada — per company. */
    private function uniqueCopyName(string $base, ?string $companyId): string
    {
        $name = "{$base} (Salinan)";

        for ($i = 2; Schedule::where('company_id', $companyId)->where('name', $name)->exists(); $i++) {
            $name = "{$base} (Salinan {$i})";
        }

        return $name;
    }

    public function render()
    {
        return view('livewire.presensi.schedules-page');
    }
}
