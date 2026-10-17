<?php

namespace Tests\Feature;

use App\Enum\CustomPermissionEnum;
use App\Enum\WidgetPermissionEnum;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Models\User;
use App\Services\RoleAccess\PermissionTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
     * buat model tanpa Filament Resource mis. EmployeeKoperasi) harus jatuh ke field key
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
            RoleForm::fieldKeyFor('Create:EmployeeKoperasi')
        );

        // Permission Shield biasa (bukan anak SISTEM_*) tetap dikelompokkan per Subject seperti biasa.
        $this->assertSame('employee_has_permissions', RoleForm::fieldKeyFor('View:Employee'));

        // Permission "milik karyawan sendiri" (baseEmployeePermissions) jatuh ke section
        // "Sistem Dasar Karyawan", bukan per-subject / "Lainnya".
        $this->assertSame(
            'sistem_dasar_karyawan_has_permissions',
            RoleForm::fieldKeyFor(CustomPermissionEnum::PELATIHAN_SAYA->value)
        );

        // Fallback terakhir: nama yang tidak cocok grup manapun -> "{subject}_has_permissions".
        $this->assertSame(
            'lainnya_has_permissions',
            RoleForm::fieldKeyFor('permission_ngawur_tanpa_grup')
        );
    }

    /**
     * WidgetPermissionEnum (2026-08-19, instruksi user) HARUS otomatis masuk 1 container baru
     * "Dashboard Widget" di tree /hak-akses - generik dari ::cases(), bukan didaftar manual per
     * case, supaya widget Dashboard baru ke depan langsung ke-grup tanpa sentuh RoleForm lagi.
     */
    public function test_widget_permissions_are_grouped_under_dashboard_widget_container(): void
    {
        $this->assertSame('DashboardWidget', RoleForm::customPermissionSubjectOverrides()[WidgetPermissionEnum::JADWAL_SHIFT_TIM->value]);
        $this->assertSame('Dashboard Widget', RoleForm::subjectContainerOverrides()['DashboardWidget']);

        Permission::firstOrCreate(['name' => WidgetPermissionEnum::JADWAL_SHIFT_TIM->value, 'guard_name' => 'web']);

        $tree = PermissionTreeBuilder::build();
        $dashboardWidgetContainer = collect($tree)->firstWhere('label', 'Dashboard Widget');

        $this->assertNotNull($dashboardWidgetContainer, 'Container "Dashboard Widget" tidak ditemukan di tree.');

        $leafIds = collect($dashboardWidgetContainer['children'])
            ->flatMap(fn (array $subject) => collect($subject['children'])->pluck('id'))
            ->all();

        $this->assertContains(WidgetPermissionEnum::JADWAL_SHIFT_TIM->value, $leafIds);
    }
}
