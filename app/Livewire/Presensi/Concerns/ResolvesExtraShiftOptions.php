<?php

namespace App\Livewire\Presensi\Concerns;

use App\Enum\ShiftType;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftRule;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftTeamMember;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Extra Shift BUKAN Tukar Shift - hanya berpengaruh ke SATU karyawan (instruksi user
 * 2026-08-19, mengoreksi desain awal yang salah kaprah: form lama mewajibkan pilih
 * "karyawan yang diambil shiftnya" lalu ambil 1 baris roster REAL milik orang lain itu, persis
 * pola Tukar Shift). Desain benar: pilih karyawan + tanggal, sistem baca shift NORMAL karyawan
 * itu sendiri di tanggal itu (via ScheduleResolver, sumber kebenaran yang sama dipakai
 * TeamShiftScheduleCard/JadwalSayaPage), lalu shift tambahan yang boleh dipilih dibatasi oleh
 * `extra_shift_rules` (pasangan from_shift_type->to_shift_type per company - baris tidak ada =
 * tidak boleh, lihat ExtraShiftRule).
 */
trait ResolvesExtraShiftOptions
{
    /**
     * @return array{category: string, label: string, scheduleId: string}|null null kalau karyawan
     *         ini bukan anggota jadwal tipe Shift. Extra shift butuh basis buat menentukan aturan
     *         `from_shift_type` mana yang berlaku: kalau tanggal itu ada shift normal, basisnya
     *         kategori shift itu; kalau rosternya KOSONG (belum diisi) atau LIBUR (off_day),
     *         basisnya kategori sentinel `libur` (instruksi user - yang lagi libur/belum diroster
     *         tetap boleh diajukan extra shift, jadwalnya tetap libur).
     */
    protected function resolveNormalShift(User $user, string $date): ?array
    {
        $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $date);

        if ($resolved && $resolved['type'] === 'shift' && ! ($resolved['is_off_day'] ?? false)) {
            $config = $resolved['config'] ?? null; // ScheduleShiftType

            if (! $config) {
                return null;
            }

            return [
                'category' => $config->shift_type,
                'label' => $config->name,
                'time' => substr((string) $config->start_time, 0, 5) . '–' . substr((string) $config->end_time, 0, 5),
                'scheduleId' => $config->schedule_id,
            ];
        }

        // Sampai sini = tidak ada shift normal aktif hari itu. Boleh extra shift HANYA kalau
        // karyawan ini anggota jadwal tipe Shift (Fixed/Flexible yang lagi libur tidak dapat)
        // DAN tanggal itu memang tidak punya shift lain (roster kosong -> $resolved null, atau
        // roster off_day -> type shift + is_off_day). Fixed/Flexible off day -> type-nya bukan
        // shift -> ditolak di sini.
        if ($resolved && $resolved['type'] !== 'shift') {
            return null;
        }

        $assignment = ScheduleAssignment::resolveForUser($user, $date);

        if (! $assignment || $assignment->schedule?->schedule_type !== 'shift') {
            return null;
        }

        return [
            'category' => 'libur',
            'label' => 'Libur',
            'scheduleId' => $assignment->schedule_id,
        ];
    }

    /** Karyawan ini SUDAH punya extra shift lain di tanggal itu (dari sumber mana pun - assigned,
     * requested, atau otomatis dari tukar shift) - tidak boleh ditumpuk dobel, satu extra shift
     * per orang per hari sudah cukup berat. */
    protected function hasExtraShiftOnDate(User $user, string $date): bool
    {
        return ExtraShiftAssignment::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->exists();
    }

    /**
     * Shift tambahan yang boleh dipilih untuk (karyawan, tanggal) ini - kosong kalau karyawan
     * tidak punya shift normal di tanggal itu, SUDAH punya extra shift lain di tanggal itu, atau
     * tidak ada baris `extra_shift_rules` yang mengizinkan transisi dari kategori shift
     * normalnya. Opsinya diambil dari Schedule YANG SAMA dengan shift normal karyawan (bukan
     * company-wide) - tidak masuk akal menugaskan shift dari jadwal unit lain. Kalau karyawan ini
     * anggota Regu bulan itu, dipersempit lagi ke shift jatah Regu-nya saja (2026-09-15).
     *
     * @return Collection<int, ScheduleShiftType>
     */
    protected function resolveAllowedExtraShiftTypes(User $user, string $date): Collection
    {
        $normal = $this->resolveNormalShift($user, $date);

        if (! $normal || $this->hasExtraShiftOnDate($user, $date)) {
            return collect();
        }

        $allowedCategories = ExtraShiftRule::query()
            ->where('company_id', $user->company_id)
            ->where('from_shift_type', $normal['category'])
            ->pluck('to_shift_type')
            ->all();

        $schedule = Schedule::find($normal['scheduleId']);

        // Oncall SENGAJA di luar extra_shift_rules (pasangan yang admin atur di sana cuma
        // Pagi/Siang/Malam, lihat ExtraShiftRulePage::getShiftTypeOptionsProperty()) - baru ikut
        // jadi pilihan Extra Shift kalau jadwal INI menyalakan schedules.allow_oncall_extra_shift
        // (instruksi user 2026-09-29 - per Schedule, bukan keputusan company-wide, karena unit
        // lain belum tentu mau Oncall dobel jadi Extra Shift juga).
        if ($schedule?->allow_oncall_extra_shift) {
            $allowedCategories[] = ShiftType::ONCALL->value;
        }

        if (empty($allowedCategories)) {
            return collect();
        }

        $types = ScheduleShiftType::query()
            ->where('schedule_id', $normal['scheduleId'])
            ->whereIn('shift_type', $allowedCategories)
            ->orderBy('start_time')
            ->get();

        // Karyawan ini anggota Regu bulan ini (jadwal pakai Regu) - Extra Shift dibatasi cuma
        // shift jatah Regu-nya juga, sama seperti roster harian manual/generate (instruksi user
        // 2026-09-15: Regu A tidak boleh nyerobot shift tambahan Regu B). null = tidak dibatasi
        // (Regu tidak aktif / user belum punya Regu bulan ini), lihat allowedShiftTypeIdsFor().
        $allowedTeamIds = $schedule
            ? ScheduleShiftTeamMember::allowedShiftTypeIdsFor($schedule, $user->id, Carbon::parse($date)->format('Y-m'))
            : null;

        if ($allowedTeamIds !== null) {
            $types = $types->whereIn('id', $allowedTeamIds)->values();
        }

        return $types;
    }
}
