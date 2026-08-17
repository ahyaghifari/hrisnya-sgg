<?php

namespace Tests\Feature\Presensi;

use App\Enum\ShiftType;
use App\Livewire\Presensi\ExtraShiftRulePage;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CRUD pasangan transisi extra shift (ViewAny/Create/Delete:ExtraShiftRule, Shield-style -
 * lihat migration seed_extra_shift_rule_permissions). Halaman ini TIDAK bisa isi/ubah nominal
 * (amount) - itu cuma bisa lewat MappingKomponenGajiPage (Payroll).
 */
class ExtraShiftRulePageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsEditor(Companies $company): User
    {
        Permission::firstOrCreate(['name' => 'ViewAny:ExtraShiftRule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:ExtraShiftRule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:ExtraShiftRule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Extra Shift Rule Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:ExtraShiftRule', 'Create:ExtraShiftRule', 'Delete:ExtraShiftRule']);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_add_rule_creates_pair_without_amount(): void
    {
        $company = Companies::create(['code' => 'ESR1', 'name' => 'RS Extra Shift Rule 1', 'location' => 'Test', 'is_active' => true]);
        $editor = $this->actingAsEditor($company);

        Livewire::actingAs($editor)
            ->test(ExtraShiftRulePage::class)
            ->set('fromShiftType', ShiftType::PAGI->value)
            ->set('toShiftType', ShiftType::SIANG->value)
            ->call('addRule')
            ->assertHasNoErrors();

        $rule = ExtraShiftRule::where('company_id', $company->id)->first();
        $this->assertNotNull($rule);
        $this->assertSame('pagi', $rule->from_shift_type);
        $this->assertSame('siang', $rule->to_shift_type);
        $this->assertNull($rule->amount);
    }

    public function test_from_equal_to_is_rejected(): void
    {
        $company = Companies::create(['code' => 'ESR2', 'name' => 'RS Extra Shift Rule 2', 'location' => 'Test', 'is_active' => true]);
        $editor = $this->actingAsEditor($company);

        Livewire::actingAs($editor)
            ->test(ExtraShiftRulePage::class)
            ->set('fromShiftType', ShiftType::PAGI->value)
            ->set('toShiftType', ShiftType::PAGI->value)
            ->call('addRule')
            ->assertHasErrors(['toShiftType']);

        $this->assertSame(0, ExtraShiftRule::where('company_id', $company->id)->count());
    }

    public function test_duplicate_pair_is_rejected(): void
    {
        $company = Companies::create(['code' => 'ESR3', 'name' => 'RS Extra Shift Rule 3', 'location' => 'Test', 'is_active' => true]);
        $editor = $this->actingAsEditor($company);
        ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'pagi', 'to_shift_type' => 'siang']);

        Livewire::actingAs($editor)
            ->test(ExtraShiftRulePage::class)
            ->set('fromShiftType', ShiftType::PAGI->value)
            ->set('toShiftType', ShiftType::SIANG->value)
            ->call('addRule')
            ->assertHasErrors(['toShiftType']);

        $this->assertSame(1, ExtraShiftRule::where('company_id', $company->id)->count());
    }

    public function test_remove_rule_deletes_pair(): void
    {
        $company = Companies::create(['code' => 'ESR4', 'name' => 'RS Extra Shift Rule 4', 'location' => 'Test', 'is_active' => true]);
        $editor = $this->actingAsEditor($company);
        $rule = ExtraShiftRule::create(['company_id' => $company->id, 'from_shift_type' => 'siang', 'to_shift_type' => 'malam']);

        Livewire::actingAs($editor)
            ->test(ExtraShiftRulePage::class)
            ->call('removeRule', $rule->id);

        $this->assertDatabaseMissing('extra_shift_rules', ['id' => $rule->id]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ExtraShiftRulePage::class)
            ->assertForbidden();
    }
}
