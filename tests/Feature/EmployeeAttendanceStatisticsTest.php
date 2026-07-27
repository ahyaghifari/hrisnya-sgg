<?php

namespace Tests\Feature;

use App\Enum\EmployeeTypeCategory;
use App\Livewire\EmployeeAttendanceStatistics;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserDocuments;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeAttendanceStatisticsTest extends TestCase
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

    // Tab Absensi/Riwayat Pengajuan/Dokumen/Penggajian di Data Diri cuma tampil kalau tipe
    // karyawan user itu SUDAH diisi (apa pun kategorinya) - lihat
    // EmployeeAttendanceStatistics::getShowExtraTabsProperty(). Test yang mengasumsikan tab itu
    // tampil harus assign tipe karyawan eksplisit di sini, user factory mentah tidak punya.
    private function assignEmployeeType(User $user, Companies $company): void
    {
        $employeeType = EmployeeTypes::create([
            'company_id' => $company->id,
            'name' => 'Tetap',
            'category' => EmployeeTypeCategory::TETAP->value,
        ]);

        UserProfiles::updateOrCreate(['user_id' => $user->id], ['employee_type_id' => $employeeType->id]);
    }

    private function createLeaveRequest(User $user, string $status = 'approved'): AttendanceRequests
    {
        $request = AttendanceRequests::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'status' => $status,
        ]);

        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Tahunan',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
        ]);

        return $request;
    }

    public function test_manager_can_view_subordinate_statistics(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'approved');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSet('status', 'approved')
            ->assertSee($staff->name)
            ->assertSee('Cuti Tahunan');
    }

    /**
     * Regresi keamanan: HandlesProfilePhotoUpload::updatedPhoto() harus abort 403 kalau
     * $record bukan diri sendiri, meski komponennya sama dan bisa dibuka manager buat lihat
     * data staf-nya (test_manager_can_view_subordinate_statistics di atas) - kalau ini tidak
     * dicek, manager yang buka Data Diri staf bisa nge-upload foto atas nama staf tsb.
     */
    public function test_manager_cannot_upload_photo_for_subordinate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'))
            ->assertForbidden();
    }

    public function test_owner_can_upload_own_photo_via_data_diri(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        \App\Models\User\UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('photo.jpg'))
            ->assertHasNoErrors();

        $this->assertNotNull($staff->fresh()->profile->fresh()->profile_picture[0] ?? null);
    }

    public function test_status_filter_excludes_non_matching_requests(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'in_review');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSet('stats', ['leave' => 0, 'permission' => 0, 'overtime' => 0]);
    }

    public function test_stranger_cannot_view_unrelated_employee_statistics(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1]);

        $stranger = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($stranger, $staffPosition);

        $other = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($other, $staffPosition);

        $this->actingAs($stranger);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $other])
            ->assertForbidden();
    }

    public function test_documents_tab_lists_only_the_records_own_documents(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $otherStaff = User::factory()->create(['company_id' => $company->id]);

        UserDocuments::create([
            'user_id' => $staff->id,
            'document_type' => 'work_certificate',
            'user_name' => $staff->name,
            'document_number' => 'SKK/001/2026',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $staff->id . '/skk.pdf',
        ]);

        UserDocuments::create([
            'user_id' => $otherStaff->id,
            'document_type' => 'work_certificate',
            'user_name' => $otherStaff->name,
            'document_number' => 'SKK/999/2026',
            'issued_date' => now()->toDateString(),
            'file_path' => 'user-documents/' . $otherStaff->id . '/skk.pdf',
        ]);

        $this->assignEmployeeType($staff, $company);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertSee('SKK/001/2026')
            ->assertDontSee('SKK/999/2026');
    }

    public function test_export_returns_download_response(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->createLeaveRequest($staff, 'approved');

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->call('export')
            ->assertFileDownloaded();
    }

    private function makePayroll(User $user, bool $isPaid, array $periodOverrides = []): \App\Models\Payroll\Payroll
    {
        $period = \App\Models\Payroll\PayrollPeriod::create(array_merge([
            'company_id' => $user->company_id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ], $periodOverrides));

        return \App\Models\Payroll\Payroll::create([
            'user_id' => $user->id,
            'payroll_period_id' => $period->id,
            'gross_salary' => 5000000,
            'total_deductions' => 500000,
            'net_salary' => 4500000,
            'is_paid' => $isPaid,
        ]);
    }

    /**
     * Regresi: Livewire::test(EmployeeAttendanceStatistics::class) TANPA param 'record' balik
     * 403 (quirk khusus test harness Livewire, sudah ditemukan sebelumnya di komponen lain
     * sesi ini) — tapi rute HTTP /data-diri yang beneran (tanpa segment {record}) harus tetap
     * jalan normal di request asli, karena mount() dipanggil tanpa argumen sama sekali, bukan
     * lewat resolusi implicit-binding yang bikin quirk itu muncul di test harness.
     */
    public function test_data_diri_route_loads_via_real_http_request(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->get(route('data-diri'))
            ->assertOk()
            ->assertSee('Data Diri');
    }

    public function test_payroll_tab_shows_only_paid_history_for_self(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $paid = $this->makePayroll($user, true);
        $this->makePayroll($user, false);

        $this->assignEmployeeType($user, $company);

        $this->actingAs($user);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $user])
            ->assertSee('Penggajian')
            ->assertSee('Rp4.500.000')
            ->assertSee(route('data-diri.penggajian.show', $paid->id), false);
    }

    public function test_payroll_tab_hidden_when_viewing_subordinate(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($manager, $managerPosition);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $this->assignJobPosition($staff, $staffPosition);

        $this->makePayroll($staff, true);

        $this->actingAs($manager);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $staff])
            ->assertDontSee('Penggajian');
    }

    public function test_extra_tabs_hidden_when_employee_type_not_set(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $user])
            ->assertSee('Presensi')
            ->assertDontSee('Presensi & Absensi')
            ->assertDontSee('Riwayat Pengajuan')
            ->assertDontSee('Dokumen')
            ->assertDontSee('Penggajian');
    }

    #[DataProvider('employeeTypeCategoryProvider')]
    public function test_extra_tabs_visible_for_any_employee_type_category(EmployeeTypeCategory $category): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $employeeType = EmployeeTypes::create([
            'company_id' => $company->id,
            'name' => $category->getLabel(),
            'category' => $category->value,
        ]);
        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $employeeType->id]);

        $this->actingAs($user);

        Livewire::test(EmployeeAttendanceStatistics::class, ['record' => $user])
            ->assertSee('Presensi & Absensi')
            ->assertSee('Riwayat Pengajuan')
            ->assertSee('Dokumen');
    }

    public static function employeeTypeCategoryProvider(): array
    {
        return [
            'tetap' => [EmployeeTypeCategory::TETAP],
            'kontrak' => [EmployeeTypeCategory::KONTRAK],
            'orientasi' => [EmployeeTypeCategory::ORIENTASI],
        ];
    }
}
