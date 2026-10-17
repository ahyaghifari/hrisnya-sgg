<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Presensi\Concerns\ResolvesExtraShiftOptions;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftRule;
use App\Models\Employee\Employee;
use App\Services\Attendance\AttendancePeriodResolver;
use Carbon\Carbon;

/**
 * Jasa Extra Shift = nominal TETAP per kejadian, dibaca dari `extra_shift_rules.amount` (pasangan
 * shift normal karyawan di hari itu -> shift extra yang diambil, per company; diisi finance di
 * Mapping Komponen Gaji tab Extra Shift). Tidak ada rumus/prorate - jumlah kejadian x nominal.
 *
 * Semua ExtraShiftAssignment `approved` berkategori `extra_shift` di window absensi periode ini
 * dibayar, TANPA syarat catatan presensi (keputusan user 2026-09-20: assign manual = dianggap
 * dikerjakan). `double_shift` (akibat tukar shift bentrok) sengaja TIDAK dibayar. Kejadian yang
 * pasangan aturannya tidak ada / nominalnya kosong / 0 dilewati diam-diam (sama pola aturan
 * "baris tidak ada = tidak berlaku" di ExtraShiftRulePage).
 *
 * Basis "shift asal" dihitung ulang pas payroll di-generate lewat resolveNormalShift() yang sama
 * dengan yang dipakai saat extra shift dibuat - kalau roster hari itu diubah setelahnya, hasilnya
 * ikut berubah (bukan snapshot saat assign).
 *
 * 1 baris payroll_items per pasangan (mis. "Extra Shift (Pagi → Siang)" x 3 kali) supaya nominal
 * satuan per pasangan tetap terbaca di slip.
 */
class ExtraShiftCalculator implements PayrollComponentCalculator
{
    use ResolvesExtraShiftOptions;

    public function calculate(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        // Window absensi (21-20 default), BUKAN literal start/end_date PayrollPeriod - sama
        // seperti LemburCalculator/Keterlambatan.
        [$start, $end] = AttendancePeriodResolver::resolve($period->start_date, $period->company_id);

        $assignments = ExtraShiftAssignment::query()
            ->where('user_id', $employee->user_id)
            ->where('status', 'approved')
            ->where('category', ExtraShiftAssignment::CATEGORY_EXTRA_SHIFT)
            ->whereBetween('date', [$start, $end])
            ->with('shiftType')
            ->orderBy('date')
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $rules = ExtraShiftRule::query()
            ->where('company_id', $period->company_id)
            ->get()
            ->keyBy(fn (ExtraShiftRule $r) => $r->from_shift_type . '|' . $r->to_shift_type);

        $groups = [];

        foreach ($assignments as $assignment) {
            $to = $assignment->shiftType?->shift_type;
            $date = Carbon::parse($assignment->date)->toDateString();
            $from = $this->resolveNormalShift($employee->user, $date)['category'] ?? null;

            if (! $to || ! $from) {
                continue;
            }

            $rule = $rules->get($from . '|' . $to);
            $unit = (float) ($rule?->amount ?? 0);

            if (! $rule || $unit <= 0) {
                continue;
            }

            $groups[$rule->id] ??= ['rule' => $rule, 'unit' => $unit, 'dates' => []];
            $groups[$rule->id]['dates'][] = Carbon::parse($date)->translatedFormat('j M');
        }

        foreach ($groups as $group) {
            $count = count($group['dates']);
            $amount = round($group['unit'] * $count, 2);

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => PayrollComponentEnum::EXTRA_SHIFT->label()
                    . " ({$group['rule']->from_shift_type_label} → {$group['rule']->to_shift_type_label})",
                'type' => PayrollComponentEnum::EXTRA_SHIFT->type()->value,
                'quantity' => $count,
                'unit' => 'kali',
                'unit_amount' => round($group['unit'], 2),
                'amount' => $amount,
                'note' => implode(', ', $group['dates']),
                'source_type' => PayrollComponentEnum::EXTRA_SHIFT->value,
            ]);

            $context->addEarning($amount);
        }
    }
}
