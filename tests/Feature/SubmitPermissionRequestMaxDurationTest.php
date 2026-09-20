<?php

namespace Tests\Feature;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Livewire\SubmitPermissionRequest;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Izin Meninggalkan Jam Kerja (apa pun sub-jenisnya) dibatasi maksimal durasi lewat setting
 * izin_meninggalkan_jam_kerja_max_jam - default 3 jam, dikonfirmasi user 2026-07-31.
 */
class SubmitPermissionRequestMaxDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duration_within_default_limit_passes(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::TERLAMBAT->value)
            ->set('date', '2026-08-10')
            ->set('start_time', '08:00')
            ->set('end_time', '10:00')
            ->call('submit')
            ->assertHasNoErrors();
    }

    public function test_duration_exceeding_default_limit_fails(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::TERLAMBAT->value)
            ->set('date', '2026-08-10')
            ->set('start_time', '08:00')
            ->set('end_time', '11:30') // 3.5 jam, melebihi default 3 jam
            ->call('submit')
            ->assertHasErrors(['end_time']);
    }

    public function test_zero_setting_means_unlimited(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => AttendanceSettingEnum::IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM->value],
            ['module' => 'attendance', 'type' => 'integer', 'label' => 'Maksimal Jam Izin Meninggalkan Jam Kerja', 'value' => '0']
        );

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::TERLAMBAT->value)
            ->set('date', '2026-08-10')
            ->set('start_time', '08:00')
            ->set('end_time', '15:00') // 7 jam, tapi setting 0 = tidak dibatasi
            ->call('submit')
            ->assertHasNoErrors();
    }
}
