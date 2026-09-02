<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\JobPositionReqStatus;
use App\Livewire\Kepegawaian\JobEvaluationPage;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JobEvaluationPageSaveTest extends TestCase
{
    use RefreshDatabase;

    private function user(Companies $company): User
    {
        foreach ([CustomPermissionEnum::LIHAT_JOB_EVALUATION->value, CustomPermissionEnum::KELOLA_JOB_EVALUATION->value] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'JE Save Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::LIHAT_JOB_EVALUATION->value, CustomPermissionEnum::KELOLA_JOB_EVALUATION->value]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_saved_je_shows_again_on_reload(): void
    {
        $company = Companies::create(['code' => 'JES', 'name' => 'RS Save Test', 'location' => 'Test', 'is_active' => true]);
        $pos = JobPositions::create(['company_id' => $company->id, 'name' => 'Analis', 'status' => JobPositionReqStatus::APPROVED->value]);
        $this->actingAs($this->user($company));

        Livewire::test(JobEvaluationPage::class)
            ->set('companyId', $company->id)
            ->set("jeValues.{$pos->id}", '350')
            ->call('saveJe')
            ->assertHasNoErrors();

        $this->assertSame(350, (int) $pos->fresh()->job_evaluation_score);

        // Buka lagi halaman (mount ulang)
        Livewire::test(JobEvaluationPage::class)
            ->set('companyId', $company->id)
            ->assertSet("jeValues.{$pos->id}", '350');
    }

    public function test_switching_company_reloads_je_values(): void
    {
        Permission::firstOrCreate(['name' => \App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']);

        $c1 = Companies::create(['code' => 'AAA', 'name' => 'RS Satu', 'location' => 'T', 'is_active' => true]);
        $c2 = Companies::create(['code' => 'BBB', 'name' => 'RS Dua', 'location' => 'T', 'is_active' => true]);
        $p1 = JobPositions::create(['company_id' => $c1->id, 'name' => 'Pos C1', 'status' => JobPositionReqStatus::APPROVED->value, 'job_evaluation_score' => 100]);
        $p2 = JobPositions::create(['company_id' => $c2->id, 'name' => 'Pos C2', 'status' => JobPositionReqStatus::APPROVED->value, 'job_evaluation_score' => 200]);

        $user = $this->user($c1);
        $user->givePermissionTo(\App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value);
        $user->update(['company_id' => null]);
        $this->actingAs($user->fresh());

        Livewire::test(JobEvaluationPage::class)
            ->assertSet("jeValues.{$p1->id}", '100')
            ->set('companyId', $c2->id)
            ->assertSet("jeValues.{$p2->id}", '200')
            ->assertSet("jeValues.{$p1->id}", null);
    }
}
