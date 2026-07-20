<?php

namespace Tests\Feature;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Livewire\SubmitLeaveRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batas akumulasi Cuti Tahunan per bulan kalender (setting `cuti_tahunan_max_per_bulan`,
 * module attendance, company-scoped) — cuma berlaku untuk jenis cuti berkategori
 * LeaveTypeCategoryEnum::TAHUNAN, dicek di AttendaceReqService::storeLeaveRequest() SEBELUM
 * request dibuat. Lihat planning/next rekap 2026-07-17 & knowledge/markdown/
 * analisis-payroll-5-klasifikasi-enum-hardcode-config.md §6 (Q11).
 */
class CutiTahunanMonthlyLimitTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyWithApprovalFlow(): Companies
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
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

        return $company;
    }

    private function makeCutiTahunanType(Companies $company): LeaveTypes
    {
        return LeaveTypes::create([
            'company_id' => $company->id,
            'name' => 'Cuti Tahunan',
            'category' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'quota_days' => 30,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);
    }

    private function setLimit(Companies $company, int $days): void
    {
        Setting::create([
            'company_id' => $company->id,
            'key' => 'cuti_tahunan_max_per_bulan',
            'module' => 'attendance',
            'type' => 'integer',
            'value' => (string) $days,
            'label' => 'Batas Cuti Tahunan per Bulan',
        ]);
    }

    public function test_submission_within_limit_succeeds(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeCutiTahunanType($company);
        $this->setLimit($company, 5);

        $start = now()->firstOfMonth()->addDays(3);

        $this->actingAs($user);
        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', false)
            ->set('start_date', $start->toDateString())
            ->set('end_date', $start->copy()->addDays(2)->toDateString()) // 3 hari
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }

    public function test_single_submission_exceeding_limit_is_blocked(): void
    {
        $this->markTestSkipped('ponytail: enforcement blokir submit dimatikan sementara di AttendaceReqService::storeLeaveRequest() — kuota tetap dihitung/ditampilkan, cuma tidak lagi hard block.');

        $company = $this->createCompanyWithApprovalFlow();
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeCutiTahunanType($company);
        $this->setLimit($company, 5);

        $start = now()->firstOfMonth()->addDays(3);

        $this->actingAs($user);
        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', false)
            ->set('start_date', $start->toDateString())
            ->set('end_date', $start->copy()->addDays(5)->toDateString()) // 6 hari > limit 5
            ->call('submit');

        $this->assertDatabaseMissing('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }

    public function test_accumulation_across_separate_submissions_is_blocked(): void
    {
        $this->markTestSkipped('ponytail: enforcement blokir submit dimatikan sementara di AttendaceReqService::storeLeaveRequest() — kuota tetap dihitung/ditampilkan, cuma tidak lagi hard block.');

        $company = $this->createCompanyWithApprovalFlow();
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeCutiTahunanType($company);
        $this->setLimit($company, 5);

        $monthStart = now()->firstOfMonth();

        $this->actingAs($user);

        // Pengajuan 1: 3 hari, di awal bulan yang sama — masih dalam batas.
        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', false)
            ->set('start_date', $monthStart->copy()->addDays(1)->toDateString())
            ->set('end_date', $monthStart->copy()->addDays(3)->toDateString())
            ->call('submit');

        $this->assertSame(1, \App\Models\Attendance\AttendanceRequests::where('user_id', $user->id)->count());

        // Pengajuan 2: 3 hari lagi di bulan yang sama -> total 6 hari > limit 5, harus ditolak.
        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', false)
            ->set('start_date', $monthStart->copy()->addDays(10)->toDateString())
            ->set('end_date', $monthStart->copy()->addDays(12)->toDateString())
            ->call('submit');

        // Tetap cuma 1 request (pengajuan ke-2 ditolak, tidak nambah).
        $this->assertSame(1, \App\Models\Attendance\AttendanceRequests::where('user_id', $user->id)->count());
    }

    public function test_non_tahunan_leave_type_is_not_limited(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = LeaveTypes::create([
            'company_id' => $company->id,
            'name' => 'Cuti Besar',
            'category' => null,
            'quota_days' => 90,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);
        $this->setLimit($company, 5);

        $start = now()->firstOfMonth()->addDays(1);

        $this->actingAs($user);
        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', false)
            ->set('start_date', $start->toDateString())
            ->set('end_date', $start->copy()->addDays(9)->toDateString()) // 10 hari, tetap boleh
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }

    public function test_no_limit_configured_means_unlimited(): void
    {
        $company = $this->createCompanyWithApprovalFlow();
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeCutiTahunanType($company);
        // Sengaja tidak set Setting sama sekali -> default 0 = tidak dibatasi.

        $start = now()->firstOfMonth()->addDays(1);

        $this->actingAs($user);
        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $leaveType->id)
            ->set('is_one_day', false)
            ->set('start_date', $start->toDateString())
            ->set('end_date', $start->copy()->addDays(9)->toDateString()) // 10 hari
            ->call('submit');

        $this->assertDatabaseHas('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }
}
