<?php

namespace Tests\Feature\Sikartap;

use App\Filament\Resources\Sikartap\Pages\ViewSikartap;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 2 — Tes & Wawancara, rewrite Tahap 2/3 detail_pengajuan.php legacy. */
class TesWawancaraTest extends TestCase
{
    use RefreshDatabase;

    private ?Companies $company = null;

    private function actingAsHrd(): User
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hrd = User::factory()->create(['company_id' => $this->company->id]);

        $role = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']),
        ]);
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    private function makeApplicant(): User
    {
        return User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_cannot_schedule_test_before_berkas_approved(): void
    {
        $this->actingAsHrd();
        $sikartap = Sikartap::create(['user_id' => $this->makeApplicant()->id]);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionHidden('scheduleTest');
    }

    public function test_can_schedule_test_and_submit_result_after_berkas_approved(): void
    {
        $this->actingAsHrd();
        $sikartap = Sikartap::create(['user_id' => $this->makeApplicant()->id, 'status_berkas' => 'approved']);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('scheduleTest')
            ->assertActionHidden('submitTestResult')
            ->callAction('scheduleTest', data: [
                'tanggal_tes' => '2026-09-01',
                'waktu_tes' => '09:00',
                'tempat_tes' => 'Aula RS',
                'keterangan' => null,
            ]);

        $sikartap->refresh();
        $this->assertNotNull($sikartap->testSchedule);
        $this->assertSame('Aula RS', $sikartap->testSchedule->tempat_tes);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('submitTestResult')
            ->callAction('submitTestResult', data: [
                'nilai_tes' => 85,
                'nilai_psikotes' => 90,
                'status_tes' => 'lulus',
                'catatan_tes' => 'Bagus',
            ]);

        $sikartap->refresh();
        $this->assertSame('lulus', $sikartap->status_tes);
        $this->assertEquals(85, $sikartap->nilai_tes);
    }

    public function test_interview_only_available_after_test_passed(): void
    {
        $this->actingAsHrd();
        $sikartap = Sikartap::create([
            'user_id' => $this->makeApplicant()->id,
            'status_berkas' => 'approved',
            'status_tes' => 'tidak_lulus',
        ]);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionHidden('scheduleInterview');

        $sikartap->update(['status_tes' => 'lulus']);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('scheduleInterview')
            ->callAction('scheduleInterview', data: [
                'tanggal_wawancara' => '2026-09-05',
                'waktu_wawancara' => '10:00',
                'tempat_wawancara' => 'Ruang HRD',
                'pewawancara' => 'Nadya, S.Psi',
                'pewawancara_2' => null,
                'keterangan' => null,
            ]);

        $sikartap->refresh();
        $this->assertNotNull($sikartap->interviewSchedule);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('submitInterviewResult')
            ->callAction('submitInterviewResult', data: [
                'status_wawancara' => 'disarankan',
                'catatan_wawancara' => 'Komunikatif',
            ]);

        $sikartap->refresh();
        $this->assertSame('disarankan', $sikartap->status_wawancara);
    }
}
