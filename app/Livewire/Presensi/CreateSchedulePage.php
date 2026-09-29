<?php

namespace App\Livewire\Presensi;

use App\Enum\ScheduleType;
use App\Livewire\Presensi\Concerns\HandlesDayScheduleForm;
use App\Livewire\Presensi\Concerns\HandlesShiftScheduleForm;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\User;
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
 * meng-update baris yang sudah ada alih-alih bikin baru. Shift yang BARU dibuat langsung
 * redirect ke halaman Lihat Jadwal (lihat method save()) — tab Template Shift/Detail
 * Penjadwalan di sana yang sekarang jadi tempat nambah baris shift & karyawan, bukan lagi di
 * halaman ini. Save berikutnya (sudah dalam mode Edit) tetap di halaman ini seperti tipe lain.
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

    /** "Kepala Ruangan" - opsional, dipakai TeamShiftScheduleCard buat resolve link "Detail
     * Jadwal" ke Schedule yang benar kalau atasan punya bawahan di lebih dari 1 Schedule. */
    public ?string $headUserId = null;

    /** Pola shift (2 atau 3 shift) - cuma relevan buat scheduleType SHIFT, lihat
     * App\Enum\ShiftPatternEnum. Null = belum diisi (schedule lama sebelum field ini ada, atau
     * tipe Fixed/Flexible). */
    public ?string $shiftPattern = null;

    /** Saklar `schedules.allow_oncall_extra_shift` - cuma relevan buat scheduleType SHIFT.
     * Ditaruh di halaman Edit Jadwal ini (bukan tab Extra Shift lagi, instruksi user 2026-09-29)
     * supaya 1 tempat sama dengan pengaturan jadwal lain (Kepala Ruangan, Pola Shift dst),
     * bukan nyebar di tab terpisah. Default FALSE - Oncall tidak pernah muncul di pilihan Extra
     * Shift kecuali dinyalakan eksplisit di sini. */
    public bool $allowOncallExtraShift = false;

    public function mount(?Schedule $schedule = null): void
    {
        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        // Livewire suntik instance Schedule KOSONG (bukan null) kalau parameter route/test
        // "schedule" tidak diisi sama sekali — ->exists yang benar-benar bedain mode Edit vs
        // Tambah, bukan null-check polos.
        if (! $schedule || ! $schedule->exists) {
            abort_unless(Auth::user()->can('create', Schedule::class), 403);

            return;
        }

        abort_unless(Auth::user()->can('update', $schedule), 403);

        $this->editingScheduleId = $schedule->id;
        $this->companyId = $schedule->company_id;
        $this->name = $schedule->name;
        $this->scheduleType = $schedule->schedule_type;
        $this->shiftPattern = $schedule->shift_pattern;
        $this->headUserId = $schedule->head_user_id;
        // Cast eksplisit (bukan percaya cast 'boolean' Eloquent apa adanya) - jadwal yang baru
        // dibuat lewat Schedule::create() TANPA eksplisit isi kolom ini (mis. fixture test lama,
        // sebelum kolom ini ada) punya instance model yang atributnya BELUM ke-refresh dari DB,
        // jadi $schedule->allow_oncall_extra_shift bisa null walau kolomnya sendiri NOT NULL
        // default 0 - ditemukan 2026-09-29 lewat CreateSchedulePageTest yang bikin Schedule
        // manual tanpa lewat save() form ini.
        $this->allowOncallExtraShift = (bool) $schedule->allow_oncall_extra_shift;
        $this->step = 2;

        if ($this->scheduleType === ScheduleType::SHIFT->value) {
            $this->mountShiftSchedule($schedule);
        } else {
            $this->mountDaySchedule($schedule);
        }
    }

    /** Ganti perusahaan -> daftar karyawan berubah total, jadi centangan lama & kata kunci/filter
     * cari lama tidak relevan lagi. Ini SATU-SATUNYA updatedCompanyId() sekarang - dulu ada versi
     * kedua di trait HandlesShiftScheduleForm yang isinya reset $selectedEmployeeIds/
     * $employeeSearch, tapi method di class SELALU menang diam-diam kalau namanya sama persis
     * dengan method trait (PHP tidak error, cuma di-shadow) - punya trait jadi dead code sejak
     * awal, makanya centangan karyawan tidak pernah kereset pas ganti company. Digabung di sini
     * (2026-08-16) biar cuma ada 1 sumber kebenaran. */
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->selectedEmployeeIds = [];
        $this->employeeSearch = '';
        $this->employeeOrgStructureFilter = '';
        $this->headUserId = null;
    }

    /** Opsi "Kepala Ruangan" - semua karyawan aktif di company yang lagi dipilih, sama scope-nya
     *  dengan pemilihan karyawan buat "Atur Pengguna Jadwal" (tidak dibatasi jabatan tertentu,
     *  kepala ruangan bisa jabatan apa saja). */
    public function getHeadUserOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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

    /** @return \App\Enum\ShiftPatternEnum[] */
    public function getShiftPatternsProperty(): array
    {
        return \App\Enum\ShiftPatternEnum::cases();
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
        // Re-kunci sebelum persist - bukan cuma percaya updatedCompanyId(), Livewire property
        // bisa di-$set langsung dari request yang di-craft (lewat hook updated*, bukan lewat
        // method public biasa), jadi companyId perlu divalidasi ulang persis sebelum ditulis.
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        // Mode Edit: perusahaan terkunci total (instruksi user 2026-08-16) - jadwal yang sudah
        // ada assignment/roster/riwayat presensi terikat ke company_id-nya, ganti company bisa
        // bikin data itu jadi orphan/salah scope. Select-nya sudah @disabled di blade, ini
        // gerbang server-side-nya (defense-in-depth, sama pola block di atas) - paksa balik ke
        // company_id ASLI dari DB, jangan percaya $this->companyId sama sekali di mode Edit.
        if ($this->editingScheduleId) {
            $this->companyId = Schedule::findOrFail($this->editingScheduleId)->company_id;
        }

        $this->validate([
            'companyId' => 'required',
            'name' => 'required|string|max:255',
            'scheduleType' => 'required|in:' . implode(',', array_column(ScheduleType::cases(), 'value')),
            'shiftPattern' => $this->scheduleType === ScheduleType::SHIFT->value
                ? 'required|in:' . implode(',', array_column(\App\Enum\ShiftPatternEnum::cases(), 'value'))
                : 'nullable',
            'headUserId' => $this->scheduleType === ScheduleType::SHIFT->value ? 'required' : 'nullable',
        ], [], [
            'companyId' => 'perusahaan',
            'name' => 'nama jadwal',
            'scheduleType' => 'tipe jadwal',
            'shiftPattern' => 'pola shift',
            'headUserId' => 'kepala ruangan',
        ]);

        $scheduleData = [
            'company_id' => $this->companyId,
            'name' => $this->name,
            'schedule_type' => $this->scheduleType,
            'flexible_checkin_from' => $this->scheduleType === ScheduleType::FLEXIBLE->value ? $this->flexibleCheckinFrom : null,
            'flexible_checkin_until' => $this->scheduleType === ScheduleType::FLEXIBLE->value ? $this->flexibleCheckinUntil : null,
            'shift_pattern' => $this->scheduleType === ScheduleType::SHIFT->value ? $this->shiftPattern : null,
            // Kepala Ruangan cuma relevan buat Jadwal Shift (field-nya cuma tampil di sana, lihat
            // blade) - dipaksa null buat tipe lain, jaga-jaga headUserId nyangkut nilai lama
            // kalau user sempat pilih Shift lalu balik ganti tipe sebelum submit.
            'head_user_id' => $this->scheduleType === ScheduleType::SHIFT->value ? ($this->headUserId ?: null) : null,
            // Sama seperti head_user_id - cuma relevan buat Jadwal Shift, dipaksa false buat
            // tipe lain (Fixed/Flexible tidak punya konsep Extra Shift sama sekali).
            'allow_oncall_extra_shift' => $this->scheduleType === ScheduleType::SHIFT->value ? $this->allowOncallExtraShift : false,
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
            // Baru dibuat (belum pernah punya editingScheduleId) -> langsung pindah ke halaman
            // Lihat Jadwal (tab Template Shift/Detail Penjadwalan di sana sekarang jadi tempat
            // nambah baris shift & karyawan, bukan lagi di halaman ini). Save berikutnya
            // (sudah dalam mode Edit) tetap di halaman ini seperti biasa.
            $wasCreating = ! $this->editingScheduleId;
            $this->editingScheduleId = $schedule->id;

            if ($wasCreating) {
                Notification::make()->title('Jadwal shift berhasil dibuat')->success()->send();

                $this->redirect(route('presensi.jadwal.show', $schedule), navigate: true);

                return;
            }

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
