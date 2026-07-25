<?php

namespace Tests\Feature\Presensi;

use App\Enum\AttendanceReqStatus;
use App\Enum\AttendanceStatus;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\AttendanceDashboardPage;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Dashboard Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Companies
    {
        return Companies::create(['code' => 'TST', 'name' => 'Company TST', 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)
            ->test(AttendanceDashboardPage::class)
            ->assertForbidden();
    }

    public function test_summarizes_today_attendance_and_pending_approvals(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsAbsensi($company->id);

        $present = User::factory()->create(['company_id' => $company->id]);
        $late = User::factory()->create(['company_id' => $company->id]);
        $absent = User::factory()->create(['company_id' => $company->id]);

        AttendanceRecords::create(['user_id' => $present->id, 'nip' => $present->nip, 'date' => now()->toDateString(), 'status' => AttendanceStatus::PRESENT->value]);
        AttendanceRecords::create(['user_id' => $late->id, 'nip' => $late->nip, 'date' => now()->toDateString(), 'status' => AttendanceStatus::LATE->value, 'late_minutes' => 15]);
        AttendanceRecords::create(['user_id' => $absent->id, 'nip' => $absent->nip, 'date' => now()->toDateString(), 'status' => AttendanceStatus::ABSENT->value]);

        AttendanceRequests::create(['user_id' => $present->id, 'type' => 'permission', 'status' => AttendanceReqStatus::SUBMITTED->value]);

        $component = Livewire::actingAs($hc)
            ->test(AttendanceDashboardPage::class)
            ->assertSee('Antrean Persetujuan');

        $this->assertSame(1, $component->instance()->presentTodayCount);
        $this->assertSame(1, $component->instance()->lateTodayCount);
        $this->assertSame(1, $component->instance()->absentTodayCount);
        $this->assertSame(1, $component->instance()->pendingApprovalCount);
    }
}
