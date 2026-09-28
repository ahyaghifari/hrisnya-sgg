<?php

namespace Tests\Feature;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Livewire\PendingApprovalsCard;
use App\Livewire\Presensi\ScheduleShiftRosterTab;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleRosterChangeRequest;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integrasi modul schedule_roster_change ke widget "Butuh Persetujuan Anda" (2026-09-28) - baris
 * muncul di kartu ini (approver TAHU ada yang perlu ditinjau + bisa approve massal langsung dari
 * sini), tapi tinjauan detailnya (lihat sel draft-nya) di halaman Detail Jadwal sendiri, BUKAN
 * modal di kartu ini (beda dari Pelatihan) - lihat url-nya di PendingApprovalsCard::buildRequests()
 * dan ScheduleShiftRosterTab::getCanReviewRosterChangeProperty(). Lihat ScheduleRosterChangeApprovalTest
 * untuk alur backend-nya, ScheduleShiftRosterTabTest untuk tinjauan di grid.
 */
class PendingApprovalsCardScheduleRosterChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_roster_change_shows_up_and_links_to_the_schedule_detail_page(): void
    {
        $company = Companies::create(['code' => 'PARC', 'name' => 'RS PendingApprovals Roster Test', 'location' => 'Test', 'is_active' => true]);

        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Karu Pending Card Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule']);

        $karu = User::factory()->create(['company_id' => $company->id]);
        $karu->assignRole($role);
        $approver = User::factory()->create(['company_id' => $company->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $company->id,
                'module' => ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value,
                'name' => 'Alur Perubahan Jadwal Shift Test',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => ScheduleType::SHIFT->value, 'use_template' => false]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();

        $component = Livewire::actingAs($approver)->test(PendingApprovalsCard::class);
        $component->assertSee('Jadwal Shift');
        $component->assertSee($karu->name);
        // Tinjau di grid Detail Penjadwalan sendiri, bukan modal - link-nya langsung ke tab itu.
        $component->assertSee(route('presensi.jadwal.show', ['schedule' => $schedule->id, 'tab' => 'penjadwalan']), false);
    }

    public function test_bulk_approve_also_works_for_roster_change(): void
    {
        $company = Companies::create(['code' => 'PARB', 'name' => 'RS PendingApprovals Roster Bulk Test', 'location' => 'Test', 'is_active' => true]);

        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ShiftScheduleAssignments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Karu Pending Card Bulk Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'Update:ShiftScheduleAssignments', 'View:Schedule']);

        $karu = User::factory()->create(['company_id' => $company->id]);
        $karu->assignRole($role);
        $approver = User::factory()->create(['company_id' => $company->id]);

        MasterApprovalFlowSteps::create([
            'flow_id' => MasterApprovalFlows::create([
                'company_id' => $company->id,
                'module' => ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value,
                'name' => 'Alur Perubahan Jadwal Shift Test',
                'flow_type' => 'default',
                'is_active' => true,
            ])->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);

        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Perawat', 'schedule_type' => ScheduleType::SHIFT->value, 'use_template' => false]);
        $shiftType = ScheduleShiftType::create(['schedule_id' => $schedule->id, 'code' => 'P', 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($karu)
            ->test(ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->call('startEdit')
            ->set("draft.{$employee->id}.{$date}", $shiftType->id)
            ->call('saveRoster');

        $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)->firstOrFail();

        Livewire::actingAs($approver)->test(PendingApprovalsCard::class)
            ->set('selected', ['schedule_roster_change:' . $request->id])
            ->call('confirmBulkApprove');

        $this->assertSame('approved', $request->fresh()->status);
    }
}
