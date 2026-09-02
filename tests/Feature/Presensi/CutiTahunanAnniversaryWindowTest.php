<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Repositories\Attendance\LeaveReqRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Periode Cuti Tahunan = jendela 1 tahun DARI ULANG TAHUN BERGABUNG (user_profiles.joining_date),
 * bukan kalender Jan–Des. Tanggal acuan = start_date pengajuan. 29 Feb non-kabisat → 28 Feb.
 * joining_date kosong → fallback tahun kalender. Lihat LeaveReqRepository::getLeaveUserBalance().
 */
class CutiTahunanAnniversaryWindowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?string $joiningDate): User
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserProfiles::create(['user_id' => $user->id, 'joining_date' => $joiningDate]);

        return $user;
    }

    private function makeTahunanType(User $user): LeaveTypes
    {
        return LeaveTypes::create([
            'company_id' => $user->company_id,
            'name' => 'Cuti Tahunan',
            'category' => LeaveTypeCategoryEnum::TAHUNAN->value,
            'quota_days' => 12,
            'reset_period' => 'yearly',
            'is_paid' => true,
        ]);
    }

    private function approvedLeave(User $user, LeaveTypes $type, string $startDate, int $totalDays): void
    {
        $req = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'leave', 'status' => 'approved']);
        AttendanceRequestLeaveDetails::create([
            'request_id' => $req->id,
            'leave_type_id' => $type->id,
            'leave_type' => $type->name,
            'category' => $type->category,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'total_days' => $totalDays,
        ]);
    }

    private function balance(User $user, LeaveTypes $type, string $ref): array
    {
        return app(LeaveReqRepository::class)->getLeaveUserBalance($user->id, $type->id, $ref);
    }

    public function test_window_is_current_anniversary_year_when_anniversary_already_passed(): void
    {
        $user = $this->makeUser('2019-05-05');
        $type = $this->makeTahunanType($user);

        // Dalam jendela 5 Mei 2026 – 4 Mei 2027
        $this->approvedLeave($user, $type, '2026-06-01', 3);
        // Di luar (jendela sebelumnya)
        $this->approvedLeave($user, $type, '2026-04-30', 5);

        $b = $this->balance($user, $type, '2026-09-03');

        $this->assertSame(3, $b['used']);
        $this->assertSame(9, $b['available']); // 12 - 3
    }

    public function test_window_is_previous_anniversary_year_when_anniversary_not_yet_reached(): void
    {
        $user = $this->makeUser('2019-05-05');
        $type = $this->makeTahunanType($user);

        // acuan 3 Maret 2026 → jendela 5 Mei 2025 – 4 Mei 2026
        $this->approvedLeave($user, $type, '2025-12-20', 4); // dalam jendela
        $this->approvedLeave($user, $type, '2026-06-01', 3); // di luar (jendela berikutnya)

        $b = $this->balance($user, $type, '2026-03-03');

        $this->assertSame(4, $b['used']);
    }

    public function test_leap_day_joining_clamps_to_28_feb_in_non_leap_year(): void
    {
        $user = $this->makeUser('2016-02-29');
        $type = $this->makeTahunanType($user);

        // 2026 bukan kabisat → anchor 28 Feb 2026. Cuti 28 Feb 2026 harus MASUK jendela berjalan.
        $this->approvedLeave($user, $type, '2026-02-28', 2);
        // 27 Feb 2026 → jendela sebelumnya (28 Feb 2025 – 27 Feb 2026)
        $this->approvedLeave($user, $type, '2026-02-27', 6);

        $b = $this->balance($user, $type, '2026-06-01');

        $this->assertSame(2, $b['used']);
    }

    public function test_null_joining_date_falls_back_to_calendar_year(): void
    {
        $user = $this->makeUser(null);
        $type = $this->makeTahunanType($user);

        $this->approvedLeave($user, $type, '2026-01-10', 3);
        $this->approvedLeave($user, $type, '2026-12-20', 2);
        $this->approvedLeave($user, $type, '2025-12-31', 9); // tahun lalu, di luar

        $b = $this->balance($user, $type, '2026-09-03');

        $this->assertSame(5, $b['used']); // 3 + 2
    }
}
