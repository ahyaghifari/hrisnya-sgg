<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Livewire\SubmitLeaveRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kuota cuti khusus (LeaveTypeCategoryEnum::usageWindow) diblokir di
 * AttendaceReqService::assertWithinSpecialLeaveQuota() SEBELUM request dibuat:
 * - HAID           : 'monthly'     — akumulasi hari per bulan kalender <= quota_days
 * - MELAHIRKAN/KEGUGURAN : 'per_request' — total_days 1 pengajuan <= quota_days
 */
class SpecialLeaveQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $this->company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $this->company->id,
            'module' => 'attendance_request',
            'name' => 'Alur Test',
            'flow_type' => 'default',
            'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    private function leaveType(LeaveTypeCategoryEnum $cat, int $quota): LeaveTypes
    {
        return LeaveTypes::create([
            'company_id' => $this->company->id,
            'name' => $cat->label(),
            'category' => $cat->value,
            'quota_days' => $quota,
            'reset_period' => $cat === LeaveTypeCategoryEnum::HAID ? 'monthly' : 'none',
            'is_paid' => true,
        ]);
    }

    private function femaleUser(): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $user->id, 'gender' => 'perempuan']);
        $this->be($user);

        return $user;
    }

    private function form(string $typeId, string $start, ?string $end = null): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $typeId)
            ->set('start_date', $start)
            ->set('end_date', $end ?? $start)
            ->set('attachments', [UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf')]);
    }

    public function test_maternity_leave_within_quota_succeeds(): void
    {
        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::MELAHIRKAN, 90);
        $start = now()->firstOfMonth()->addDays(2);

        $this->form($type->id, $start->toDateString(), $start->copy()->addDays(9)->toDateString())->call('submit');

        $this->assertDatabaseHas('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }

    public function test_maternity_leave_over_quota_per_request_is_blocked(): void
    {
        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::MELAHIRKAN, 90);
        $start = now()->firstOfMonth();

        // 100 hari > kuota 90 -> ditolak, request tidak dibuat.
        $this->form($type->id, $start->toDateString(), $start->copy()->addDays(99)->toDateString())->call('submit');

        $this->assertSame(0, AttendanceRequests::where('user_id', $user->id)->count());
    }

    public function test_menstrual_leave_monthly_accumulation_is_blocked(): void
    {
        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::HAID, 2);
        $monthStart = now()->firstOfMonth();

        // Pengajuan 1: 2 hari — pas kuota.
        $this->form($type->id, $monthStart->copy()->addDays(1)->toDateString(), $monthStart->copy()->addDays(2)->toDateString())->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());

        // Pengajuan 2: 1 hari lagi di bulan sama -> total 3 > 2, ditolak.
        $this->form($type->id, $monthStart->copy()->addDays(10)->toDateString())->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());
    }

    public function test_menstrual_leave_next_month_resets(): void
    {
        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::HAID, 2);
        $monthStart = now()->firstOfMonth();

        $this->form($type->id, $monthStart->copy()->addDays(1)->toDateString(), $monthStart->copy()->addDays(2)->toDateString())->call('submit');

        // Bulan depan: kuota penuh lagi.
        $next = now()->addMonthNoOverflow()->firstOfMonth();
        $this->form($type->id, $next->copy()->addDays(1)->toDateString(), $next->copy()->addDays(2)->toDateString())->call('submit');

        $this->assertSame(2, AttendanceRequests::where('user_id', $user->id)->count());
    }
}
