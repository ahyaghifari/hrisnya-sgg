<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Foto profil = atribut akun (users.profile_picture), bukan employees/user_profiles.
 * User::profilePhotoPath() menormalkan bentuk data lama.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_lives_on_users_not_on_employees(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'profile_picture'));
        $this->assertFalse(Schema::hasColumn('employees', 'profile_picture'));
    }

    public function test_profile_photo_path_normalizes_every_stored_shape(): void
    {
        $user = new User();

        $user->setRawAttributes(['profile_picture' => json_encode(['profile-pictures/a.jpg'])]);
        $this->assertSame('profile-pictures/a.jpg', $user->profilePhotoPath(), 'array path (bentuk normal)');

        $user->setRawAttributes(['profile_picture' => json_encode(json_encode(['profile-pictures/b.jpg']))]);
        $this->assertSame('profile-pictures/b.jpg', $user->profilePhotoPath(), 'string JSON (bentuk lama, double-encoded)');

        $user->setRawAttributes(['profile_picture' => json_encode('profile-pictures/c.jpg')]);
        $this->assertSame('profile-pictures/c.jpg', $user->profilePhotoPath(), 'string path biasa');

        foreach ([null, json_encode([]), json_encode(['']), json_encode([null])] as $empty) {
            $user->setRawAttributes(['profile_picture' => $empty]);
            $this->assertNull($user->profilePhotoPath(), 'kosong -> null, bukan satu karakter pertama');
        }
    }

    public function test_writing_the_photo_through_the_model_is_readable_back(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->fresh()->profilePhotoPath());

        $user->update(['profile_picture' => ['profile-pictures/x.jpg']]);

        $this->assertSame('profile-pictures/x.jpg', $user->fresh()->profilePhotoPath());
        $this->assertSame(['profile-pictures/x.jpg'], json_decode(DB::table('users')->where('id', $user->id)->value('profile_picture'), true));
    }

    public function test_photo_is_not_copied_into_employees_by_the_sync(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['profile_picture' => ['profile-pictures/old.jpg']]);

        $employee = $user->fresh()->employee;

        $this->assertNotNull($employee);
        $this->assertArrayNotHasKey('profile_picture', $employee->getAttributes());
    }
}
