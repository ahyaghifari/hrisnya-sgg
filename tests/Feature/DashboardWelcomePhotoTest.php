<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Ganti foto langsung dari welcome card beranda (HandlesProfilePhotoUpload di Dashboard). */
class DashboardWelcomePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_own_photo_from_welcome_card(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Selamat datang kembali')
            ->set('photo', UploadedFile::fake()->image('foto.jpg'))
            ->assertHasNoErrors();

        $path = $user->fresh()->profilePhotoPath();
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }
}
