<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\ShiftSwapRecapPage;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShiftSwapRecapPageTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'REK', 'name' => 'Rekap Co', 'location' => 'Test', 'is_active' => true]);
    }

    private function viewer(): User
    {
        $name = CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value;
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekap Tukar', 'guard_name' => 'web']);
        $role->givePermissionTo($name);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeSwap(User $requester, User $target, string $date, string $status): ShiftSwapRequests
    {
        $schedule = Schedule::firstOrCreate(['company_id' => $this->company->id, 'name' => 'Shift RS'], ['schedule_type' => 'shift']);
        $type = ScheduleShiftType::firstOrCreate(['schedule_id' => $schedule->id, 'code' => 'PAGI'], [
            'name' => 'Pagi', 'shift_type' => 'pagi', 'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        $row = fn (User $u) => ScheduleShiftAssignment::create([
            'company_id' => $this->company->id, 'user_id' => $u->id, 'shift_type_id' => $type->id, 'date' => $date,
        ]);

        return ShiftSwapRequests::create([
            'company_id' => $this->company->id, 'requester_id' => $requester->id, 'target_id' => $target->id,
            'req_schedule_id' => $row($requester)->id, 'target_schedule_id' => $row($target)->id,
            'req_date' => $date, 'target_date' => $date, 'status' => $status,
        ]);
    }

    public function test_forbidden_without_permission(): void
    {
        Livewire::actingAs(User::factory()->create(['company_id' => $this->company->id]))
            ->test(ShiftSwapRecapPage::class)
            ->assertForbidden();
    }

    public function test_swap_counts_for_requester_and_partner_per_status_within_period(): void
    {
        $a = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Ani Pengaju']);
        $b = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Rekan']);
        $this->makeSwap($a, $b, '2026-07-05', 'approved');
        $this->makeSwap($a, $b, '2026-07-06', 'in_review');
        $this->makeSwap($a, $b, '2026-08-06', 'approved'); // di luar periode

        $recap = Livewire::actingAs($this->viewer())->test(ShiftSwapRecapPage::class)
            ->set('date_from', '2026-07-01')
            ->set('date_to', '2026-07-31')
            ->assertSee('Ani Pengaju')->assertSee('Budi Rekan')
            ->instance()->recap;

        $this->assertSame(1, $recap['overall']['counts']['approved']);
        $this->assertSame(1, $recap['overall']['counts']['in_review']);
        $this->assertSame(2, $recap['overall']['employee_count']);
        $this->assertSame(2, $recap['rows'][0]['total']);
    }
}
