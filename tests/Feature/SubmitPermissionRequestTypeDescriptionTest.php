<?php

namespace Tests\Feature;

use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Livewire\SubmitPermissionRequest;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deskripsi jenis izin (m_permission_types.description, kolom baru 2026-07-31) tampil ke
 * pemohon saat memilih jenis izin di dropdown level 2 - kalau kosong, tidak tampil apa-apa.
 */
class SubmitPermissionRequestTypeDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_type_description_is_exposed_when_filled(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $type = PermissionTypes::create([
            'company_id' => $company->id,
            'name' => 'Izin Menikah (Karyawan)',
            'category' => PermissionTypeCategoryEnum::IZIN_KHUSUS->value,
            'default_days' => 3,
            'description' => '3 hari kerja, atau 5 hari kalau lokasi >150km.',
        ]);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('permission_type_id', $type->id)
            ->assertSee('3 hari kerja, atau 5 hari kalau lokasi >150km.');
    }

    public function test_no_description_shown_when_type_has_none(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $type = PermissionTypes::create([
            'company_id' => $company->id,
            'name' => 'Izin Khitan Anak',
            'category' => PermissionTypeCategoryEnum::IZIN_KHUSUS->value,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('permission_type_id', $type->id);

        $this->assertNull($component->get('selectedType')->description);
    }
}
