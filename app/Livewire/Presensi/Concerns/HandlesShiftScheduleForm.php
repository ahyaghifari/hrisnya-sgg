<?php

namespace App\Livewire\Presensi\Concerns;

use App\Models\Master\OrgStructure;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Form tipe Shift di CreateSchedulePage — tabel kode shift (dinamis, tombol "+ Tambah Shift")
 * + tabel karyawan perusahaan yang dicentang buat dipakaikan jadwal ini. Genuinely beda
 * struktur dari Fixed/Flexible (bukan tabel 7-hari keyed tetap), makanya trait terpisah.
 * Pasangannya: Concerns\HandlesDayScheduleForm.
 */
trait HandlesShiftScheduleForm
{
    /** @var array<int, array<string, mixed>> baris kode shift, index dinamis (nambah/hapus
     * baris) — beda dari $days yang key-nya tetap 0-6. Tiap baris punya 'id' (null = baru,
     * terisi = baris lama pas Edit) buat bedain create/update/hapus saat save(). */
    public array $shiftTypes = [];

    /** Index baris shiftTypes yang lagi dibuka modal "Pengaturan Lanjutan"-nya, null = tertutup. */
    public ?int $advancedShiftIndex = null;

    /** @var array<int, string> id user yang dicentang buat pakai jadwal shift ini. */
    public array $selectedEmployeeIds = [];

    /** Kata kunci cari nama/NIP/jabatan/unit di tabel karyawan — server-side (bukan List.js),
     * List.js suka nyisain state/baris lama nyangkut kalau kejadian bareng morph Livewire pas
     * ganti perusahaan (tabelnya diganti total, bukan cuma disaring). */
    public string $employeeSearch = '';

    /** Filter divisi/struktur organisasi (org_structure_id) buat persempit tabel karyawan -
     * instruksi user 2026-08-16, tambahan di luar kata kunci nama/NIP/jabatan yang sudah ada.
     * Kosong = semua divisi. */
    public string $employeeOrgStructureFilter = '';

    /** Isi $shiftTypes + $selectedEmployeeIds dari relasi shiftTypes/ScheduleAssignment —
     * dipanggil dari CreateSchedulePage::mount() pas mode Edit. */
    protected function mountShiftSchedule(Schedule $schedule): void
    {
        foreach ($schedule->shiftTypes as $shiftType) {
            $data = $shiftType->toArray();
            $data['start_time'] = $this->formatTime($data['start_time'] ?? null);
            $data['end_time'] = $this->formatTime($data['end_time'] ?? null);
            $this->shiftTypes[] = $data;
        }

        $this->selectedEmployeeIds = ScheduleAssignment::where('schedule_id', $schedule->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();
    }

    /** Upsert baris $shiftTypes ke ScheduleShiftType (soft-delete yang dihapus user) + sync
     * $selectedEmployeeIds ke ScheduleAssignment (user_id, bukan job_position_id) — dipanggil
     * dari CreateSchedulePage::save() setelah header Schedule dibuat/diupdate. */
    protected function saveShiftSchedule(Schedule $schedule): void
    {
        $keepIds = [];

        foreach ($this->shiftTypes as &$row) {
            $id = $row['id'] ?? null;
            $payload = $row;
            unset($payload['id']);
            $payload['schedule_id'] = $schedule->id;

            $shiftType = $id
                ? tap(ScheduleShiftType::findOrFail($id))->update($payload)
                : ScheduleShiftType::create($payload);

            $row['id'] = $shiftType->id;
            $keepIds[] = $shiftType->id;
        }
        unset($row);

        // Baris yang dihapus user dari array (tombol hapus) ikut dihapus dari DB pas Edit
        // (soft-delete, kolomnya punya SoftDeletes).
        ScheduleShiftType::where('schedule_id', $schedule->id)->whereNotIn('id', $keepIds)->delete();

        // Karyawan yang dicentang di bawah tabel shift disimpan langsung di sini (bukan
        // lewat "Atur Pengguna Jadwal" tree jabatan) — user_id, bukan job_position_id. Disaring
        // ulang ke karyawan perusahaan INI (bukan percaya mentah $selectedEmployeeIds) — jaga
        // dari sisa id karyawan perusahaan lain yang ketinggalan kalau reset di ganti perusahaan
        // gagal/telat di sisi form.
        $validUserIds = $this->companyEmployeeIds();
        $this->selectedEmployeeIds = array_values(array_intersect($this->selectedEmployeeIds, $validUserIds));

        $previousIds = ScheduleAssignment::where('schedule_id', $schedule->id)->whereNotNull('user_id')->pluck('user_id')->all();

        ScheduleAssignment::where('schedule_id', $schedule->id)
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $this->selectedEmployeeIds)
            ->delete();

        foreach ($this->selectedEmployeeIds as $userId) {
            ScheduleAssignment::updateOrCreate(
                ['schedule_id' => $schedule->id, 'user_id' => $userId],
                ['company_id' => $schedule->company_id, 'effective_from' => now()->toDateString(), 'created_by' => Auth::id()]
            );
        }

        // Karyawan yang baru masuk jadwal ini (mis. dipindah dari jadwal lain) membawa roster lama
        // yang masih menunjuk shift jadwal asal - arahkan ke shift jadwal ini mulai hari ini.
        $newcomers = array_values(array_diff($this->selectedEmployeeIds, $previousIds));
        if ($newcomers) {
            app(\App\Services\Shift\RosterScheduleRemapper::class)->remap($schedule->load('shiftTypes'), $newcomers, now()->toDateString());
        }
    }

