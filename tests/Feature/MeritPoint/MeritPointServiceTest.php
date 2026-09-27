<?php

namespace Tests\Feature\MeritPoint;

use App\Enum\MeritPoint\MeritPointTriggerType;
use App\Enum\MeritPoint\MeritPointType;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\UserHrBalance;
use App\Services\MeritPoint\MeritPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saldo Merit Point mulai dari 100 (karyawan tanpa transaksi dianggap skor penuh - lihat widget
 * tab Karir) dan dijepit di LANTAI 0 saja SETIAP transaksi - TIDAK ada plafon 100 lagi (instruksi
 * user 2026-09-27, "poin bonus"): saldo boleh lewat 100 (mis. 110), widget menampilkan
 * kelebihannya sebagai badge "+10" terpisah dari angka 100 di gauge. Bukan SUM lalu dijepit
 * sekali di akhir - itu bikin transaksi pertama dihitung dari 0 kalau urutannya penalti duluan.
 */
class MeritPointServiceTest extends TestCase
{
    use RefreshDatabase;

    private function add(User $user, int $points): void
    {
        app(MeritPointService::class)->addTransaction([
            'user_id' => $user->id, 'points' => $points, 'reason' => 'tes',
        ]);
    }

    private function balance(User $user): int
    {
        return UserHrBalance::where('user_id', $user->id)->value('merit_point');
    }

    public function test_first_penalty_deducts_from_full_score_not_from_zero(): void
    {
        $user = User::factory()->create();

        $this->add($user, -5);

        $this->assertSame(95, $this->balance($user));
    }

    public function test_reward_can_go_past_100_and_penalty_still_counts_from_there(): void
    {
        $user = User::factory()->create();

        $this->add($user, 10); // 100 + 10, tidak lagi hangus
        $this->assertSame(110, $this->balance($user));

        $this->add($user, -5);
        $this->assertSame(105, $this->balance($user));
    }

    public function test_balance_never_goes_below_zero_and_recovers_from_zero(): void
    {
        $user = User::factory()->create();

        $this->add($user, -200);
        $this->assertSame(0, $this->balance($user));

        $this->add($user, 20);
        $this->assertSame(20, $this->balance($user));
    }

    public function test_preview_shows_result_without_writing_anything(): void
    {
        $user = User::factory()->create();
        $service = app(MeritPointService::class);

        $this->add($user, -30);

        $this->assertSame(65, $service->previewBalance($user->id, -5));
        $this->assertSame(570, $service->previewBalance($user->id, 500));
        $this->assertSame(0, $service->previewBalance($user->id, -500));
        $this->assertSame(70, $this->balance($user), 'preview tidak boleh mengubah saldo tersimpan');
    }

    // ── Poin Bonus (instruksi user 2026-09-27) ──────────────────────────────

    private function rewardRule(?int $points = 15, ?int $bonusPoints = 2): MeritPointRule
    {
        $company = Companies::create(['code' => 'MPB' . uniqid(), 'name' => 'Bonus Co', 'location' => 'Test', 'is_active' => true]);

        return MeritPointRule::create([
            'company_id' => $company->id, 'type' => MeritPointType::REWARD->value, 'name' => 'Ikut Pelatihan',
            'points' => $points, 'bonus_points' => $bonusPoints, 'trigger_type' => MeritPointTriggerType::MANUAL->value, 'is_active' => true,
        ]);
    }

    public function test_effective_points_uses_normal_points_below_100(): void
    {
        $user = User::factory()->create();
        $rule = $this->rewardRule(points: 15, bonusPoints: 2);
        $this->add($user, -50); // saldo 50, masih di bawah 100

        $this->assertSame(15, app(MeritPointService::class)->effectivePoints($rule, $user->id));
    }

    public function test_effective_points_switches_to_bonus_once_balance_reaches_100(): void
    {
        $user = User::factory()->create(); // saldo default 100 (belum ada transaksi sama sekali)
        $rule = $this->rewardRule(points: 15, bonusPoints: 2);

        $this->assertSame(2, app(MeritPointService::class)->effectivePoints($rule, $user->id));
    }

    public function test_effective_points_keeps_normal_points_when_bonus_points_is_null(): void
    {
        $user = User::factory()->create();
        $rule = $this->rewardRule(points: 15, bonusPoints: null);

        $this->assertSame(15, app(MeritPointService::class)->effectivePoints($rule, $user->id));
    }

    public function test_effective_points_ignores_bonus_for_penalty_rules(): void
    {
        $company = Companies::create(['code' => 'MPP' . uniqid(), 'name' => 'Penalty Co', 'location' => 'Test', 'is_active' => true]);
        $rule = MeritPointRule::create([
            'company_id' => $company->id, 'type' => MeritPointType::PENALTY->value, 'name' => 'Terlambat',
            'points' => -5, 'bonus_points' => -1, 'trigger_type' => MeritPointTriggerType::MANUAL->value, 'is_active' => true,
        ]);
        $user = User::factory()->create();

        $this->assertSame(-5, app(MeritPointService::class)->effectivePoints($rule, $user->id));
    }

    public function test_bonus_rule_applied_through_apply_event_stacks_past_100(): void
    {
        $companyModel = Companies::create(['code' => 'MPE' . uniqid(), 'name' => 'Event Co', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $companyModel->id]);

        \App\Models\Setting::create([
            'company_id' => $companyModel->id, 'module' => 'kepegawaian',
            'key' => \App\Enum\Kepegawaian\KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
            'type' => 'boolean', 'value' => '1', 'label' => 'x',
        ]);
        MeritPointRule::create([
            'company_id' => $companyModel->id, 'type' => MeritPointType::REWARD->value, 'name' => 'Otomatis: Terlambat',
            'points' => 15, 'bonus_points' => 2, 'trigger_type' => MeritPointTriggerType::AUTOMATIC->value,
            'event' => \App\Enum\MeritPoint\MeritPointEvent::LATE->value, 'is_active' => true,
        ]);

        app(MeritPointService::class)->applyEvent(\App\Enum\MeritPoint\MeritPointEvent::LATE, $user->id, $companyModel->id, 'attendance_record', '01A', 'test 1');
        $this->assertSame(102, $this->balance($user), 'kejadian pertama saldo sudah 100 -> pakai bonus (+2), bukan +15');

        app(MeritPointService::class)->applyEvent(\App\Enum\MeritPoint\MeritPointEvent::LATE, $user->id, $companyModel->id, 'attendance_record', '01B', 'test 2');
        $this->assertSame(104, $this->balance($user), 'kejadian kedua tetap pakai bonus (+2), saldo terus naik');
    }
}
