<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Modal nama panggilan di welcome card beranda: 3-12 huruf, tersimpan ke user_profiles.nickname. */
class DashboardNicknameTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $user->profile()->create([]);
        $this->actingAs($user->fresh());

        return $user;
    }

    public function test_saves_valid_nickname_and_trims_spaces(): void
    {
        $user = $this->user();

        Livewire::test(Dashboard::class)
            ->set('nickname', '  Ahya ')
            ->call('saveNickname')
            ->assertHasNoErrors()
            ->assertDispatched('nickname-saved');

        $this->assertSame('Ahya', $user->profile()->first()->nickname);
    }

    public function test_rejects_too_short_and_too_long(): void
    {
        $user = $this->user();

        Livewire::test(Dashboard::class)->set('nickname', 'Ab')->call('saveNickname')->assertHasErrors(['nickname' => 'min']);
        Livewire::test(Dashboard::class)->set('nickname', 'Abcdefghijklm')->call('saveNickname')->assertHasErrors(['nickname' => 'max']);
        Livewire::test(Dashboard::class)->set('nickname', '')->call('saveNickname')->assertHasErrors(['nickname' => 'required']);

        $this->assertNull($user->profile()->first()->nickname);
    }

    public function test_twelve_letters_is_accepted(): void
    {
        $this->user();

        Livewire::test(Dashboard::class)->set('nickname', 'Abcdefghijkl')->call('saveNickname')->assertHasNoErrors();
    }
}
