<?php

namespace Tests\Feature\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\ShiftType;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\Shift\Schedule;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ShiftSwapRequestService/ApprovalFlowService dipindah dari model roster LAMA
 * (ShiftScheduleAssignments/shift_schedule_assignments, sudah tidak ada penulis aktif) ke BARU
 * (ScheduleShiftAssignment/schedule_shift_assignments, sumber roster yang dipakai perhitungan
 * absensi sekarang) - 2026-08-14. Test ini memverifikasi jalur store() + eksekusi swap saat
 * approval final disetujui benar-benar berjalan di skema baru (bukan cuma lolos syntax).
 */
class ShiftSwapRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;
    private ScheduleShiftType $malam;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create([
            'code' => 'SWP',
            'name' => 'RS Simulasi',
            'location' => 'Banjarbaru',
            'is_active' => true,
        ]);

        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Jadwal Perawat',
            'schedule_type' => 'shift',
        ]);

        $this->pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'PAGI',
            'name' => 'Pagi',
            'shift_type' => ShiftType::PAGI->value,
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_overnight' => false,
        ]);

        $this->siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'SIANG',
            'name' => 'Siang',
            'shift_type' => ShiftType::SIANG->value,
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
            'is_overnight' => false,
        ]);

        $this->malam = ScheduleShiftType::create([
            'schedule_id' => $schedule->id,
            'code' => 'MALAM',
            'name' => 'Malam',
            'shift_type' => ShiftType::MALAM->value,
            'start_time' => '23:00:00',
            'end_time' => '07:00:00',
            'is_overnight' => true,
        ]);

        $this->approver = User::factory()->create(['company_id' => $this->company->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $this->company->id,
                'module' => ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value,
                'name' => 'Alur Tukar Shift Test',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $this->approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    private function assignShift(User $user, ScheduleShiftType $shiftType, string $date): ScheduleShiftAssignment
    {
        return ScheduleShiftAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'shift_type_id' => $shiftType->id,
            'date' => $date,
            'off_day' => false,
        ]);
    }

    private function approve(ShiftSwapRequests $swapRequest): void
    {
        $swapRequest->load('approvalSnapshot.activity');

        app(ApprovalFlowService::class)->advanceStep(
            $swapRequest->approvalSnapshot,
            $this->approver->id,
            'Disetujui lewat automated test',
            'approved',
        );
    }

    public function test_same_date_swap_exchanges_shift_type_after_approval(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->siang, '2026-08-10');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->assertSame('in_review', $swapRequest->status);
        $this->assertSame($perawatB->id, $swapRequest->target_id);

        $this->approve($swapRequest);

        $this->assertSame('approved', $swapRequest->fresh()->status);
        // Tanggal sama -> yang ditukar shift_type_id-nya, bukan user_id (hindari bentrok unique
        // constraint (user_id, date)).
        $this->assertSame($this->siang->id, $scheduleA->fresh()->shift_type_id);
        $this->assertSame($this->pagi->id, $scheduleB->fresh()->shift_type_id);
        $this->assertSame($perawatA->id, $scheduleA->fresh()->user_id, 'Baris tetap milik pemiliknya, cuma shift-nya yang berubah.');
        $this->assertSame($perawatB->id, $scheduleB->fresh()->user_id);
    }

    public function test_different_date_swap_exchanges_owner_after_approval(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->pagi, '2026-08-15');

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);

        $this->approve($swapRequest);

        // Tanggal beda -> yang ditukar pemiliknya (user_id), tanggal & shift tetap di baris asal.
        $this->assertSame($perawatB->id, $scheduleA->fresh()->user_id);
        $this->assertSame($perawatA->id, $scheduleB->fresh()->user_id);
        $this->assertSame('2026-08-10', $scheduleA->fresh()->date->toDateString());
        $this->assertSame('2026-08-15', $scheduleB->fresh()->date->toDateString());
    }

    public function test_off_day_swap_transfers_shift_to_target_after_approval(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        $scheduleA = $this->assignShift($perawatA, $this->malam, '2026-08-10');
        // Perawat B sengaja TIDAK punya assignment di 2026-08-10 (lagi libur).

        Auth::login($perawatA);
        $swapRequest = app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'off_day',
            'req_schedule_id' => $scheduleA->id,
            'target_user_off' => $perawatB->id,
        ]);

        $this->assertNull($swapRequest->target_schedule_id);

        $this->approve($swapRequest);

        $this->assertSame($perawatB->id, $scheduleA->fresh()->user_id, 'Shift pindah ke B, A jadi libur (baris tidak dihapus).');
    }

    public function test_incompatible_shift_types_are_rejected(): void
    {
        $perawatA = User::factory()->create(['company_id' => $this->company->id]);
        $perawatB = User::factory()->create(['company_id' => $this->company->id]);

        // Pagi <-> Malam tidak boleh ditukar (cuma pagi<->siang atau malam<->malam).
        $scheduleA = $this->assignShift($perawatA, $this->pagi, '2026-08-10');
        $scheduleB = $this->assignShift($perawatB, $this->malam, '2026-08-11');

        Auth::login($perawatA);

        $this->expectException(\InvalidArgumentException::class);

        app(ShiftSwapRequestService::class)->store([
            'swap_mode' => 'schedule',
            'req_schedule_id' => $scheduleA->id,
            'target_schedule_id' => $scheduleB->id,
        ]);
    }
}
