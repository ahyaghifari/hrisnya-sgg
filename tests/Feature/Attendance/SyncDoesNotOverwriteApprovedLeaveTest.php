<?php

namespace Tests\Feature\Attendance;

use App\Enum\AttendanceStatus;
use App\Models\Attendance\AttendanceRecords;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Baris attendance_records yang statusnya sudah Cuti/Sakit/Izin (disetujui) tidak boleh ketimpa
 * hasil sync device (scan nyasar/keliru tetap mungkin ada meski karyawannya lagi cuti) - instruksi
 * user 2026-09-27. Lihat AttendanceRecordRepository::saveCalculatedResult().
 */
class SyncDoesNotOverwriteApprovedLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function makeCalculatedResult(User $user, string $date): array
    {
        return [
            'user' => $user,
            'date' => $date,
            'check_in' => '08:00:00',
            'check_out' => '17:00:00',
            'working_minutes' => 480,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'work_day_weight' => 1,
            'status' => AttendanceStatus::PRESENT->value,
        ];
    }

    public function test_sync_does_not_overwrite_leave_status(): void
    {
        $company = Companies::create(['code' => 'SYN1', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'EMP-SYN1']);
        $date = now()->toDateString();

        AttendanceRecords::create([
            'user_id' => $user->id,
            'nip' => $user->nip,
            'date' => $date,
            'status' => AttendanceStatus::LEAVE->value,
            'source' => 'leave_approved',
        ]);

        app(AttendanceRecordRepository::class)->saveCalculatedResult($this->makeCalculatedResult($user, $date), 'access_logs_fixed');

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date)->first();
        $this->assertSame(AttendanceStatus::LEAVE->value, $record->status);
        $this->assertNull($record->check_in_time);
    }

    public function test_sync_still_writes_normally_when_no_leave_status(): void
    {
        $company = Companies::create(['code' => 'SYN2', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'nip' => 'EMP-SYN2']);
        $date = now()->toDateString();

        app(AttendanceRecordRepository::class)->saveCalculatedResult($this->makeCalculatedResult($user, $date), 'access_logs_fixed');

        $record = AttendanceRecords::where('nip', $user->nip)->where('date', $date)->first();
        $this->assertSame(AttendanceStatus::PRESENT->value, $record->status);
        $this->assertSame('08:00:00', $record->check_in_time);
    }
}
