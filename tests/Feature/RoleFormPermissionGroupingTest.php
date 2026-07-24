<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RoleForm men-generate CheckboxList per permission Shield ("Verb:Subject") dan
 * mengelompokkannya berdasarkan navigationGroup Resource pemilik model tsb — regresi di
 * sini biasanya berupa exception saat render (bukan assertion salah), jadi cukup pastikan
 * halaman edit Role render 200 dengan katalog permission Shield asli.
 */
class RoleFormPermissionGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_role_page_renders_with_shield_permission_catalog(): void
    {
        $this->artisan('shield:generate', [
            '--panel' => 'admin',
            '--all' => true,
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        $admin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole($superAdminRole);

        $editableRole = Role::create(['name' => 'Kepegawaian', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->get(RoleResource::getUrl('edit', ['record' => $editableRole]))
            ->assertOk();
    }

    /**
     * SISTEM_* (portal karyawan) + permission anaknya (EDIT_* custom, atau Shield Verb:Subject
     * buat model tanpa Filament Resource mis. UserKoperasi) harus jatuh ke field key
     * "portal_{sistem}_has_permissions" yang SAMA baik saat render (RoleForm) maupun preload
     * (EditRole::mutateFormDataBeforeFill) — kalau field key-nya beda, checkbox tampil kosong
     * walau role sudah punya permission itu (lihat komentar di EditRole.php).
     */
    public function test_field_key_for_groups_portal_permissions_together(): void
    {
        $this->assertSame(
            'portal_sistem_keuangan_has_permissions',
            RoleForm::fieldKeyFor(CustomPermissionEnum::SISTEM_KEUANGAN->value)
        );
        $this->assertSame(
            'portal_sistem_keuangan_has_permissions',
            RoleForm::fieldKeyFor(CustomPermissionEnum::EDIT_MAPPING_KOMPONEN_GAJI->value)
        );
        $this->assertSame(
            'portal_sistem_keuangan_has_permissions',
            RoleForm::fieldKeyFor('Create:UserKoperasi')
        );

        // Permission Shield biasa (bukan anak SISTEM_*) tetap dikelompokkan per Subject seperti biasa.
        $this->assertSame('employee_has_permissions', RoleForm::fieldKeyFor('View:Employee'));

        // Permission manual yang bukan bagian grup portal manapun tetap jatuh ke "Lainnya".
        $this->assertSame(
            'lainnya_has_permissions',
            RoleForm::fieldKeyFor(CustomPermissionEnum::PELATIHAN_SAYA->value)
        );
    }
}
