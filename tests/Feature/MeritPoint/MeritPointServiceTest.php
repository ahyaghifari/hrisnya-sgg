<?php

namespace Tests\Feature\MeritPoint;

use App\Models\User;
use App\Models\UserHrBalance;
use App\Services\MeritPoint\MeritPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saldo Merit Point mulai dari 100 (karyawan tanpa transaksi dianggap skor penuh - lihat widget
 * tab Karir) dan dijepit 0..100 SETIAP transaksi, seperti contoh di dokumen acuan §9
 * (100 +15 = 100, lalu -5 = 95) - bukan SUM lalu dijepit sekali di akhir, yang bikin penalti
 * "tertelan" reward yang tadinya kelebihan di atas 100.
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

    public function test_reward_at_full_score_is_wasted_and_later_penalty_still_counts(): void
    {
        $user = User::factory()->create();

        $this->add($user, 10); // sudah 100, kelebihan hangus
        $this->assertSame(100, $this->balance($user));

        $this->add($user, -5);
        $this->assertSame(95, $this->balance($user));
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
        $this->assertSame(100, $service->previewBalance($user->id, 500));
        $this->assertSame(0, $service->previewBalance($user->id, -500));
        $this->assertSame(70, $this->balance($user), 'preview tidak boleh mengubah saldo tersimpan');
    }
}
