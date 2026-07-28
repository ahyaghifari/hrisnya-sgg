<?php

namespace Tests\Feature\Kepegawaian;

use App\Enum\UserTransitionTypeEnum;
use App\Livewire\Kepegawaian\UserTransitionsPage;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTransitionsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsHc(string $companyId): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:UserTransition', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:UserTransition', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'HC UserTransition Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:UserTransition', 'Create:UserTransition']);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $code = 'TST'): Companies
    {
        return Companies::create(['code' => $code, 'name' => "Company {$code}", 'location' => 'Test', 'is_active' => true]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($user)->test(UserTransitionsPage::class)->assertForbidden();
    }

    public function test_lists_only_transitions_from_own_company(): void
    {
        $companyA = $this->makeCompany('AAA');
        $companyB = $this->makeCompany('BBB');
        $hc = $this->actingAsHc($companyA->id);

        $employeeA = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Karyawan A']);
        $employeeB = User::factory()->create(['company_id' => $companyB->id, 'name' => 'Karyawan B']);

        UserTransition::create(['company_id' => $companyA->id, 'user_id' => $employeeA->id, 'created_by' => $hc->id, 'number' => 'A/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::PROMOTION->value]);
        UserTransition::create(['company_id' => $companyB->id, 'user_id' => $employeeB->id, 'created_by' => $hc->id, 'number' => 'B/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::PROMOTION->value]);

        Livewire::actingAs($hc)
            ->test(UserTransitionsPage::class)
            ->assertSee('Karyawan A')
            ->assertDontSee('Karyawan B');
    }

    public function test_create_button_label_matches_selected_type(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsHc($company->id);

        // Default jenis Promosi.
        Livewire::actingAs($hc)
            ->test(UserTransitionsPage::class)
            ->assertSee('Ajukan Promosi')
            ->call('setType', UserTransitionTypeEnum::TERMINATION->value)
            ->assertSee('Ajukan PHK');
    }

    public function test_switching_type_shows_only_matching_transitions(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsHc($company->id);
        $promoted = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Promosi']);
        $terminated = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan PHK']);

        UserTransition::create(['company_id' => $company->id, 'user_id' => $promoted->id, 'created_by' => $hc->id, 'number' => 'P/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::PROMOTION->value]);
        UserTransition::create(['company_id' => $company->id, 'user_id' => $terminated->id, 'created_by' => $hc->id, 'number' => 'T/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::TERMINATION->value]);

        $component = Livewire::actingAs($hc)->test(UserTransitionsPage::class);

        // Default: jenis Promosi.
        $component->assertSee('Karyawan Promosi')->assertDontSee('Karyawan PHK');

        $component->call('setType', UserTransitionTypeEnum::TERMINATION->value)
            ->assertSee('Karyawan PHK')
            ->assertDontSee('Karyawan Promosi');
    }

    public function test_transition_counts_reflect_each_type(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsHc($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        UserTransition::create(['company_id' => $company->id, 'user_id' => $employee->id, 'created_by' => $hc->id, 'number' => 'P/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::PROMOTION->value]);
        UserTransition::create(['company_id' => $company->id, 'user_id' => $employee->id, 'created_by' => $hc->id, 'number' => 'P/2', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::PROMOTION->value]);
        UserTransition::create(['company_id' => $company->id, 'user_id' => $employee->id, 'created_by' => $hc->id, 'number' => 'T/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::TERMINATION->value]);

        $counts = Livewire::actingAs($hc)->test(UserTransitionsPage::class)->get('transitionCounts');

        $this->assertSame(2, $counts->get(UserTransitionTypeEnum::PROMOTION->value));
        $this->assertSame(1, $counts->get(UserTransitionTypeEnum::TERMINATION->value));
    }

    /**
     * Regresi: pagination di halaman ini pakai wire:navigate (full page-visit), yang bikin
     * komponen di-mount ULANG dari URL — tanpa #[Url] filter `type` hilang begitu pindah
     * halaman. Test GET langsung (bukan Livewire::test) biar simulasi mount dari URL yang
     * sebenarnya kejadian saat wire:navigate.
     */
    public function test_type_filter_is_read_from_url_on_page_load(): void
    {
        $company = $this->makeCompany();
        $hc = $this->actingAsHc($company->id);
        $promoted = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Promosi']);
        $terminated = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan PHK']);

        // created_at eksplisit menaik (T/1 paling lama) — baseQuery pakai ->latest(), jadi
        // urutan tampil deterministik terlepas dari kecepatan eksekusi test (dua create() yang
        // kebetulan dapat timestamp detik yang sama bisa membalik urutan tie-break).
        for ($i = 1; $i <= 15; $i++) {
            UserTransition::create([
                'company_id' => $company->id, 'user_id' => $terminated->id, 'created_by' => $hc->id,
                'number' => "T/{$i}", 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::TERMINATION->value,
                'created_at' => now()->subMinutes(20 - $i),
            ]);
        }
        UserTransition::create([
            'company_id' => $company->id, 'user_id' => $promoted->id, 'created_by' => $hc->id,
            'number' => 'P/1', 'status' => 'pending', 'transition_type' => UserTransitionTypeEnum::PROMOTION->value,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($hc)->get(route('kepegawaian.mutasi-karyawan', ['type' => UserTransitionTypeEnum::TERMINATION->value, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('T/1', false);
        $response->assertDontSee('P/1');
    }
}
