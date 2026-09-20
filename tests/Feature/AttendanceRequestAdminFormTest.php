<?php

namespace Tests\Feature;

use App\Filament\Resources\AttendanceRequests\Pages\CreateAttendanceRequest;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Form pengajuan admin (panel /admin): jenis Cuti/Izin/Lembur berasal dari enum tetap, bukan
 * master per perusahaan (halaman master Jenis Cuti/Izin/Lembur sudah dihapus).
 */
class AttendanceRequestAdminFormTest extends TestCase
{
    use RefreshDatabase;

    private function field($schema, string $suffix)
    {
        $flat = $schema->getFlatFields(withHidden: true);
        $key = collect(array_keys($flat))->first(fn ($k) => str_ends_with($k, $suffix));

        return $flat[$key] ?? null;
    }

    public function test_type_options_come_from_fixed_enums(): void
    {
        Companies::forceCreate(['id' => '01J2F0C8KQ9M7D4S8RBX6E1ZVP', 'code' => 'GLB', 'name' => 'Global Company', 'location' => 'Global', 'is_active' => true]);
        $admin = User::factory()->create(['company_id' => '01J2F0C8KQ9M7D4S8RBX6E1ZVP']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        $livewire = Livewire::actingAs($admin)->test(CreateAttendanceRequest::class);
        $schema = $livewire->instance()->getSchema('form');

        $this->assertSame(
            ['Cuti Tahunan', 'Cuti Haid', 'Cuti Melahirkan', 'Cuti Keguguran'],
            array_values($this->field($schema, 'leave_type')->getOptions())
        );
        $this->assertSame(
            ['Lembur Biasa', 'Lembur Khusus/Proyek'],
            array_values($this->field($schema, 'overtime_type')->getOptions())
        );

        // Jenis izin (level 2) muncul sesuai kelompok yang dipilih.
        $livewire->set('data.group', 'khusus');
        $this->assertCount(8, $this->field($livewire->instance()->getSchema('form'), 'permission_type')->getOptions());

        $livewire->set('data.group', 'meninggalkan_jam_kerja');
        $this->assertSame(
            ['Izin Terlambat', 'Izin Pulang Lebih Awal', 'Izin Meninggalkan Jam Kerja'],
            array_values($this->field($livewire->instance()->getSchema('form'), 'permission_type')->getOptions())
        );
    }
}
