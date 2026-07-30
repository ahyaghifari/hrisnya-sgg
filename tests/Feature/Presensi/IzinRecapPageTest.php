<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\IzinRecapPage;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IzinRecapPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsKepegawaian(string $companyId): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Absensi Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::SISTEM_ABSENSI->value);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $code = 'TST'): Companies
    {
        return Companies::create(['code' => $code, 'name' => "Company {$code}", 'location' => 'Test', 'is_active' => true]);
    }

    private function makePermissionRequest(User $user, string $permissionTypeId, string $date, string $status): void
    {
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => $status]);
        $permissionType = PermissionTypes::find($permissionTypeId);

        AttendanceRequestPermissionDetails::create([
            'request_id' => $request->id,
            'permission_type_id' => $permissionTypeId,
            'permission_type' => $permissionType->name,
            'category' => $permissionType->category,
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(IzinRecapPage::class)->assertForbidden();
    }

    public function test_recap_groups_approved_requests_by_category(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);

        $terlambat = PermissionTypes::create(['company_id' => $company->id, 'name' => 'Izin Terlambat Masuk', 'category' => PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value]);
        $pulangCepat = PermissionTypes::create(['company_id' => $company->id, 'name' => 'Izin Pulang Cepat', 'category' => PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value]);
        $lainnya = PermissionTypes::create(['company_id' => $company->id, 'name' => 'Izin Sakit', 'category' => null]);

        $month = now()->format('Y-m');

        $this->makePermissionRequest($employee, $terlambat->id, "{$month}-05", 'approved');
        $this->makePermissionRequest($employee, $terlambat->id, "{$month}-10", 'approved');
        $this->makePermissionRequest($employee, $pulangCepat->id, "{$month}-12", 'approved');
        $this->makePermissionRequest($employee, $lainnya->id, "{$month}-15", 'approved');
        // Belum diputuskan — tidak boleh ikut kehitung.
        $this->makePermissionRequest($employee, $terlambat->id, "{$month}-20", 'in_review');

        $this->actingAs($hc);

        $component = Livewire::test(IzinRecapPage::class)
            ->set('date_from', "{$month}-01")
            ->set('date_to', now()->endOfMonth()->toDateString());
        $recap = $component->get('recap');

        $this->assertCount(1, $recap['rows']);
        $row = $recap['rows'][0];

        $this->assertSame($employee->id, $row['user']->id);
        $this->assertSame(2, $row['counts'][PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value]);
        $this->assertSame(1, $row['counts'][PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->value]);
        $this->assertSame(1, $row['counts']['lainnya']);
        $this->assertSame(4, $row['total']);

        $this->assertSame(4, $recap['overall']['total']);
        $this->assertSame(1, $recap['overall']['employee_count']);
        $this->assertSame(2, $recap['overall']['counts'][PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value]);

        $this->assertCount(4, $row['items']);
        $this->assertSame("{$month}-05", $row['items'][0]['date']);

        // Klik baris karyawan -> modal rincian tanggal terbuka, isinya item yang sama.
        $component->call('openDetail', $employee->id);
        $this->assertSame($employee->id, $component->get('selectedUserId'));

        $component->call('closeDetail');
        $this->assertNull($component->get('selectedUserId'));
    }

    public function test_recap_is_scoped_per_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');
        $hc = $this->actingAsKepegawaian($companyA->id);

        $employeeA = User::factory()->create(['company_id' => $companyA->id]);
        $employeeB = User::factory()->create(['company_id' => $companyB->id]);

        $typeA = PermissionTypes::create(['company_id' => $companyA->id, 'name' => 'Izin Terlambat Masuk', 'category' => PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value]);
        $typeB = PermissionTypes::create(['company_id' => $companyB->id, 'name' => 'Izin Terlambat Masuk', 'category' => PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->value]);

        $month = now()->format('Y-m');
        $this->makePermissionRequest($employeeA, $typeA->id, "{$month}-05", 'approved');
        $this->makePermissionRequest($employeeB, $typeB->id, "{$month}-05", 'approved');

        $this->actingAs($hc);

        $recap = Livewire::test(IzinRecapPage::class)
            ->set('date_from', "{$month}-01")
            ->set('date_to', now()->endOfMonth()->toDateString())
            ->get('recap');

        $this->assertCount(1, $recap['rows']);
        $this->assertSame($employeeA->id, $recap['rows'][0]['user']->id);
    }

    public function test_recap_shows_not_ready_state_when_dates_not_selected(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $this->actingAs($hc);

        $recap = Livewire::test(IzinRecapPage::class)->get('recap');

        $this->assertFalse($recap['ready']);
        $this->assertCount(0, $recap['rows']);
    }
}
