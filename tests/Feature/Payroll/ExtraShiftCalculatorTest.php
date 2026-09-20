<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ExtraShiftRule;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Services\Payroll\Calculators\ExtraShiftCalculator;
use App\Services\Payroll\Calculators\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Jasa Extra Shift = jumlah kejadian x nominal tetap `extra_shift_rules.amount` (pasangan shift asal -> tujuan). */
class ExtraShiftCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $user;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;
    private ScheduleShiftType $malam;
    private PayrollPeriod $period;
    private Payroll $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'XS', 'name' => 'Extra Shift Co', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $make = fn (string $code, string $type, string $start, string $end) => ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => $code, 'name' => ucfirst($type), 'shift_type' => $type,
            'start_time' => $start, 'end_time' => $end, 'counted_work_days' => 1,
        ]);
        $this->pagi = $make('PAGI', 'pagi', '07:00:00', '14:00:00');
        $this->siang = $make('SIANG', 'siang', '14:00:00', '21:00:00');
        $this->malam = $make('MALAM', 'malam', '22:00:00', '06:00:00');

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        // Kasus "libur" (roster kosong/off_day) butuh karyawan terdaftar di jadwal tipe Shift.
        ScheduleAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'schedule_id' => $schedule->id, 'effective_from' => '2026-01-01', 'created_by' => $this->user->id]);
        $this->period =PayrollPeriod::create(['company_id' => $this->company->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $this->payroll = Payroll::create(['user_id' => $this->user->id, 'payroll_period_id' => $this->period->id]);
    }

    private function rule(string $from, string $to, ?int $amount): void
    {
        ExtraShiftRule::create(['company_id' => $this->company->id, 'from_shift_type' => $from, 'to_shift_type' => $to, 'amount' => $amount]);
    }

    /** Roster normal $normal + extra shift $extra di tanggal yang sama. */
    private function extraShift(string $date, ScheduleShiftType $normal, ScheduleShiftType $extra, string $status = 'approved', string $category = 'extra_shift'): void
    {
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'shift_type_id' => $normal->id, 'date' => $date, 'off_day' => false]);
        ExtraShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id, 'shift_type_id' => $extra->id, 'date' => $date,
            'status' => $status, 'source' => 'assigned', 'category' => $category, 'created_by' => $this->user->id,
        ]);
    }

    private function run_(): PayrollContext
    {
        $context = new PayrollContext();
        (new ExtraShiftCalculator())->calculate($this->user, $this->payroll, $this->period, $context);

        return $context;
    }

    public function test_pays_fixed_amount_per_extra_shift_grouped_by_pair(): void
    {
        $this->rule('pagi', 'siang', 50_000);
        $this->extraShift('2026-07-05', $this->pagi, $this->siang);
        $this->extraShift('2026-07-12', $this->pagi, $this->siang);

        $context = $this->run_();

        $this->assertSame(100_000.0, $context->grossSalary);
        $item = $this->payroll->items()->where('source_type', 'extra_shift')->sole();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame(100_000.0, (float) $item->amount);
        $this->assertSame(50_000.0, (float) $item->unit_amount);
    }

    public function test_different_pairs_get_their_own_rows_and_amounts(): void
    {
        $this->rule('pagi', 'siang', 50_000);
        $this->rule('pagi', 'malam', 80_000);
        $this->extraShift('2026-07-05', $this->pagi, $this->siang);
        $this->extraShift('2026-07-06', $this->pagi, $this->malam);

        $context = $this->run_();

        $this->assertSame(130_000.0, $context->grossSalary);
        $this->assertSame(2, $this->payroll->items()->where('source_type', 'extra_shift')->count());
    }

    public function test_double_shift_pending_and_out_of_window_are_not_paid(): void
    {
        $this->rule('pagi', 'siang', 50_000);
        $this->extraShift('2026-07-05', $this->pagi, $this->siang, category: 'double_shift');
        $this->extraShift('2026-07-06', $this->pagi, $this->siang, status: 'pending');
        $this->extraShift('2026-08-02', $this->pagi, $this->siang);

        $context = $this->run_();

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertSame(0, $this->payroll->items()->count());
    }

    public function test_missing_rule_or_empty_amount_is_skipped(): void
    {
        $this->rule('pagi', 'siang', null);
        $this->rule('pagi', 'malam', 0);
        $this->extraShift('2026-07-05', $this->pagi, $this->siang);
        $this->extraShift('2026-07-06', $this->pagi, $this->malam);
        $this->extraShift('2026-07-07', $this->siang, $this->malam); // tidak ada aturannya

        $context = $this->run_();

        $this->assertSame(0.0, $context->grossSalary);
        $this->assertSame(0, $this->payroll->items()->count());
    }

    public function test_extra_shift_on_off_day_uses_libur_rule(): void
    {
        $this->rule('libur', 'pagi', 70_000);
        ScheduleShiftAssignment::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'shift_type_id' => null, 'date' => '2026-07-09', 'off_day' => true]);
        ExtraShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id, 'shift_type_id' => $this->pagi->id, 'date' => '2026-07-09',
            'status' => 'approved', 'source' => 'assigned', 'category' => 'extra_shift', 'created_by' => $this->user->id,
        ]);

        $context = $this->run_();

        $this->assertSame(70_000.0, $context->grossSalary);
    }
}
