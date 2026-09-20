<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\ExtraShiftRecapPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rekap Extra Shift butuh DUA permission: VIEW_REPORT_DATA_ABSENSI + ViewAny:ExtraShiftAssignment.
 * Permission dibuat eksplisit di sini (bukan diasumsikan sudah ada di DB test).
 */
class ExtraShiftRecapPageTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private ScheduleShiftType $pagi;
    private ScheduleShiftType $siang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'REK', 'name' => 'Rekap Co', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $this->company->id, 'name' => 'Shift RS', 'schedule_type' => 'shift']);

        $this->pagi = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'PAGI', 'name' => 'Pagi', 'shift_type' => 'pagi',
            'start_time' => '07:00:00', 'end_time' => '14:00:00', 'counted_work_days' => 1,
        ]);
        $this->siang = ScheduleShiftType::create([
            'schedule_id' => $schedule->id, 'code' => 'SIANG', 'name' => 'Siang', 'shift_type' => 'siang',
            'start_time' => '14:00:00', 'end_time' => '21:00:00', 'counted_work_days' => 1,
        ]);
    }

    /** @param  list<string>  $permissions */
    private function actingUser(array $permissions, ?string $companyId = null): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Rekap ' . implode('|', $permissions), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create(['company_id' => $companyId ?? $this->company->id]);
        $user->assignRole($role);

        return $user;
    }

    private function pengelola(): User
    {
        return $this->actingUser([CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value, 'ViewAny:ExtraShiftAssignment']);
    }

    private function extraShift(User $user, string $date, ScheduleShiftType $type, string $category = 'extra_shift', string $status = 'approved'): ExtraShiftAssignment
    {
        return ExtraShiftAssignment::create([
            'company_id' => $user->company_id, 'user_id' => $user->id, 'shift_type_id' => $type->id, 'date' => $date,
            'status' => $status, 'source' => 'assigned', 'category' => $category, 'created_by' => $user->id,
        ]);
    }

    private function page(User $viewer, string $from = '2026-07-01', string $to = '2026-07-31')
    {
        return Livewire::actingAs($viewer)->test(ExtraShiftRecapPage::class)
            ->set('date_from', $from)
            ->set('date_to', $to);
    }

    public function test_forbidden_without_any_permission(): void
    {
        Livewire::actingAs(User::factory()->create(['company_id' => $this->company->id]))
            ->test(ExtraShiftRecapPage::class)
            ->assertForbidden();
    }

    public function test_forbidden_with_only_rekap_permission(): void
    {
        $viewer = $this->actingUser([CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value]);

        Livewire::actingAs($viewer)->test(ExtraShiftRecapPage::class)->assertForbidden();
    }

    public function test_forbidden_with_only_view_extra_shift_permission(): void
    {
        $viewer = $this->actingUser(['ViewAny:ExtraShiftAssignment']);

        Livewire::actingAs($viewer)->test(ExtraShiftRecapPage::class)->assertForbidden();
    }

    public function test_extra_and_double_shift_are_separate_columns_in_one_table(): void
    {
        $viewer = $this->pengelola();
        $emp = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Budi Rekap']);
        $this->extraShift($emp, '2026-07-05', $this->siang);
        $this->extraShift($emp, '2026-07-06', $this->siang);
        $this->extraShift($emp, '2026-07-07', $this->pagi);
        $this->extraShift($emp, '2026-07-08', $this->siang, category: 'double_shift');

        $page = $this->page($viewer)->assertSee('Budi Rekap')->assertSee('Extra Shift')->assertSee('Double Shift');
        $recap = $page->instance()->recap;

        // Tanpa tab: satu tabel, dua kolom jenis. Extra Shift TIDAK tercampur double shift.
        $this->assertSame(['extra_shift' => 3, 'double_shift' => 1], $recap['overall']['counts']);
        $this->assertSame(['extra_shift' => 3, 'double_shift' => 1], $recap['rows'][0]['counts']);
        $this->assertSame(4, $recap['rows'][0]['total']);
        $this->assertSame(1, $recap['overall']['employee_count']);
        $this->assertCount(4, $recap['rows'][0]['items']);
        $this->assertSame(['extra_shift', 'extra_shift', 'extra_shift', 'double_shift'], array_column($recap['rows'][0]['items'], 'category'));
    }

    public function test_employee_with_only_double_shift_still_appears(): void
    {
        $viewer = $this->pengelola();
        $onlyExtra = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Hanya Extra']);
        $withDouble = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Punya Double']);
        $this->extraShift($onlyExtra, '2026-07-05', $this->siang);
        $this->extraShift($withDouble, '2026-07-06', $this->siang, category: 'double_shift');

        $page = $this->page($viewer)->assertSee('Hanya Extra')->assertSee('Punya Double');
        $recap = $page->instance()->recap;

        $this->assertSame(2, $recap['overall']['employee_count']);
        $this->assertSame(['extra_shift' => 1, 'double_shift' => 1], $recap['overall']['counts']);
    }
    public function test_excludes_out_of_range_non_approved_and_other_company(): void
    {
        $viewer = $this->pengelola();
        $emp = User::factory()->create(['company_id' => $this->company->id]);
        $this->extraShift($emp, '2026-07-05', $this->siang);            // masuk
        $this->extraShift($emp, '2026-08-05', $this->siang);            // di luar rentang
        $this->extraShift($emp, '2026-07-06', $this->siang, status: 'pending');

        $other = Companies::create(['code' => 'OTH', 'name' => 'Lain', 'location' => 'X', 'is_active' => true]);
        $stranger = User::factory()->create(['company_id' => $other->id]);
        $this->extraShift($stranger, '2026-07-05', $this->siang);       // company lain

        $recap = $this->page($viewer)->instance()->recap;

        $this->assertSame(1, $recap['overall']['total']);
        $this->assertSame(1, $recap['overall']['employee_count']);
    }

    public function test_department_filter_narrows_rows(): void
    {
        $viewer = $this->pengelola();
        $dept = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Keperawatan', 'code' => 'KEP', 'level' => 'DEPARTMENT']);
        $other = OrgStructure::create(['company_id' => $this->company->id, 'name' => 'Umum', 'code' => 'UMM', 'level' => 'DEPARTMENT']);

        $inDept = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Di Departemen']);
        $deptJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Perawat', 'status' => 'approved', 'org_structure_id' => $dept->id]);
        UserProfiles::create(['user_id' => $inDept->id, 'job_position_id' => $deptJob->id]);
        $outDept = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Di Luar']);
        $otherJob = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Staf Umum', 'status' => 'approved', 'org_structure_id' => $other->id]);
        UserProfiles::create(['user_id' => $outDept->id, 'job_position_id' => $otherJob->id]);

        $this->extraShift($inDept, '2026-07-05', $this->siang);
        $this->extraShift($outDept, '2026-07-05', $this->siang);

        $page = $this->page($viewer);
        $this->assertSame(2, $page->instance()->recap['overall']['employee_count']);

        $page->set('departmentId', $dept->id);
        $recap = $page->instance()->recap;

        $this->assertSame(1, $recap['overall']['employee_count']);
        $page->assertSee('Di Departemen')->assertDontSee('Di Luar');
    }
}
