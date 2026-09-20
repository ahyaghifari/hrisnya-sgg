<?php

namespace Tests\Feature\Presensi;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\IzinRecapPage;
use App\Models\Attendance\AttendanceRequestPermissionDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Enum\Attendance\PermissionTypeEnum;
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
        // mount() mengecek VIEW_REPORT_DATA_ABSENSI; SISTEM_ABSENSI cuma buat gerbang perusahaan.
        foreach ([CustomPermissionEnum::SISTEM_ABSENSI, CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI] as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Absensi Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value]);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $code = 'TST'): Companies
    {
        return Companies::create(['code' => $code, 'name' => "Company {$code}", 'location' => 'Test', 'is_active' => true]);
    }

    /** $type null = data lama tanpa category (masuk kolom "lainnya" di rekap). */
    private function makePermissionRequest(User $user, ?PermissionTypeEnum $type, string $date, string $status): void
    {
        $request = AttendanceRequests::create(['user_id' => $user->id, 'type' => 'permission', 'status' => $status]);

        AttendanceRequestPermissionDetails::create([
            'request_id' => $request->id,
            'permission_type' => $type?->label() ?? 'Izin Medis',
            'category' => $type?->category()->value,
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

        $month = now()->format('Y-m');

        $this->makePermissionRequest($employee, PermissionTypeEnum::TERLAMBAT, "{$month}-05", 'approved');
        $this->makePermissionRequest($employee, PermissionTypeEnum::TERLAMBAT, "{$month}-10", 'approved');
        $this->makePermissionRequest($employee, PermissionTypeEnum::PULANG_CEPAT, "{$month}-12", 'approved');
        $this->makePermissionRequest($employee, null, "{$month}-15", 'approved');
        // Belum diputuskan — tidak boleh ikut kehitung.
        $this->makePermissionRequest($employee, PermissionTypeEnum::TERLAMBAT, "{$month}-20", 'in_review');

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

    public function test_meninggalkan_jam_kerja_is_one_group_header_with_three_subcolumns(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $this->actingAs($hc);

        Livewire::test(IzinRecapPage::class)
            ->assertSeeInOrder(['Izin Meninggalkan Jam Kerja', 'Terlambat', 'Pulang Duluan', 'Tengah Jam Kerja']);
    }

    public function test_summary_cards_merge_meninggalkan_jam_kerja_and_drop_lainnya_card(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $month = now()->format('Y-m');

        $this->makePermissionRequest($employee, PermissionTypeEnum::TERLAMBAT, "{$month}-05", 'approved');
        $this->makePermissionRequest($employee, PermissionTypeEnum::PULANG_CEPAT, "{$month}-06", 'approved');
        $this->makePermissionRequest($employee, null, "{$month}-07", 'approved'); // lainnya

        $this->actingAs($hc);

        $page = Livewire::test(IzinRecapPage::class)
            ->set('date_from', "{$month}-01")
            ->set('date_to', now()->endOfMonth()->toDateString());

        // Kartu per kategori lama (Terlambat Masuk / Pulang Cepat) diganti 1 kartu gabungan.
        $page->assertSee('Izin Meninggalkan Jam Kerja')
            ->assertDontSee(PermissionTypeCategoryEnum::IZIN_TERLAMBAT_MASUK->label())
            ->assertDontSee(PermissionTypeCategoryEnum::IZIN_PULANG_CEPAT->label());

        // Total tetap menghitung semua (termasuk lainnya).
        $this->assertSame(3, $page->get('recap')['overall']['total']);
    }

    public function test_recap_is_scoped_per_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');
        $hc = $this->actingAsKepegawaian($companyA->id);

        $employeeA = User::factory()->create(['company_id' => $companyA->id]);
        $employeeB = User::factory()->create(['company_id' => $companyB->id]);

        $month = now()->format('Y-m');
        $this->makePermissionRequest($employeeA, PermissionTypeEnum::TERLAMBAT, "{$month}-05", 'approved');
        $this->makePermissionRequest($employeeB, PermissionTypeEnum::TERLAMBAT, "{$month}-05", 'approved');

        $this->actingAs($hc);

        $recap = Livewire::test(IzinRecapPage::class)
            ->set('date_from', "{$month}-01")
            ->set('date_to', now()->endOfMonth()->toDateString())
            ->get('recap');

        $this->assertCount(1, $recap['rows']);
        $this->assertSame($employeeA->id, $recap['rows'][0]['user']->id);
    }

    public function test_mount_defaults_date_range_to_current_attendance_period(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsKepegawaian($company->id);

        $this->actingAs($hc);

        $component = Livewire::test(IzinRecapPage::class);
        [$periodStart, $periodEnd] = \App\Services\Attendance\AttendancePeriodResolver::resolve(now(), $company->id);

        $this->assertSame($periodStart->toDateString(), $component->get('date_from'));
        $this->assertSame($periodEnd->toDateString(), $component->get('date_to'));
        $this->assertTrue($component->get('recap')['ready']);
    }
}
