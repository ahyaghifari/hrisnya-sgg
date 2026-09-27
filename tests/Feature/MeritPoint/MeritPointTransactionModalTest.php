<?php

namespace Tests\Feature\MeritPoint;

use App\Enum\CustomPermissionEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Livewire\EmployeeStatistics;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\MeritPoint\MeritPointTransaction;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Modal "Transaksi Merit Point" di tab Karir (EmployeeStatistics) - 2 tab: "Berdasarkan Rule"
 * (dropdown meritPointRules) & "Manual" (jumlah+arah bebas). Tab "Manual" sempat dihapus
 * 2026-09-27, dikembalikan hari yang sama atas instruksi user. Rule bertipe Otomatis
 * (trigger_type=automatic) tidak boleh dipilih di tab Rule sama sekali - itu murni jalan
 * sendiri lewat MeritPointService::applyEvent().
 */
class MeritPointTransactionModalTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    private User $giver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'MPT', 'name' => 'Merit Co', 'location' => 'Test', 'is_active' => true]);

        Setting::create([
            'company_id' => $this->company->id, 'module' => 'kepegawaian',
            'key' => KepegawaianSettingEnum::MERIT_POINT_ENABLED->value, 'type' => 'boolean', 'value' => '1', 'label' => 'x',
        ]);

        // BERI_MERIT_POINT gerbang tombol/aksi kasih poin. SISTEM_KEPEGAWAIAN gerbang TERPISAH
        // (EmployeeStatistics::canManage()) buat sekadar buka halaman Data Diri karyawan lain -
        // keduanya dibutuhkan di sini karena giver melihat data $employee, bukan dirinya sendiri.
        $this->giver = User::factory()->create(['company_id' => $this->company->id]);
        foreach ([CustomPermissionEnum::BERI_MERIT_POINT, CustomPermissionEnum::SISTEM_KEPEGAWAIAN] as $p) {
            Permission::firstOrCreate(['name' => $p->value, 'guard_name' => 'web']);
            $this->giver->givePermissionTo($p->value);
        }
    }

    private function manualRule(int $points = 5): MeritPointRule
    {
        return MeritPointRule::create([
            'company_id' => $this->company->id, 'type' => 'reward', 'name' => 'Bantu rekan kerja',
            'points' => $points, 'trigger_type' => 'manual', 'is_active' => true,
        ]);
    }

    private function automaticRule(int $points = -10): MeritPointRule
    {
        return MeritPointRule::create([
            'company_id' => $this->company->id, 'type' => 'penalty', 'name' => 'Otomatis: SP1',
            'points' => $points, 'trigger_type' => 'automatic', 'event' => 'sp1', 'is_active' => true,
        ]);
    }

    public function test_manual_tab_gives_signed_points_without_a_rule(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->giver);

        Livewire::test(EmployeeStatistics::class, ['record' => $employee])
            ->call('openMeritTransactionModal')
            ->set('meritTab', 'manual')
            ->set('merit_manual_type', 'subtract')
            ->set('merit_points', 8)
            ->set('merit_reason', 'Melanggar SOP')
            ->call('saveMeritTransaction')
            ->assertHasNoErrors();

        $tx = MeritPointTransaction::where('user_id', $employee->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame(-8, $tx->points);
        $this->assertNull($tx->rule_id);
    }

    public function test_rule_dropdown_excludes_automatic_rules(): void
    {
        $rule = $this->manualRule();
        $this->automaticRule();
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->giver);

        $rules = Livewire::test(EmployeeStatistics::class, ['record' => $employee])->get('meritPointRules');

        $this->assertCount(1, $rules);
        $this->assertSame($rule->id, $rules->first()->id);
    }

    public function test_save_gives_exact_rule_points(): void
    {
        $rule = $this->manualRule(7);
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->giver);

        Livewire::test(EmployeeStatistics::class, ['record' => $employee])
            ->call('openMeritTransactionModal')
            ->set('merit_rule_id', $rule->id)
            ->set('merit_reason', 'Membantu rekan sekerja')
            ->call('saveMeritTransaction')
            ->assertHasNoErrors();

        $tx = MeritPointTransaction::where('user_id', $employee->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame(7, $tx->points);
        $this->assertSame($rule->id, $tx->rule_id);
    }

    /** Tidak boleh beri poin ke diri sendiri walau punya BERI_MERIT_POINT - dicek di server
     * (bukan cuma tombol disembunyikan), instruksi user 2026-09-27. */
    public function test_cannot_give_points_to_self_even_with_permission(): void
    {
        $rule = $this->manualRule();
        $this->actingAs($this->giver);

        Livewire::test(EmployeeStatistics::class, ['record' => $this->giver])
            ->assertSet('canCreateMeritTransaction', false)
            ->call('openMeritTransactionModal')
            ->assertForbidden();

        $this->assertSame(0, MeritPointTransaction::where('user_id', $this->giver->id)->count());
    }

    /** Payload dimanipulasi memilih rule Otomatis - server tolak, bukan cuma disembunyikan di UI. */
    public function test_cannot_give_points_using_an_automatic_rule_even_via_manipulated_payload(): void
    {
        $auto = $this->automaticRule();
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->giver);

        Livewire::test(EmployeeStatistics::class, ['record' => $employee])
            ->call('openMeritTransactionModal')
            ->set('merit_rule_id', $auto->id)
            ->set('merit_reason', 'Coba pakai rule otomatis')
            ->call('saveMeritTransaction')
            ->assertHasErrors('merit_rule_id');

        $this->assertSame(0, MeritPointTransaction::where('user_id', $employee->id)->count());
    }

    /** Info Aturan: read-only, kelihatan buat siapa saja yang lihat widget (diri sendiri ATAU
     * orang lain) - tidak butuh BERI_MERIT_POINT, cuma menampilkan rule aktif company itu. */
    public function test_rules_info_shows_active_manual_and_automatic_rules_for_anyone(): void
    {
        $manual = $this->manualRule(5);
        $auto = $this->automaticRule(-10);
        $employee = User::factory()->create(['company_id' => $this->company->id]);

        // Viewer TANPA BERI_MERIT_POINT sama sekali, cuma lihat profil sendiri.
        $viewer = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($viewer);

        $component = Livewire::test(EmployeeStatistics::class, ['record' => $viewer])
            ->call('openMeritRulesInfoModal')
            ->assertSee('Aturan Merit Point')
            ->assertSee($manual->name)
            ->assertSee('Penerbitan SP 1');

        $rules = $component->get('meritRulesInfo');
        $this->assertCount(2, $rules);
        $this->assertTrue($rules->contains('id', $manual->id));
        $this->assertTrue($rules->contains('id', $auto->id));
    }

    /** Widget tab Karir: saldo lewat 100 (poin bonus) tetap tampil "100" di medali (ring cuma
     * masuk akal 0-100%), kelebihannya jadi badge "+12" terpisah - instruksi user 2026-09-27. */
    public function test_widget_shows_100_in_medal_and_bonus_as_separate_badge_past_100(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        \App\Models\UserHrBalance::create(['user_id' => $employee->id, 'merit_point' => 112]);
        $this->actingAs($employee);

        Livewire::test(EmployeeStatistics::class, ['record' => $employee])
            ->assertSee('<span class="num">100</span>', false)
            ->assertSee('+12');
    }
}
