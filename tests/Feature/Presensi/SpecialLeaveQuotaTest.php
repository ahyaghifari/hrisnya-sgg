<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Livewire\SubmitLeaveRequest;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\User;
use App\Models\User\UserProfiles;
use Carbon\Carbon;
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

    /** Kuota tetap di enum ($quota cuma dokumentasi angka di tes). */
    private function leaveType(LeaveTypeCategoryEnum $cat, int $quota): LeaveTypeCategoryEnum
    {
        $this->assertSame($quota, $cat->quotaDays());

        return $cat;
    }

    private function femaleUser(): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $user->id, 'gender' => 'perempuan']);
        $this->be($user);

        return $user;
    }

    /** Jadwal Senin-Jumat (Sabtu/Minggu off_day) — sama fixture WorkingDaysScheduleTest. */
    private function giveFixedScheduleSatSunOff(User $user): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Senin-Jumat',
            'schedule_type' => 'fixed',
        ]);

        foreach (range(0, 6) as $dow) {
            ScheduleFixedDay::create([
                'schedule_id' => $schedule->id,
                'day_of_week' => $dow,
                'is_off_day' => in_array($dow, [0, 6], true), // Minggu & Sabtu libur
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);
        }

        ScheduleAssignment::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);
    }

    private function form(string $typeId, string $start, ?string $end = null): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', $typeId)
            ->set('start_date', $start)
            ->set('end_date', $end ?? $start)
            ->set('attachments', [UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf')]);
    }

    public function test_maternity_leave_within_quota_succeeds(): void
    {
        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::MELAHIRKAN, 90);
        $start = now()->firstOfMonth()->addDays(2);

        $this->form($type->value, $start->toDateString(), $start->copy()->addDays(9)->toDateString())->call('submit');

        $this->assertDatabaseHas('attendance_requests', ['user_id' => $user->id, 'type' => 'leave']);
    }

    public function test_maternity_leave_over_quota_per_request_is_blocked(): void
    {
        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::MELAHIRKAN, 90);
        $start = now()->firstOfMonth();

        // 100 hari > kuota 90 -> ditolak, request tidak dibuat.
        $this->form($type->value, $start->toDateString(), $start->copy()->addDays(99)->toDateString())->call('submit');

        $this->assertSame(0, AttendanceRequests::where('user_id', $user->id)->count());
    }

    /**
     * Karyawan berjadwal Senin-Jumat: kalau dihitung hari KERJA, rentang 90 hari kalender cuma
     * ~64 hari kerja (Sabtu/Minggu dikecualikan) — total_days yang tersimpan HARUS tetap 90
     * (hari kalender, sesuai UU 13/2003 Ps.82), bukan ~64.
     */
    public function test_maternity_leave_total_days_is_calendar_not_working_days(): void
    {
        $user = $this->femaleUser();
        $this->giveFixedScheduleSatSunOff($user);
        $type = $this->leaveType(LeaveTypeCategoryEnum::MELAHIRKAN, 90);
        $monday = Carbon::parse('2026-09-07')->startOfWeek(Carbon::MONDAY);

        $this->form($type->value, $monday->toDateString(), $monday->copy()->addDays(89)->toDateString())->call('submit');

        $request = AttendanceRequests::where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('attendance_req_leave_details', [
            'request_id' => $request->id,
            'total_days' => 90,
        ]);
    }

    /**
     * Sebelum fix: kuota per-request divalidasi pakai hari KERJA, jadi karyawan berjadwal 5
     * hari/minggu bisa ajukan rentang ~125 hari kalender (kuota 90 hari kerja ≈ 125 hari
     * kalender) dan tetap lolos — padahal itu jauh melebihi hak 90 hari kalender UU. Harus
     * ditolak sekarang.
     */
    public function test_maternity_leave_calendar_days_over_quota_blocked_even_with_schedule(): void
    {
        $user = $this->femaleUser();
        $this->giveFixedScheduleSatSunOff($user);
        $type = $this->leaveType(LeaveTypeCategoryEnum::MELAHIRKAN, 90);
        $monday = Carbon::parse('2026-09-07')->startOfWeek(Carbon::MONDAY);

        // 125 hari kalender - hari kerjanya cuma ~89 (di bawah kuota 90) kalau salah hitung.
        $this->form($type->value, $monday->toDateString(), $monday->copy()->addDays(124)->toDateString())->call('submit');

        $this->assertSame(0, AttendanceRequests::where('user_id', $user->id)->count());
    }

    public function test_menstrual_leave_monthly_accumulation_is_blocked(): void
    {
        // Cuti Haid DIMATIKAN sementara dari pengajuan (instruksi user 2026-09-30) -
        // SubmitLeaveRequest::submit() menolak kategori ini duluan sebelum sempat kena cek kuota
        // di sini. Aktifkan lagi test ini begitu Cuti Haid dinyalakan kembali.
        $this->markTestSkipped('Cuti Haid dimatikan sementara dari pengajuan (instruksi user 2026-09-30).');

        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::HAID, 2);
        $monthStart = now()->firstOfMonth();

        // Pengajuan 1: 2 hari — pas kuota.
        $this->form($type->value, $monthStart->copy()->addDays(1)->toDateString(), $monthStart->copy()->addDays(2)->toDateString())->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());

        // Pengajuan 2: 1 hari lagi di bulan sama -> total 3 > 2, ditolak.
        $this->form($type->value, $monthStart->copy()->addDays(10)->toDateString())->call('submit');
        $this->assertSame(1, AttendanceRequests::where('user_id', $user->id)->count());
    }

    public function test_menstrual_leave_next_month_resets(): void
    {
        // Cuti Haid DIMATIKAN sementara dari pengajuan (instruksi user 2026-09-30) - lihat
        // catatan di test_menstrual_leave_monthly_accumulation_is_blocked().
        $this->markTestSkipped('Cuti Haid dimatikan sementara dari pengajuan (instruksi user 2026-09-30).');

        $user = $this->femaleUser();
        $type = $this->leaveType(LeaveTypeCategoryEnum::HAID, 2);
        $monthStart = now()->firstOfMonth();

        $this->form($type->value, $monthStart->copy()->addDays(1)->toDateString(), $monthStart->copy()->addDays(2)->toDateString())->call('submit');

        // Bulan depan: kuota penuh lagi.
        $next = now()->addMonthNoOverflow()->firstOfMonth();
        $this->form($type->value, $next->copy()->addDays(1)->toDateString(), $next->copy()->addDays(2)->toDateString())->call('submit');

        $this->assertSame(2, AttendanceRequests::where('user_id', $user->id)->count());
    }
}
