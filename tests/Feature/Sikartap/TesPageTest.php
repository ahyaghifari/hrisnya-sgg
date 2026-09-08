<?php

namespace Tests\Feature\Sikartap;

use App\Livewire\Sikartap\TesPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Halaman Tes lintas pengajuan — list + penjadwalan massal + isi hasil per baris. */
class TesPageTest extends TestCase
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
            Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']),
        ]);
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    private function applicant(): User
    {
        return User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_blocked_without_permission(): void
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));

        Livewire::test(TesPage::class)->assertForbidden();
    }

    public function test_lists_berkas_approved_and_already_scheduled_only(): void
    {
        $this->actingAsHrd();

        $ready = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);

        $scheduled = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'pending']);
        $scheduled->testSchedule()->create(['tanggal_tes' => '2026-09-01', 'waktu_tes' => '09:00', 'tempat_tes' => 'Aula']);

        $notReady = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'pending']);

        $jadwal = Livewire::test(TesPage::class)->get('jadwal');

        $this->assertTrue($jadwal->contains('id', $ready->id));
        $this->assertTrue($jadwal->contains('id', $scheduled->id));
        $this->assertFalse($jadwal->contains('id', $notReady->id));
    }

    public function test_bulk_schedule_applies_to_eligible_selected_only(): void
    {
        $this->actingAsHrd();

        $a = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);
        $b = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);
        $notEligible = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'pending']);

        Livewire::test(TesPage::class)
            ->set('selected', [$a->id, $b->id, $notEligible->id])
            ->call('openScheduleModal')
            ->set('testScheduleForm.tanggal_tes', '2026-09-10')
            ->set('testScheduleForm.waktu_tes', '09:00')
            ->set('testScheduleForm.tempat_tes', 'Aula RS')
            ->call('saveTestSchedule')
            ->assertHasNoErrors();

        $this->assertSame('Aula RS', $a->fresh()->testSchedule?->tempat_tes);
        $this->assertSame('Aula RS', $b->fresh()->testSchedule?->tempat_tes);
        $this->assertNull($notEligible->fresh()->testSchedule);
    }

    public function test_result_entry_gated_on_schedule_exists(): void
    {
        $this->actingAsHrd();

        $noSchedule = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);

        Livewire::test(TesPage::class)
            ->call('openResultModal', $noSchedule->id)
            ->set('testResultForm.nilai_tes', 80)
            ->set('testResultForm.nilai_psikotes', 85)
            ->set('testResultForm.status_tes', 'lulus')
            ->call('saveTestResult')
            ->assertForbidden();

        $this->assertSame('pending', $noSchedule->fresh()->status_tes);
    }

    public function test_cannot_reschedule_once_test_result_entered(): void
    {
        $this->actingAsHrd();

        $done = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved', 'status_tes' => 'lulus']);
        $done->testSchedule()->create(['tanggal_tes' => '2026-09-01', 'waktu_tes' => '09:00', 'tempat_tes' => 'Aula']);

        Livewire::test(TesPage::class)
            ->set('selected', [$done->id])
            ->call('openScheduleModal')
            ->set('testScheduleForm.tanggal_tes', '2026-09-20')
            ->set('testScheduleForm.waktu_tes', '09:00')
            ->set('testScheduleForm.tempat_tes', 'Aula Baru')
            ->call('saveTestSchedule')
            ->assertHasNoErrors();

        $this->assertSame('Aula', $done->fresh()->testSchedule->tempat_tes);
    }

    public function test_hrd_can_enter_test_result_from_list(): void
    {
        $this->actingAsHrd();

        $sikartap = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);
        $sikartap->testSchedule()->create(['tanggal_tes' => '2026-09-01', 'waktu_tes' => '09:00', 'tempat_tes' => 'Aula']);

        Livewire::test(TesPage::class)
            ->call('openResultModal', $sikartap->id)
            ->set('testResultForm.nilai_tes', 88)
            ->set('testResultForm.nilai_psikotes', 90)
            ->set('testResultForm.status_tes', 'lulus')
            ->call('saveTestResult')
            ->assertHasNoErrors();

        $this->assertSame('lulus', $sikartap->fresh()->status_tes);
    }
}
