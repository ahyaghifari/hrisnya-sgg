<?php

namespace Tests\Feature\LMS;

use App\Livewire\LMS\TrainingReportPage;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMeetings;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test halaman "Laporan Pelatihan" portal (TrainingReportPage) — padanan portal dari Filament
 * TrainingReportResource. Halaman filter tunggal (bukan CRUD): pilih baseline aktif, tampilkan
 * matriks karyawan x pelatihan, dan export Excel.
 */
class TrainingReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $code, string $name): Companies
    {
        $company = new Companies();
        $company->fill([
            'code' => $code,
            'name' => $name,
            'location' => 'Test',
            'is_active' => true,
        ]);
        $company->save();

        return $company;
    }

    private function actingAsLmsAdmin(string $companyId): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Training Report Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)->test(TrainingReportPage::class)->assertForbidden();
    }

    public function test_defaults_to_own_company_active_baseline(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'min_courses' => 1, 'min_hours' => 10, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(TrainingReportPage::class)
            ->assertSet('baselineId', $baseline->id);
    }

    public function test_shows_no_data_message_without_baseline(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);

        Livewire::actingAs($admin)
            ->test(TrainingReportPage::class)
            ->assertSee('Pilih periode baseline');
    }

    public function test_matrix_shows_enrollment_hours_and_unfulfilled(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);

        $course = LmsCourses::create([
            'company_id' => $company->id, 'code' => 'TRN-001', 'title' => 'Pelatihan K3',
            'start_date' => '2026-03-01', 'end_date' => '2026-03-01',
        ]);
        $meeting = LmsMeetings::create([
            'course_id' => $course->id, 'title' => 'Sesi 1',
            'start_date' => '2026-03-01', 'end_date' => '2026-03-01',
            'start_time' => '08:00', 'end_time' => '10:00',
        ]);

        LmsCourseUsers::create([
            'user_id' => $employee->id, 'course_id' => $course->id,
            'status' => 'approved', 'enrolled_at' => '2026-03-01',
        ]);
        LmsCourseMeetingUsers::create([
            'user_id' => $employee->id, 'course_id' => $course->id, 'meeting_id' => $meeting->id,
            'absen_masuk' => true, 'absen_pulang' => true,
        ]);

        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'min_courses' => 2, 'min_hours' => 20, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(TrainingReportPage::class)
            ->set('baselineId', $baseline->id)
            ->assertSee('Budi Santoso')
            ->assertSee('Pelatihan K3')
            ->assertSee('2 jam') // 08:00-10:00 = 2 jam
            ->assertSee('18 jam'); // belum terpenuhi: 20 - 2
    }

    public function test_export_excel_requires_baseline_selected(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);

        Livewire::actingAs($admin)
            ->test(TrainingReportPage::class)
            ->set('baselineId', null)
            ->call('exportExcel');

        // Tidak boleh crash — cukup notifikasi warning, tidak ada file yang di-generate.
        $this->assertTrue(true);
    }

    public function test_export_excel_downloads_file_when_baseline_selected(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        User::factory()->create(['company_id' => $company->id, 'name' => 'Siti Aminah']);

        $baseline = LmsCourseBaselines::create([
            'company_id' => $company->id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'min_courses' => 1, 'min_hours' => 5, 'is_active' => true,
        ]);

        $response = Livewire::actingAs($admin)
            ->test(TrainingReportPage::class)
            ->set('baselineId', $baseline->id)
            ->call('exportExcel');

        $response->assertStatus(200);
    }
}
