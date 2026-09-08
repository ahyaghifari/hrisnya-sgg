<?php

namespace Tests\Feature\Sikartap;

use App\Livewire\Sikartap\WawancaraPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Halaman Wawancara lintas pengajuan — list + penjadwalan massal + isi hasil per baris. */
class WawancaraPageTest extends TestCase
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

        Livewire::test(WawancaraPage::class)->assertForbidden();
    }

    public function test_lists_test_passed_and_already_scheduled_only(): void
    {
        $this->actingAsHrd();

        $ready = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved', 'status_tes' => 'lulus']);

        $scheduled = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved', 'status_tes' => 'tidak_lulus']);
        $scheduled->interviewSchedule()->create(['tanggal_wawancara' => '2026-09-05', 'waktu_wawancara' => '10:00', 'tempat_wawancara' => 'Ruang HRD', 'pewawancara' => 'Nadya']);

        $notReady = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);

        $jadwal = Livewire::test(WawancaraPage::class)->get('jadwal');

        $this->assertTrue($jadwal->contains('id', $ready->id));
        $this->assertTrue($jadwal->contains('id', $scheduled->id));
        $this->assertFalse($jadwal->contains('id', $notReady->id));
    }

    public function test_bulk_schedule_applies_to_eligible_selected_only(): void
    {
        $this->actingAsHrd();

        $a = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved', 'status_tes' => 'lulus']);
        $notEligible = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved']);

        Livewire::test(WawancaraPage::class)
            ->set('selected', [$a->id, $notEligible->id])
            ->call('openScheduleModal')
            ->set('interviewScheduleForm.tanggal_wawancara', '2026-09-12')
            ->set('interviewScheduleForm.waktu_wawancara', '10:00')
            ->set('interviewScheduleForm.tempat_wawancara', 'Ruang HRD')
            ->set('interviewScheduleForm.pewawancara', 'Nadya')
            ->call('saveInterviewSchedule')
            ->assertHasNoErrors();

        $this->assertSame('Ruang HRD', $a->fresh()->interviewSchedule?->tempat_wawancara);
        $this->assertNull($notEligible->fresh()->interviewSchedule);
    }

    public function test_cannot_reschedule_once_interview_result_entered(): void
    {
        $this->actingAsHrd();

        $done = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved', 'status_tes' => 'lulus', 'status_wawancara' => 'disarankan']);
        $done->interviewSchedule()->create(['tanggal_wawancara' => '2026-09-05', 'waktu_wawancara' => '10:00', 'tempat_wawancara' => 'Ruang HRD', 'pewawancara' => 'Nadya']);

        Livewire::test(WawancaraPage::class)
            ->set('selected', [$done->id])
            ->call('openScheduleModal')
            ->set('interviewScheduleForm.tanggal_wawancara', '2026-09-20')
            ->set('interviewScheduleForm.waktu_wawancara', '10:00')
            ->set('interviewScheduleForm.tempat_wawancara', 'Ruang Baru')
            ->set('interviewScheduleForm.pewawancara', 'Budi')
            ->call('saveInterviewSchedule')
            ->assertHasNoErrors();

        $this->assertSame('Ruang HRD', $done->fresh()->interviewSchedule->tempat_wawancara);
    }

    public function test_hrd_can_enter_interview_result_from_list(): void
    {
        $this->actingAsHrd();

        $sikartap = Sikartap::create(['user_id' => $this->applicant()->id, 'status_berkas' => 'approved', 'status_tes' => 'lulus']);
        $sikartap->interviewSchedule()->create(['tanggal_wawancara' => '2026-09-05', 'waktu_wawancara' => '10:00', 'tempat_wawancara' => 'Ruang HRD', 'pewawancara' => 'Nadya']);

        Livewire::test(WawancaraPage::class)
            ->call('openResultModal', $sikartap->id)
            ->set('interviewResultForm.status_wawancara', 'disarankan')
            ->call('saveInterviewResult')
            ->assertHasNoErrors();

        $this->assertSame('disarankan', $sikartap->fresh()->status_wawancara);
    }
}
