<?php

namespace Tests\Feature;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Livewire\SubmitLeaveRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jendela pengajuan Cuti Tahunan (instruksi user 2026-09-05): masa kerja minimal
 * (AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN) menentukan tanggal ELIGIBLE, tapi form
 * boleh diisi 1 BULAN lebih awal dari itu - tanggal MULAI cuti yang dipilih tetap wajib >=
 * tanggal eligible. Contoh: minimal 12 bulan, gabung 5 Mei -> eligible 5 Mei tahun depan,
 * form baru boleh diisi mulai 5 April tahun depan. Lihat
 * AttendaceReqService::cutiTahunanEligibleDate()/assertCutiTahunanEligibility().
 */
class CutiTahunanEligibilityWindowTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithApprovalFlow(): Companies
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $approver = User::factory()->create(['company_id' => $company->id]);

        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id, 'module' => 'attendance_request',
            'name' => 'Alur Test', 'flow_type' => 'default', 'is_active' => true,
        ]);
        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id, 'step_order' => 1, 'approver_type' => 'user',
            'approver_ref_id' => $approver->id, 'approval_mode' => 'any',
            'scope_type' => 'global', 'is_final' => true,
        ]);

        return $company;
    }

    private function userJoinedOn(Companies $company, string $joiningDate): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'joining_date' => $joiningDate]);

        return $user;
    }

    private function cutiTahunanType(Companies $company): LeaveTypes
    {
        return LeaveTypes::create([
            'company_id' => $company->id, 'name' => 'Cuti Tahunan',
            'category' => LeaveTypeCategoryEnum::TAHUNAN->value, 'quota_days' => 12,
            'reset_period' => 'yearly', 'is_paid' => true,
        ]);
    }

    private function setMinMasaKerjaBulan(Companies $company, int $months): void
    {
        Setting::create([
            'company_id' => $company->id, 'key' => AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN->value,
            'module' => 'attendance', 'type' => 'integer', 'value' => (string) $months,
            'label' => AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN->label(),
        ]);
    }

    /** Gabung 5 Mei 2025, minimal 12 bulan -> eligible 5 Mei 2026, jendela buka 5 April 2026. */
    public function test_dropdown_hides_cuti_tahunan_before_window_opens(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 12);
        $type = $this->cutiTahunanType($company);
        $user = $this->userJoinedOn($company, '2025-05-05');
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-03-01'); // sebelum jendela (5 April 2026)

        $types = Livewire::test(SubmitLeaveRequest::class)->instance()->leaveTypes;

        $this->assertFalse($types->contains('id', $type->id));

        \Carbon\Carbon::setTestNow();
    }

    public function test_dropdown_shows_cuti_tahunan_once_window_opens(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 12);
        $type = $this->cutiTahunanType($company);
        $user = $this->userJoinedOn($company, '2025-05-05');
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-04-10'); // dalam jendela (mulai 5 April 2026)

        $types = Livewire::test(SubmitLeaveRequest::class)->instance()->leaveTypes;

        $this->assertTrue($types->contains('id', $type->id));

        \Carbon\Carbon::setTestNow();
    }

    public function test_submit_rejected_before_window_opens_even_via_direct_call(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 12);
        $type = $this->cutiTahunanType($company);
        $user = $this->userJoinedOn($company, '2025-05-05');
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-03-01');

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $type->id)
            ->set('start_date', '2026-05-05')
            ->set('end_date', '2026-05-05')
            ->call('submit');

        $this->assertSame(0, AttendanceRequests::where('user_id', $user->id)->count());

        \Carbon\Carbon::setTestNow();
    }

    /** Sudah dalam jendela (5 April 2026+), tapi tanggal MULAI cuti < tanggal eligible (5 Mei
     *  2026) - tetap ditolak. */
    public function test_submit_rejected_when_start_date_before_eligible_date(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 12);
        $type = $this->cutiTahunanType($company);
        $user = $this->userJoinedOn($company, '2025-05-05');
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-04-10');

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $type->id)
            ->set('start_date', '2026-04-20') // < 5 Mei 2026
            ->set('end_date', '2026-04-20')
            ->call('submit');

        $this->assertSame(0, AttendanceRequests::where('user_id', $user->id)->count());

        \Carbon\Carbon::setTestNow();
    }

    /** Dalam jendela, tanggal mulai PAS di tanggal eligible - harus lolos. */
    public function test_submit_succeeds_when_start_date_on_eligible_date(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 12);
        $type = $this->cutiTahunanType($company);
        $user = $this->userJoinedOn($company, '2025-05-05');
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-04-10');

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $type->id)
            ->set('start_date', '2026-05-05')
            ->set('end_date', '2026-05-05')
            ->call('submit');

        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());

        \Carbon\Carbon::setTestNow();
    }

    /** Setting 0/tidak diset - tidak ada batasan sama sekali, dropdown & submit normal. */
    public function test_no_restriction_when_min_masa_kerja_not_configured(): void
    {
        $company = $this->companyWithApprovalFlow();
        $type = $this->cutiTahunanType($company);
        $user = $this->userJoinedOn($company, '2026-08-01'); // baru gabung kemarin
        $this->be($user);

        $types = Livewire::test(SubmitLeaveRequest::class)->instance()->leaveTypes;
        $this->assertTrue($types->contains('id', $type->id));

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $type->id)
            ->set('start_date', now()->addDay()->toDateString())
            ->set('end_date', now()->addDay()->toDateString())
            ->call('submit');

        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());
    }

    private function cutiBiasaType(Companies $company): LeaveTypes
    {
        return LeaveTypes::create([
            'company_id' => $company->id, 'name' => 'Cuti Biasa',
            'category' => null, 'quota_days' => 12,
            'reset_period' => 'yearly', 'is_paid' => true,
        ]);
    }

    /** Masa kerja minimal (instruksi user 2026-09-15) berlaku ke jenis cuti SELAIN Tahunan
     *  juga - disembunyikan dari dropdown & ditolak kalau di-submit langsung, tidak cuma
     *  warning. Beda dari Tahunan yang punya jendela "boleh diisi 1 bulan lebih awal". */
    public function test_non_tahunan_leave_type_hidden_and_rejected_before_tenure_met(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 12);
        $type = $this->cutiBiasaType($company);
        $user = $this->userJoinedOn($company, '2026-01-01'); // baru 2 bulan
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-03-01');

        $types = Livewire::test(SubmitLeaveRequest::class)->instance()->leaveTypes;
        $this->assertFalse($types->contains('id', $type->id));

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $type->id)
            ->set('start_date', '2026-03-05')
            ->set('end_date', '2026-03-05')
            ->call('submit');

        $this->assertSame(0, AttendanceRequests::where('user_id', $user->id)->count());

        \Carbon\Carbon::setTestNow();
    }

    /** Sekali masa kerja minimal terpenuhi, jenis cuti selain Tahunan normal lagi. */
    public function test_non_tahunan_leave_type_shown_and_allowed_once_tenure_met(): void
    {
        $company = $this->companyWithApprovalFlow();
        $this->setMinMasaKerjaBulan($company, 1);
        $type = $this->cutiBiasaType($company);
        $user = $this->userJoinedOn($company, '2026-01-01');
        $this->be($user);

        \Carbon\Carbon::setTestNow('2026-03-01'); // 2 bulan kerja >= minimal 1 bulan

        $types = Livewire::test(SubmitLeaveRequest::class)->instance()->leaveTypes;
        $this->assertTrue($types->contains('id', $type->id));

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $type->id)
            ->set('start_date', '2026-03-05')
            ->set('end_date', '2026-03-05')
            ->call('submit');

        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());

        \Carbon\Carbon::setTestNow();
    }
}
