<?php

namespace Tests\Feature;

use App\Livewire\MyAttendanceRequests;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceRequestListTest extends TestCase
{
    use RefreshDatabase;

    private function assignJobPosition(User $user, JobPositions $jobPosition): void
    {
        UserJobPositions::create([
            'user_id' => $user->id,
            'job_position_id' => $jobPosition->id,
            'start_date' => now()->subYear(),
            'status' => 'active',
        ]);
    }

    private function createLeaveRequest(User $user, string $leaveType = 'Cuti Tahunan'): AttendanceRequests
    {
        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => 'in_review',
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => $leaveType,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
        ]);

        return $request;
    }

    public function test_employee_sees_only_own_history(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($employee, $staffPosition);

        $this->createLeaveRequest($employee, 'Cuti Tahunan');

        $this->actingAs($employee);

        Livewire::test(MyAttendanceRequests::class)
            ->assertSee('Cuti')
            ->assertSee('Cuti Tahunan')
            ->assertDontSee('Nama Karyawan');
    }

    public function test_manager_does_not_see_subordinate_requests_on_own_history_page(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'Cuti Sakit');

        $this->actingAs($manager);

        Livewire::test(MyAttendanceRequests::class)
            ->assertDontSee($staff->name)
            ->assertDontSee('Cuti Sakit');
    }

    /**
     * Regresi: pagination di halaman ini pakai wire:navigate (full page-visit), yang bikin
     * komponen di-mount ULANG dari URL — tanpa #[Url] filter `type` hilang begitu pindah
     * halaman. Test GET langsung (bukan Livewire::test) biar simulasi mount dari URL yang
     * sebenarnya kejadian saat wire:navigate.
     */
    public function test_type_filter_is_read_from_url_on_page_load(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($employee, $staffPosition);

        // PENGAJUAN_ABSENSI_SAYA menggerbangi halaman ini (config/portal-pages.php) — Livewire::test()
        // di test lain di file ini bypass middleware, tapi GET biasa (dibutuhkan buat simulasi
        // wire:navigate) lewat middleware sungguhan jadi permission-nya harus eksplisit.
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value, 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Employee Attendance List Test', 'guard_name' => 'web']);
        $role->givePermissionTo(\App\Enum\CustomPermissionEnum::PENGAJUAN_ABSENSI_SAYA->value);
        $employee->assignRole($role);

        for ($i = 1; $i <= 15; $i++) {
            $request = AttendanceRequests::create([
                'user_id' => $employee->id,
                'type' => 'leave',
                'status' => 'in_review',
            ]);
            AttendanceRequestLeaveDetails::create([
                'request_id' => $request->id,
                'leave_type' => "Cuti Tahunan {$i}",
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'total_days' => 1,
            ]);
        }

        $overtime = AttendanceRequests::create([
            'user_id' => $employee->id,
            'type' => 'overtime',
            'status' => 'in_review',
        ]);
        \App\Models\Attendance\AttendanceRequestOvertimeDetails::create([
            'request_id' => $overtime->id,
            'overtime_type' => 'Lembur Marker',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ]);

        $response = $this->actingAs($employee)->get(route('my-attendance-requests', ['type' => 'leave', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('Cuti Tahunan 11');
        $response->assertDontSee('Lembur Marker');
    }
}
