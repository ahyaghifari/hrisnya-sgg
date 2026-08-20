<?php

namespace App\Livewire\Presensi\Concerns;

use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftRule;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
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
     * @return array{category: string, label: string}|null null kalau karyawan ini TIDAK punya
     *         shift normal (roster shift, bukan off_day) di tanggal itu - extra shift butuh
     *         basis shift normal buat menentukan aturan `from_shift_type` mana yang berlaku.
     */
    protected function resolveNormalShift(User $user, string $date): ?array
    {
        $resolved = app(ScheduleResolver::class)->resolveForUserDate($user, $date);

        if (! $resolved || $resolved['type'] !== 'shift' || ($resolved['is_off_day'] ?? false)) {
            return null;
        }

        $config = $resolved['config'] ?? null; // ScheduleShiftType

        if (! $config) {
            return null;
        }

        return [
            'category' => $config->shift_type,
            'label' => $config->name,
            'scheduleId' => $config->schedule_id,
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
     * company-wide) - tidak masuk akal menugaskan shift dari jadwal unit lain.
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
            ->pluck('to_shift_type');

        if ($allowedCategories->isEmpty()) {
            return collect();
        }

        return ScheduleShiftType::query()
            ->where('schedule_id', $normal['scheduleId'])
            ->whereIn('shift_type', $allowedCategories)
            ->orderBy('start_time')
            ->get();
    }
}
