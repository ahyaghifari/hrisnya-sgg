<?php

namespace Tests\Feature\Sikartap;

use App\Livewire\Sikartap\JadwalPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Daftar jadwal Tes & Wawancara lintas pengajuan (belum ada sebelumnya). */
class JadwalPageTest extends TestCase
{
    use RefreshDatabase;

    private ?Companies $company = null;

    private function actingAsHrd(): User
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hrd = User::factory()->create(['company_id' => $this->company->id]);

        $role = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']));
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    public function test_blocked_without_permission(): void
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));

        Livewire::test(JadwalPage::class)->assertForbidden();
    }

    public function test_only_lists_applicants_with_a_schedule(): void
    {
        $this->actingAsHrd();

        $withTest = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);
        $withTest->testSchedule()->create(['tanggal_tes' => '2026-09-01', 'waktu_tes' => '09:00', 'tempat_tes' => 'Aula']);

        $withInterview = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);
        $withInterview->interviewSchedule()->create(['tanggal_wawancara' => '2026-09-05', 'waktu_wawancara' => '10:00', 'tempat_wawancara' => 'Ruang HRD', 'pewawancara' => 'Nadya']);

        $withoutSchedule = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

        $jadwal = Livewire::test(JadwalPage::class)->get('jadwal');

        $this->assertTrue($jadwal->contains('id', $withTest->id));
        $this->assertTrue($jadwal->contains('id', $withInterview->id));
        $this->assertFalse($jadwal->contains('id', $withoutSchedule->id));
    }
}
