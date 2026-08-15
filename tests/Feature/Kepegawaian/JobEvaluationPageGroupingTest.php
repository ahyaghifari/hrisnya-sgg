<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\JobPositionReqStatus;
use App\Livewire\Kepegawaian\JobEvaluationPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\Pangkats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tabel "Saat Ini" dikelompokkan per Pangkat (bukan flat list lagi) supaya jabatan setingkat
 * gampang dibandingkan JE-nya - urutan grup ikut urutan Pangkat dibuat (id ULID), jabatan tanpa
 * pangkat_id masuk grup "Tanpa Pangkat" di paling akhir.
 */
class JobEvaluationPageGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsWithPermission(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_JOB_EVALUATION->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'JE Test', 'guard_name' => 'web']);
        $role->givePermissionTo(CustomPermissionEnum::LIHAT_JOB_EVALUATION->value);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_positions_are_grouped_by_pangkat_in_creation_order_with_ungrouped_last(): void
    {
        $company = Companies::create(['code' => 'JEG', 'name' => 'RS Grouping Test', 'location' => 'Test', 'is_active' => true]);
        $user = $this->actingAsWithPermission($company);

        $manager = Pangkats::create(['name' => 'Manager RS', 'company_id' => $company->id]);
        $koordinator = Pangkats::create(['name' => 'Koordinator RS', 'company_id' => $company->id]);

        JobPositions::create(['company_id' => $company->id, 'name' => 'Manager Keuangan', 'pangkat_id' => $manager->id, 'status' => JobPositionReqStatus::APPROVED->value]);
        JobPositions::create(['company_id' => $company->id, 'name' => 'Koordinator IGD', 'pangkat_id' => $koordinator->id, 'status' => JobPositionReqStatus::APPROVED->value]);
        JobPositions::create(['company_id' => $company->id, 'name' => 'Koordinator ICU', 'pangkat_id' => $koordinator->id, 'status' => JobPositionReqStatus::APPROVED->value]);
        JobPositions::create(['company_id' => $company->id, 'name' => 'Digital Marketing', 'pangkat_id' => null, 'status' => JobPositionReqStatus::APPROVED->value]);
        // Draft tidak boleh ikut kehitung/tampil.
        JobPositions::create(['company_id' => $company->id, 'name' => 'Draft Position', 'pangkat_id' => $manager->id, 'status' => JobPositionReqStatus::DRAFT->value]);

        $this->actingAs($user);

        $component = Livewire::test(JobEvaluationPage::class, ['companyId' => $company->id])
            ->set('companyId', $company->id)
            ->assertOk();

        $grouped = $component->get('groupedJobPositions');

        $this->assertSame(['Manager RS', 'Koordinator RS', 'Tanpa Pangkat'], $grouped->keys()->all());
        $this->assertSame(1, $grouped['Manager RS']->count());
        $this->assertSame(2, $grouped['Koordinator RS']->count());
        $this->assertSame(['Koordinator ICU', 'Koordinator IGD'], $grouped['Koordinator RS']->pluck('name')->sort()->values()->all());
        $this->assertSame(1, $grouped['Tanpa Pangkat']->count());
        $this->assertSame('Digital Marketing', $grouped['Tanpa Pangkat']->first()->name);

        // Total via grup harus sama dengan total jabatan approved (draft tidak ikut).
        $this->assertSame(4, $grouped->sum->count());
    }
}
