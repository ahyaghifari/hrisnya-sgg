<?php

namespace Tests\Feature\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\Sikartap\Pages\ViewSikartap;
use App\Livewire\Sikartap\Mcu\AsesmenPerawatPage;
use App\Livewire\Sikartap\Mcu\HasilLaboratoriumPage;
use App\Livewire\Sikartap\Mcu\HasilRadiologiPage;
use App\Livewire\Sikartap\Mcu\PemeriksaanDokterPage;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 3 — MCU paralel (perawat/lab/radiologi/dokter), rewrite Tahap 4 detail_pengajuan.php legacy. */
class McuTest extends TestCase
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

    private function actingAsStaff(CustomPermissionEnum $permission): User
    {
        $this->company ??= Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $this->company->id]);

        $role = Role::firstOrCreate(['name' => 'Staff-' . $permission->value], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_SIKARTAP->value, 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']),
        ]);
        $staff->assignRole($role);

        $this->actingAs($staff);

        return $staff;
    }

    private function makeSikartapReadyForMcu(): Sikartap
    {
        $this->company ??= Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $applicant = User::factory()->create(['company_id' => $this->company->id]);

        return Sikartap::create([
            'user_id' => $applicant->id,
            'status_berkas' => 'approved',
            'berkas_lengkap' => true,
            'status_tes' => 'lulus',
            'status_wawancara' => 'disarankan',
        ]);
    }

    public function test_mcu_form_blocked_without_permission(): void
    {
        $this->actingAsHrd(); // HRD tidak dikasih permission INPUT_MCU_*
        $sikartap = $this->makeSikartapReadyForMcu();

        Livewire::test(AsesmenPerawatPage::class, ['sikartap' => $sikartap])
            ->assertForbidden();
    }

    public function test_mcu_form_blocked_before_wawancara_disarankan(): void
    {
        $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT);
        $sikartap = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);

        Livewire::test(AsesmenPerawatPage::class, ['sikartap' => $sikartap])
            ->assertForbidden();
    }

    public function test_nurse_can_submit_assessment(): void
    {
        $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT);
        $sikartap = $this->makeSikartapReadyForMcu();

        Livewire::test(AsesmenPerawatPage::class, ['sikartap' => $sikartap])
            ->set('form.tinggi_badan', 170)
            ->set('form.berat_badan', 65)
            ->set('form.tekanan_darah', '120/80')
            ->set('form.nadi', 80)
            ->set('form.suhu', 36.5)
            ->set('form.pernafasan', 20)
            ->call('save');

        $this->assertNotNull($sikartap->fresh()->mcuNurseAssessment);
        $this->assertEquals(170, $sikartap->fresh()->mcuNurseAssessment->tinggi_badan);
    }

    /** Instruksi user 2026-08-26: data yang SUDAH diisi cuma bisa diedit ulang oleh pemegang
     * EDIT_INPUT_MCU - staff biasa (INPUT_MCU_* doang) diblokir begitu ada isinya. */
    public function test_nurse_without_edit_permission_cannot_reopen_filled_assessment(): void
    {
        $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT);
        $sikartap = $this->makeSikartapReadyForMcu();
        $sikartap->mcuNurseAssessment()->create(['tinggi_badan' => 170, 'berat_badan' => 65, 'tekanan_darah' => '120/80', 'nadi' => 80, 'suhu' => 36.5, 'pernafasan' => 20]);

        Livewire::test(AsesmenPerawatPage::class, ['sikartap' => $sikartap])
            ->assertForbidden();
    }

    public function test_staff_with_edit_permission_can_correct_filled_assessment(): void
    {
        $staff = $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT);
        $staff->givePermissionTo(Permission::firstOrCreate(['name' => CustomPermissionEnum::EDIT_INPUT_MCU->value, 'guard_name' => 'web']));

        $sikartap = $this->makeSikartapReadyForMcu();
        $sikartap->mcuNurseAssessment()->create(['tinggi_badan' => 170, 'berat_badan' => 65, 'tekanan_darah' => '120/80', 'nadi' => 80, 'suhu' => 36.5, 'pernafasan' => 20]);

        Livewire::test(AsesmenPerawatPage::class, ['sikartap' => $sikartap])
            ->set('form.tinggi_badan', 175)
            ->call('save');

        $this->assertEquals(175, $sikartap->fresh()->mcuNurseAssessment->tinggi_badan);
    }

    public function test_lab_staff_can_submit_result(): void
    {
        $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_LABORATORIUM);
        $sikartap = $this->makeSikartapReadyForMcu();

        Livewire::test(HasilLaboratoriumPage::class, ['sikartap' => $sikartap])
            ->set('form.hbsag', 'Non Reaktif')
            ->set('form.hiv', 'Non Reaktif')
            ->call('save');

        $this->assertSame('Non Reaktif', $sikartap->fresh()->mcuLabResult->hbsag);
    }

    public function test_radiology_staff_can_submit_result(): void
    {
        $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_RADIOLOGI);
        $sikartap = $this->makeSikartapReadyForMcu();

        Livewire::test(HasilRadiologiPage::class, ['sikartap' => $sikartap])
            ->set('form.foto_thorax', 'Normal')
            ->call('save');

        $this->assertSame('Normal', $sikartap->fresh()->mcuRadiologyResult->foto_thorax);
    }

    public function test_doctor_can_submit_examination(): void
    {
        $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER);
        $sikartap = $this->makeSikartapReadyForMcu();

        Livewire::test(PemeriksaanDokterPage::class, ['sikartap' => $sikartap])
            ->set('form.diagnosa', 'Sehat')
            ->set('form.rekomendasi', 'Fit untuk bekerja')
            ->call('save');

        $this->assertSame('Fit untuk bekerja', $sikartap->fresh()->mcuDoctorExamination->rekomendasi);
    }

    public function test_mcu_decision_action_requires_doctor_examination_first(): void
    {
        $this->actingAsHrd();
        $sikartap = $this->makeSikartapReadyForMcu();

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionHidden('submitMcuDecision');

        $sikartap->mcuDoctorExamination()->create(['rekomendasi' => 'Fit untuk bekerja']);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->assertActionVisible('submitMcuDecision')
            ->callAction('submitMcuDecision', data: [
                'status_mcu' => 'fit',
                'catatan_mcu' => 'Fit untuk bekerja',
            ]);

        $sikartap->refresh();
        $this->assertSame('fit', $sikartap->status_mcu);
        $this->assertTrue($sikartap->isEligibleForDirectorDecision());
    }

    /** Antrian MCU sekarang widget tabel di DashboardSikartapPage (bukan halaman terpisah lagi,
     * instruksi user 2026-08-26), staff MCU-only cuma lihat baris yang beneran bisa dia aksi. */
    public function test_dashboard_table_only_lists_applicants_awaiting_mcu(): void
    {
        $nurse = $this->actingAsStaff(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT);
        $waiting = $this->makeSikartapReadyForMcu();
        $notYetInterviewed = Sikartap::create(['user_id' => User::factory()->create(['company_id' => $this->company->id])->id]);
        $alreadyDecided = $this->makeSikartapReadyForMcu();
        $alreadyDecided->update(['status_mcu' => 'fit']);

        $this->actingAs($nurse);

        $queue = Livewire::test(\App\Livewire\Sikartap\DashboardSikartapPage::class)->get('pengajuan');

        $this->assertTrue($queue->contains('id', $waiting->id));
        $this->assertFalse($queue->contains('id', $notYetInterviewed->id));
        $this->assertFalse($queue->contains('id', $alreadyDecided->id));
    }
}
