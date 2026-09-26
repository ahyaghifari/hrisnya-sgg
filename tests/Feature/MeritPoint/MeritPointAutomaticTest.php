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

    public function test_page_renders_and_saves_automatic_rule(): void
    {
        $user = User::factory()->create(['company_id' => Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true])->id]);
        foreach (['ViewAny:MeritPointRule', 'Create:MeritPointRule', 'Update:MeritPointRule'] as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Kepegawaian\MeritPointPage::class)
            ->assertOk()
            ->call('create')
            ->set('name', 'SP1 potong')->set('type', 'penalty')->set('points', -10)
            ->set('trigger_type', 'automatic')->set('event', 'sp1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sp1', MeritPointRule::first()->event->value);
    }
}
