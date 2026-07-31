<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\CutiRecapPage;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Minggu tidak dihitung sebagai hari cuti terpakai di rekap (dikonfirmasi user) - Sabtu tetap
 * dihitung, beda dari perhitungan hari kerja lain di sistem ini yang mengecualikan Sabtu+Minggu.
 */
class CutiRecapPageSundayExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAbsensi(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Rekap Cuti Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole($role);

        return $viewer;
    }

    public function test_sunday_within_range_is_excluded_from_day_count(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Cuti']);

        // 2026-03-28 (Sabtu) - 2026-03-31 (Selasa): Minggu 29 Maret dikecualikan -> 3 hari
        // (Sabtu, Senin, Selasa dihitung; cuma Minggu yang tidak).
        $request = AttendanceRequests::create([
            'user_id' => $staff->id,
            'type' => 'leave',
            'status' => 'approved',
        ]);
        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Tahunan',
            'start_date' => '2026-03-28',
            'end_date' => '2026-03-31',
            'total_days' => 4,
        ]);

        $this->actingAs($viewer);

        $component = Livewire::test(CutiRecapPage::class)
            ->set('date_from', '2026-03-01')
            ->set('date_to', '2026-03-31');

        $recap = $component->get('recap');

        $this->assertSame(3, $recap['rows'][0]['total']);
    }

    public function test_range_without_sunday_is_unaffected(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->actingAsAbsensi($company->id);
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Cuti']);

        // 2026-03-23 (Senin) - 2026-03-25 (Rabu): tidak ada Minggu -> tetap 3 hari.
        $request = AttendanceRequests::create([
            'user_id' => $staff->id,
            'type' => 'leave',
            'status' => 'approved',
        ]);
        AttendanceRequestLeaveDetails::create([
            'request_id' => $request->id,
            'leave_type' => 'Cuti Tahunan',
            'start_date' => '2026-03-23',
            'end_date' => '2026-03-25',
            'total_days' => 3,
        ]);

        $this->actingAs($viewer);

        $component = Livewire::test(CutiRecapPage::class)
            ->set('date_from', '2026-03-01')
            ->set('date_to', '2026-03-31');

        $recap = $component->get('recap');

        $this->assertSame(3, $recap['rows'][0]['total']);
    }
}
