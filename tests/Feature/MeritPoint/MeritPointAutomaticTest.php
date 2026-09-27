<?php

namespace Tests\Feature\MeritPoint;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\MeritPoint\MeritPointEvent;
use App\Models\Attendance\AttendanceRecords;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\MeritPoint\MeritPointTransaction;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use App\Services\MeritPoint\MeritPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeritPointAutomaticTest extends TestCase
{
    use RefreshDatabase;

    private function setup_rule(User $user, MeritPointEvent $event, int $points, bool $enabled = true): void
    {
        Setting::create([
            'company_id' => $user->company_id, 'module' => 'kepegawaian',
            'key' => KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
            'type' => 'boolean', 'value' => $enabled ? '1' : '0', 'label' => 'x',
        ]);
        MeritPointRule::create([
            'company_id' => $user->company_id, 'type' => 'penalty', 'name' => 'auto',
            'points' => $points, 'trigger_type' => 'automatic', 'event' => $event->value, 'is_active' => true,
        ]);
    }

    public function test_event_applies_once_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['company_id' => Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id]);
        $this->setup_rule($user, MeritPointEvent::SP1, -10);
        $svc = app(MeritPointService::class);

        $svc->applyEvent(MeritPointEvent::SP1, $user->id, $user->company_id, 'employee_sp', '01SPX', 'SP1');
        $svc->applyEvent(MeritPointEvent::SP1, $user->id, $user->company_id, 'employee_sp', '01SPX', 'SP1'); // idempotent

        $this->assertSame(1, MeritPointTransaction::count());
        $this->assertSame(90, $svc->computeBalance($user->id));

        $svc->revokeEvent($user->id, 'employee_sp', '01SPX');
        $this->assertSame(100, $svc->computeBalance($user->id));
    }

    public function test_disabled_company_gets_nothing(): void
    {
        $user = User::factory()->create(['company_id' => Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id]);
        $this->setup_rule($user, MeritPointEvent::LATE, -1, enabled: false);

        app(MeritPointService::class)->applyEvent(MeritPointEvent::LATE, $user->id, $user->company_id, 'attendance_record', '01A', 'telat');

        $this->assertSame(0, MeritPointTransaction::count());
    }

    /** Tipe (Reward/Penalty) tidak boleh punya default - admin harus pilih sendiri lewat radio,
     * bukan kepencet Simpan dengan tipe yang kepilih diam-diam (instruksi user 2026-09-27). */
    public function test_type_has_no_default_and_blocks_submit_until_chosen(): void
    {
        $user = User::factory()->create(['company_id' => Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id]);
        foreach (['ViewAny:MeritPointRule', 'Create:MeritPointRule'] as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Kepegawaian\MeritPointPage::class)
            ->call('create')
            ->assertSet('type', '')
            ->set('name', 'Belum pilih tipe')->set('points', 5)
            ->call('save')
            ->assertHasErrors('type');

        $this->assertSame(0, MeritPointRule::count());
    }

    /** Modal "Tambah Aturan" cuma bikin rule MANUAL - tidak ada pilihan Cara Pemakaian lagi. */
    public function test_manual_rule_modal_always_creates_manual_trigger_type(): void
    {
        $user = User::factory()->create(['company_id' => Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id]);
        foreach (['ViewAny:MeritPointRule', 'Create:MeritPointRule', 'Update:MeritPointRule'] as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Kepegawaian\MeritPointPage::class)
            ->assertOk()
            ->call('create')
            ->set('name', 'Bantu rekan kerja')->set('type', 'reward')->set('points', 5)
            ->call('save')
            ->assertHasNoErrors();

        $rule = MeritPointRule::first();
        $this->assertSame('manual', $rule->trigger_type->value);
        $this->assertNull($rule->event);
    }

    /** Section "Otomatis" (saveAutomaticRule) - 4 baris tetap dimuat langsung dari
     * MeritPointEvent::cases(), diisi tanpa modal, upsert per event, tidak bisa dobel. */
    public function test_automatic_section_loads_all_events_and_saves_per_row(): void
    {
        $user = User::factory()->create(['company_id' => Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id]);
        foreach (['ViewAny:MeritPointRule', 'Create:MeritPointRule', 'Update:MeritPointRule'] as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        $test = \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Kepegawaian\MeritPointPage::class)
            ->assertOk()
            ->assertSet('automaticForm.sp1.is_active', false)
            ->assertSet('automaticForm.sp1.points', 0)
            ->assertCount('automaticForm', 4);

        $test->set('automaticForm.sp1.type', 'penalty')
            ->set('automaticForm.sp1.points', -10)
            ->set('automaticForm.sp1.is_active', true)
            ->call('saveAutomaticRule', 'sp1')
            ->assertHasNoErrors()
            ->assertSet('automaticForm.sp1.is_active', true);

        $this->assertSame(1, MeritPointRule::where('event', 'sp1')->count());
        $rule = MeritPointRule::where('event', 'sp1')->first();
        $this->assertSame('automatic', $rule->trigger_type->value);
        $this->assertSame(-10, $rule->points);

        // Simpan lagi (ubah poin) - HARUS update baris yang sama, tidak boleh bikin baris kedua
        // (upsert keyBy company_id+trigger_type+event, satu baris tetap per kejadian).
        $test->set('automaticForm.sp1.points', -15)->call('saveAutomaticRule', 'sp1')->assertHasNoErrors();
        $this->assertSame(1, MeritPointRule::where('event', 'sp1')->count());
        $this->assertSame(-15, MeritPointRule::where('event', 'sp1')->first()->points);
    }
}
