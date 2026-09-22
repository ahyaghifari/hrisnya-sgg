<?php

namespace Tests\Feature\Attendance;

use App\Models\Master\Attendance\AttendanceDevice;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: pivot attendance_device_users dulu punya kolom id ulid yang tidak pernah diisi
 * belongsToMany::attach() -> "Field 'id' doesn't have a default value", jadi device tidak bisa
 * di-assign ke user holding sama sekali. Sekarang pakai composite primary key.
 */
class AttendanceDeviceUserPivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_can_be_assigned_to_a_user_from_another_company(): void
    {
        $holding = Companies::create(['code' => 'HOLD', 'name' => 'Holding', 'location' => 'Jakarta']);
        $unit = Companies::create(['code' => 'UNIT', 'name' => 'Unit Usaha', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'HLD001', 'company_id' => $holding->id]);
        $device = AttendanceDevice::create(['company_id' => $unit->id, 'name' => 'DEVICE-UNIT', 'is_active' => true]);

        $device->users()->attach($user->id);

        $this->assertTrue($device->users()->whereKey($user->id)->exists());
        $this->assertNotNull($device->users()->first()->pivot->created_at, 'withTimestamps() harus mengisi created_at pivot.');
    }

    public function test_the_same_user_cannot_be_assigned_twice_to_one_device(): void
    {
        $company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
        $user = User::factory()->create(['nip' => 'DUP001', 'company_id' => $company->id]);
        $device = AttendanceDevice::create(['company_id' => $company->id, 'name' => 'DEVICE-A', 'is_active' => true]);

        $device->users()->syncWithoutDetaching([$user->id]);
        $device->users()->syncWithoutDetaching([$user->id]);

        $this->assertSame(1, $device->users()->count());
    }
}
