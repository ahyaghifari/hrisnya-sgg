<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Repositories\Attendance\LeaveReqRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sisa Cuti Tahunan = quota - used - hari cuti bersama tahun berjalan (lihat
 * planning/2026-07-16-master-hari-libur-cuti-bersama.md). Deduksi cuti bersama cuma berlaku
 * untuk leave type "tahunan" — tipe cuti lain (izin, melahirkan, dst) tidak boleh ikut terkurangi.
 */
class LeaveBalanceCollectiveLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $code): Companies
    {
        return Companies::create([
            'code' => $code,
            'name' => "Company {$code}",
            'location' => 'Test',
            'is_active' => true,
        ]);
    }

    private function makeLeaveType(Companies $company, string $name, int $quota = 12): LeaveTypes
    {
        return LeaveTypes::create([
            'company_id' => $company->id,
            'name' => $name,
            'quota_days' => $quota,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);
    }

    public function test_collective_leave_days_reduce_available_cuti_tahunan_balance(): void
    {
        $company = $this->makeCompany('TST');
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company, 'Cuti Tahunan', 12);


        // "used" dihitung live dari request APPROVED dalam periode — bukan lagi baca kolom
        // LeaveUserBalances::used. Lihat LeaveReqRepository::getLeaveUserBalance().
        $approvedRequest = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'approved',
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $approvedRequest->id,
            'leave_type_id' => $leaveType->id,
            'leave_type' => $leaveType->name,
            'start_date' => now()->startOfYear()->addDays(1)->toDateString(),
            'end_date' => now()->startOfYear()->addDays(2)->toDateString(),
            'total_days' => 2,
        ]);

        // 2 hari cuti bersama tahun ini — hari libur/cuti bersama berlaku global buat semua company.
        Holidays::create(['date' => now()->startOfYear()->addDays(10), 'name' => 'Cuti Bersama 1', 'is_collective_leave' => true]);
        Holidays::create(['date' => now()->startOfYear()->addDays(20), 'name' => 'Cuti Bersama 2', 'is_collective_leave' => true]);

        $balance = (new LeaveReqRepository())->getLeaveUserBalance($user->id, $leaveType->id);

        // 12 - 2 (used) - 2 (cuti bersama) = 8
        $this->assertSame(12, $balance['quota']);
        $this->assertSame(2, $balance['used']);
        $this->assertSame(2, $balance['collective_leave_days']);
        $this->assertSame(8, $balance['available']);
    }

    public function test_holiday_without_collective_leave_flag_does_not_reduce_balance(): void
    {
        $company = $this->makeCompany('TSC');
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company, 'Cuti Tahunan', 12);

        Holidays::create(['date' => now()->startOfYear()->addDays(5), 'name' => 'Libur Nasional Biasa', 'is_collective_leave' => false]);

        $balance = (new LeaveReqRepository())->getLeaveUserBalance($user->id, $leaveType->id);

        $this->assertSame(0, $balance['collective_leave_days']);
        $this->assertSame(12, $balance['available']);
    }

    public function test_collective_leave_does_not_affect_non_tahunan_leave_type(): void
    {
        $company = $this->makeCompany('TSD');
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company, 'Cuti Melahirkan', 90);

        Holidays::create(['date' => now()->startOfYear()->addDays(10), 'name' => 'Cuti Bersama Global', 'is_collective_leave' => true]);

        $balance = (new LeaveReqRepository())->getLeaveUserBalance($user->id, $leaveType->id);

        $this->assertSame(0, $balance['collective_leave_days']);
        $this->assertSame(90, $balance['available']);
    }

    public function test_collective_leave_outside_current_year_period_is_not_counted(): void
    {
        $company = $this->makeCompany('TSE');
        $user = User::factory()->create(['company_id' => $company->id]);
        $leaveType = $this->makeLeaveType($company, 'Cuti Tahunan', 12);

        Holidays::create(['date' => now()->subYear()->startOfYear()->addDays(10), 'name' => 'Cuti Bersama Tahun Lalu', 'is_collective_leave' => true]);

        $balance = (new LeaveReqRepository())->getLeaveUserBalance($user->id, $leaveType->id);

        $this->assertSame(0, $balance['collective_leave_days']);
        $this->assertSame(12, $balance['available']);
    }
}
