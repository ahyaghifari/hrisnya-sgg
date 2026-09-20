<?php

namespace Tests\Feature;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Livewire\SubmitPermissionRequest;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deskripsi jenis izin (PermissionTypeEnum::description()) tampil ke
 * pemohon saat memilih jenis izin di dropdown level 2 - kalau kosong, tidak tampil apa-apa.
 */
class SubmitPermissionRequestTypeDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_type_description_is_exposed_when_filled(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('permission_type', PermissionTypeEnum::MENIKAH_KARYAWAN->value)
            ->assertSee('geser tanggal selesai manual kalau berlaku');
    }

    public function test_no_description_shown_when_type_has_none(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        $component = Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::TERLAMBAT->value);

        $this->assertNull($component->get('selectedType')->description());
    }
}