    /** @return array<int, string> id semua user di $this->companyId — dipakai buat memvalidasi
     * $selectedEmployeeIds tidak kebawa id karyawan perusahaan lain. */
    private function companyEmployeeIds(): array
    {
        return User::whereCompany($this->companyId)->pluck('id')->all();
    }

    /** Warna default tiap baris baru gantian dari palet ini (bukan hitam semua) — biar langsung
     * kebeda dari awal tanpa user harus pilih warna manual dulu. */
    private const DEFAULT_SHIFT_COLORS = ['#042c6c', '#047C54', '#d97706', '#dc2626', '#7c3aed', '#0284c7'];

    /** Baris kode shift baru, kosong — user isi manual satu-satu lewat tombol "+ Tambah Shift". */
    public function addShiftType(): void
    {
        $this->shiftTypes[] = [
            'id' => null,
            'code' => '',
            'name' => '',
            'shift_type' => null,
            'start_time' => '08:00',
            'break_minutes' => 0,
            'end_time' => '16:00',
            'is_overnight' => false,
            'overtime_before_min' => 0,
            'overtime_before_break' => 0,
            'overtime_before_max' => 0,
            'overtime_after_min' => 0,
            'overtime_after_break' => 0,
            'overtime_after_max' => 0,
            'checkin_buffer_minutes' => 150,
            'checkout_buffer_minutes' => 210,
            'late_tolerance_minutes' => 5,
            'early_leave_tolerance_minutes' => 0,
            'counted_work_days' => 1,
            'max_work_minutes' => 480,
            'half_day_threshold_minutes' => 0,
            'absent_threshold_minutes' => 0,
            'color' => self::DEFAULT_SHIFT_COLORS[count($this->shiftTypes) % count(self::DEFAULT_SHIFT_COLORS)],
            'font_color' => '#ffffff',
        ];
    }

    /** Salin baris $index ke baris baru di paling akhir. `id` null (jadi baris baru saat save)
     * dan `code` dikosongkan supaya user wajib isi inisial baru, bukan kembar. */
    public function duplicateShiftType(int $index): void
    {
        if (! isset($this->shiftTypes[$index])) {
            return;
        }

        $this->shiftTypes[] = ['id' => null, 'code' => ''] + $this->shiftTypes[$index];
    }

    /** Minimal 1 baris shift harus tetap ada — tombol hapus di blade sengaja disembunyikan
     * kalau cuma tersisa 1, guard di sini jaga-jaga kalau tetap terpanggil. */
    public function removeShiftType(int $index): void
    {
        if (count($this->shiftTypes) <= 1) {
            return;
        }

        unset($this->shiftTypes[$index]);
        $this->shiftTypes = array_values($this->shiftTypes);
    }

    public function openAdvancedShift(int $index): void
    {
        $this->advancedShiftIndex = $index;
    }

    public function closeAdvancedShift(): void
    {
        $this->advancedShiftIndex = null;
    }

    /** Karyawan perusahaan yang lagi dipilih (disaring $employeeSearch & $employeeOrgStructureFilter
     * kalau diisi) — dicentang buat dipakaikan jadwal shift ini lewat $selectedEmployeeIds. */
    public function getCompanyEmployeesProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::whereCompany($this->companyId)
            ->with('activeJobPosition.jobPosition.orgStructure')
            ->when($this->employeeOrgStructureFilter, fn ($query) => $query->whereHas(
                'activeJobPosition.jobPosition',
                fn ($jobQuery) => $jobQuery->where('org_structure_id', $this->employeeOrgStructureFilter)
            ))
            ->when($this->employeeSearch, function ($query) {
                $search = $this->employeeSearch;

                $query->where(function ($q) use ($search) {
                    $q->searchName($search)
                        ->orWhereIn('users.id', \App\Models\Employee\Employee::query()->select('user_id')->where('nip', 'like', "%{$search}%"))
                        ->orWhereHas('activeJobPosition.jobPosition', function ($jobQuery) use ($search) {
                            $jobQuery->where('name', 'like', "%{$search}%")
                                ->orWhereHas('orgStructure', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderByName()
            ->get();
    }

    /** Opsi dropdown filter divisi - divisi/unit company yang lagi dipilih saja (bukan semua
     * company), diurut nama. */
    public function getOrgStructureFilterOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name']);
    }

    /** Karyawan yang sudah dicentang — ditampilkan di tabel terpisah di bawah. Ikut disaring
     * company_id supaya id karyawan perusahaan lain yang ketinggalan (mis. reset gagal pas
     * ganti perusahaan) tidak pernah nongol di sini. */
    public function getSelectedEmployeesProperty()
    {
        if (empty($this->selectedEmployeeIds) || ! $this->companyId) {
            return collect();
        }

        return User::whereIn('id', $this->selectedEmployeeIds)
            ->whereCompany($this->companyId)
            ->with('activeJobPosition.jobPosition.orgStructure')
            ->orderByName()
            ->get();
    }

    public function removeSelectedEmployee(string $userId): void
    {
        $this->selectedEmployeeIds = array_values(array_diff($this->selectedEmployeeIds, [$userId]));
    }
}
