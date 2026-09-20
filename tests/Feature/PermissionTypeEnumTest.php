<?php

namespace Tests\Feature;

use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Enum\Attendance\PermissionTypeEnum;
use App\Livewire\SubmitPermissionRequest;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Jenis Izin tetap (enum), bukan master per perusahaan - form tetap 2 tingkat: kelompok lalu jenis. */
class PermissionTypeEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_expose_their_own_types_and_flat_groups_have_none(): void
    {
        $this->assertSame(
            [PermissionTypeEnum::TERLAMBAT, PermissionTypeEnum::PULANG_CEPAT, PermissionTypeEnum::MENINGGALKAN_JAM_KERJA],
            PermissionTypeEnum::forGroup(PermissionGroupEnum::MENINGGALKAN_JAM_KERJA)
        );
        $this->assertCount(8, PermissionTypeEnum::forGroup(PermissionGroupEnum::KHUSUS));

        // Tidak Masuk & Sakit: tidak ada dropdown jenis di form (hasSubKategori false).
        $this->assertFalse(PermissionGroupEnum::TIDAK_MASUK_SEHARIAN->hasSubKategori());
        $this->assertFalse(PermissionGroupEnum::SAKIT->hasSubKategori());
    }

    public function test_lookup_helpers_resolve_stored_labels_and_categories(): void
    {
        $this->assertSame(PermissionTypeEnum::TIDAK_MASUK, PermissionTypeEnum::firstForCategory(PermissionTypeCategoryEnum::IZIN_SEHARIAN->value));
        $this->assertSame(PermissionTypeEnum::SAKIT, PermissionTypeEnum::firstForCategory(PermissionTypeCategoryEnum::IZIN_SAKIT->value));
        $this->assertSame(PermissionTypeEnum::MENIKAH_KARYAWAN, PermissionTypeEnum::fromLabel('Izin Menikah (Karyawan)'));
        $this->assertNull(PermissionTypeEnum::fromLabel('Izin Medis')); // data lama/custom
        $this->assertSame(3, PermissionTypeEnum::MENIKAH_KARYAWAN->defaultDays());
        $this->assertNull(PermissionTypeEnum::MUSIBAH->defaultDays());
        $this->assertSame(PermissionTypeCategoryEnum::IZIN_KHUSUS, PermissionTypeEnum::IBADAH_HAJI->category());
        $this->assertCount(13, PermissionTypeEnum::options());
    }

    public function test_type_from_another_group_is_rejected_by_the_form(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        // Jenis Khusus dipilih di kelompok Meninggalkan Jam Kerja -> ditolak.
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'meninggalkan_jam_kerja')
            ->set('permission_type', PermissionTypeEnum::MENIKAH_KARYAWAN->value)
            ->set('date', '2026-08-10')
            ->set('start_time', '08:00')
            ->set('end_time', '09:00')
            ->call('submit')
            ->assertHasErrors(['permission_type']);

        // Kelompok dengan jenis wajib memilih jenis.
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'khusus')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-12')
            ->call('submit')
            ->assertHasErrors(['permission_type']);

        // Kelompok flat tidak butuh jenis.
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10')
            ->call('submit')
            ->assertHasNoErrors(['permission_type']);
    }
}
