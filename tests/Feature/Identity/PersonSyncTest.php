<?php

namespace Tests\Feature\Identity;

use App\Models\Person\Person;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * persons* = salinan satu arah dari users + user_profiles (PersonSyncService, dipicu
 * PersonSyncObserver). Tabel lama tetap sumber; edit di mana pun yang lewat model harus ikut
 * tersalin tanpa mengubah halaman penulisnya.
 */
class PersonSyncTest extends TestCase
{
    use RefreshDatabase;

    private function profile(User $user, array $attrs = []): UserProfiles
    {
        return $user->profile()->create($attrs + [
            'no_nik' => '3201000000000001',
            'bank_name' => 'BCA',
            'bank_account_number' => '123',
            'bank_account_name' => 'Budi',
            'address' => 'Jl. Mawar 1',
            'village' => 'Kelurahan A',
            'phone' => '0811',
            'marital_status' => 'single',
        ]);
    }

    public function test_user_without_profile_gets_person_with_name_only(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $person = Person::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Budi Santoso', $person->nama_lengkap);
        $this->assertNull($person->identity);
        $this->assertCount(0, $person->addresses);
    }

    public function test_creating_profile_copies_to_person_identity_and_address(): void
    {
        $user = User::factory()->create(['name' => 'Budi']);
        $this->profile($user);

        $person = Person::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('single', $person->marital_status);
        $this->assertSame('0811', $person->phone);
        $this->assertSame('3201000000000001', $person->identity->no_nik);
        $this->assertSame('BCA', $person->identity->bank_name);
        $this->assertSame('123', $person->identity->bank_account_number);
        $this->assertSame('Jl. Mawar 1', $person->primaryAddress->address);
        $this->assertSame('utama', $person->primaryAddress->type);
    }

    public function test_editing_profile_via_user_service_updates_person(): void
    {
        $user = User::factory()->create();
        $this->profile($user);

        // Jalur edit tab Data Diri: UserService -> UserRepository::updateProfileUser.
        app(UserService::class)->updateUserProfile($user, ['phone' => '0899', 'bank_account_number' => '999']);

        $person = Person::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('0899', $person->phone);
        $this->assertSame('999', $person->identity->bank_account_number);
        $this->assertSame(1, Person::where('user_id', $user->id)->count());
    }

    public function test_changing_user_name_updates_person_name(): void
    {
        $user = User::factory()->create(['name' => 'Lama']);

        $user->update(['name' => 'Baru']);

        $this->assertSame('Baru', Person::where('user_id', $user->id)->value('nama_lengkap'));
    }

    public function test_backfill_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $user = User::factory()->create();
        $this->profile($user);
        Person::query()->delete(); // simulasi data lama sebelum persons ada

        $this->artisan('identity:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Person::count());

        $this->artisan('identity:backfill')->assertSuccessful();
        $this->artisan('identity:backfill')->assertSuccessful();

        $this->assertSame(1, Person::where('user_id', $user->id)->count());
        $this->assertSame('3201000000000001', Person::where('user_id', $user->id)->first()->identity->no_nik);
    }
}
