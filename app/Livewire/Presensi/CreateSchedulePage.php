<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\Presensi\Concerns\HandlesDayScheduleForm;
use App\Livewire\Presensi\Concerns\HandlesShiftScheduleForm;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Halaman terpisah (bukan modal, atas permintaan user) buat bikin/ubah 1 `schedules`. Cuma
 * orkestrator tipis di sini (step, company/name, mount/save) — logic per tipe jadwal ada di
 * Concerns\HandlesDayScheduleForm (Fixed & Flexible, berbagi struktur tabel 7-hari) dan
 * Concerns\HandlesShiftScheduleForm (Shift, tabel kode dinamis + assignment karyawan). Blade-nya
 * juga dipecah sama — lihat resources/views/livewire/presensi/partials/.
 *
 * Dipakai dobel buat Create (`/presensi/jadwal/tambah`, `$schedule` null) dan Edit
 * (`/presensi/jadwal/{schedule}/edit`) — beda dari Create, Edit langsung lompat ke step 2
 * (tipe jadwal dikunci, tidak bisa diganti setelah ada baris hari tersimpan) dan `save()`
 * meng-update baris yang sudah ada alih-alih bikin baru. Shift adalah pengecualian: `save()`
 * SENGAJA tidak redirect (lihat method save()), jadi begitu jadwal shift pertama kali
 * tersimpan, halaman ini otomatis masuk mode Edit juga supaya save berikutnya meng-update.
 */
class CreateSchedulePage extends Component
{
    use HandlesDayScheduleForm;
    use HandlesShiftScheduleForm;

    /** 1 = pilih tipe jadwal (kartu ikon), 2 = isi detail + tabel hari (Fixed/Flexible). */
    public int $step = 1;

    /** Null = mode Tambah. Terisi = mode Edit, tipe jadwal terkunci. */
    public ?string $editingScheduleId = null;

    public ?string $companyId = null;
    public string $name = '';
    public string $scheduleType = '';

    public function mount(?Schedule $schedule = null): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');

        // Livewire suntik instance Schedule KOSONG (bukan null) kalau parameter route/test
        // "schedule" tidak diisi sama sekali — ->exists yang benar-benar bedain mode Edit vs
        // Tambah, bukan null-check polos.
        if (! $schedule || ! $schedule->exists) {
            return;
        }

        $this->editingScheduleId = $schedule->id;
        $this->companyId = $schedule->company_id;
        $this->name = $schedule->name;
        $this->scheduleType = $schedule->schedule_type;
        $this->step = 2;

        if ($this->scheduleType === ScheduleType::SHIFT->value) {
            $this->mountShiftSchedule($schedule);
        } else {
            $this->mountDaySchedule($schedule);
        }
    }

    /** "08:30:00" (MySQL) atau "08:30" (SQLite, tidak menormalkan TIME) -> selalu "08:30". */
    protected function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getScheduleTypesProperty(): array
    {
        return ScheduleType::cases();
    }

    public function selectType(string $type): void
    {
        $this->scheduleType = $type;
    }

    public function nextStep(): void
    {
        $this->validate([
            'scheduleType' => 'required|in:' . implode(',', array_column(ScheduleType::cases(), 'value')),
        ], [], ['scheduleType' => 'tipe jadwal']);

        if ($this->scheduleType === ScheduleType::SHIFT->value) {
            // Sediakan 1 baris shift langsung — user tinggal isi, bukan mulai dari tabel kosong.
            if (empty($this->shiftTypes)) {
                $this->addShiftType();
            }
        } else {
            $this->initDays();
        }

        $this->step = 2;
    }

    public function backStep(): void
    {
        $this->step = 1;
    }

    public function save(): void
    {
        $this->validate([
            'companyId' => 'required',
            'name' => 'required|string|max:255',
            'scheduleType' => 'required|in:' . implode(',', array_column(ScheduleType::cases(), 'value')),
        ], [], [
            'companyId' => 'perusahaan',
            'name' => 'nama jadwal',
            'scheduleType' => 'tipe jadwal',
        ]);

        $scheduleData = [
            'company_id' => $this->companyId,
            'name' => $this->name,
            'schedule_type' => $this->scheduleType,
            'flexible_checkin_from' => $this->scheduleType === ScheduleType::FLEXIBLE->value ? $this->flexibleCheckinFrom : null,
            'flexible_checkin_until' => $this->scheduleType === ScheduleType::FLEXIBLE->value ? $this->flexibleCheckinUntil : null,
        ];

        $schedule = DB::transaction(function () use ($scheduleData) {
            if ($this->editingScheduleId) {
                $schedule = Schedule::findOrFail($this->editingScheduleId);

                // Snapshot dulu (baca kondisi lama) SEBELUM header di-update — Shift belum
                // punya tab Riwayat jadi dilewati.
                if ($this->scheduleType !== ScheduleType::SHIFT->value) {
                    $this->snapshotDayScheduleChangeLog($schedule);
                }

                $schedule->update($scheduleData);
            } else {
                // Penempatan jabatan (dulu rencananya org_structure_id di sini, kolomnya sudah
                // dihapus — tidak pernah dipakai) ditentukan lewat ScheduleAssignmentsPage
                // ("Atur Pengguna Jadwal") setelah jadwal ini disimpan, bukan pas bikin header.
                $schedule = Schedule::create($scheduleData);
            }

            if ($this->scheduleType === ScheduleType::SHIFT->value) {
                $this->saveShiftSchedule($schedule);
            } else {
                $this->saveDaySchedule($schedule);
            }

            return $schedule;
        });

        if ($this->scheduleType === ScheduleType::SHIFT->value) {
            // Sengaja TIDAK redirect — halaman ini dipakai buat nambah baris shift & karyawan
            // berkali-kali sebelum pindah, atas permintaan user.
            $this->editingScheduleId = $schedule->id;

            Notification::make()->title('Jadwal shift berhasil disimpan')->success()->send();

            return;
        }

        if ($this->editingScheduleId) {
            Notification::make()->title('Jadwal berhasil diubah')->success()->send();

            $this->redirect(route('presensi.jadwal.show', $schedule), navigate: true);

            return;
        }

        Notification::make()
            ->title('Jadwal berhasil dibuat')
            ->body('Lanjutkan atur jabatan mana yang memakai jadwal ini.')
            ->success()
            ->send();

        // Otomatis lanjut ke "Atur Pengguna Jadwal" (tree jabatan) — bukan balik ke daftar jadwal.
        $this->redirect(route('presensi.jadwal.pengguna', $schedule), navigate: true);
    }

    public function render()
    {
        return view('livewire.presensi.create-schedule-page')
            ->layout('components.layout', ['title' => $this->editingScheduleId ? 'Edit Jadwal' : 'Tambah Jadwal']);
    }
}
