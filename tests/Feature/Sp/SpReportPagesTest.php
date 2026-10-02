<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportStatus;
use App\Livewire\Sp\BeritaAcaraDetailPage;
use App\Livewire\Sp\CreateBeritaAcaraPage;
use App\Models\Master\Companies;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Services\Sp\SpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Halaman HC untuk Berita Acara alur baru (instruksi user 2026-10-01). */
class SpReportPagesTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private User $hc;
    private User $employee;
    private User $knower;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Companies::create(['code' => 'SPP', 'name' => 'Company SPP', 'location' => 'Test', 'is_active' => true]);

        $perms = [CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp', 'ViewAny:EmployeeSp'];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->hc = User::factory()->create(['company_id' => $this->company->id]);
        $this->hc->givePermissionTo($perms);
        $this->employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->knower = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_create_page_makes_draft_and_requires_dasar_and_kejadian(): void
    {
        Livewire::actingAs($this->hc)->test(CreateBeritaAcaraPage::class)
            ->set('user_id', $this->employee->id)
            ->set('knows_by', $this->knower->id)
            ->call('save')
            ->assertHasErrors(['dasar_klarifikasi', 'kejadian']);

        Livewire::actingAs($this->hc)->test(CreateBeritaAcaraPage::class)
            ->set('user_id', $this->employee->id)
            ->set('knows_by', $this->knower->id)
            ->set('dasar_klarifikasi', 'Rekap presensi')
            ->set('kejadian', 'Terlambat 5 kali')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SpReportStatus::DRAFT, SpReport::firstOrFail()->status);
    }

    public function test_employee_without_permission_cannot_open_hc_pages(): void
    {
        Livewire::actingAs($this->employee)->test(CreateBeritaAcaraPage::class)->assertForbidden();
    }

    public function test_hc_walks_the_whole_flow_through_the_detail_page(): void
    {
        $service = app(SpReportService::class);
        $report = $service->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat', 'dasar_klarifikasi' => 'Rekap',
        ]);

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report])
            ->assertSee('Minta Klarifikasi Karyawan')
            ->call('requestClarification')
            ->assertSee('Menunggu')
            ->assertSee($this->employee->name);

        $service->submitClarification($report->fresh(), $this->employee, 'macet di jalan');

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report->fresh()])
            ->assertSee('macet di jalan')
            ->set('klarifikasi_parafrase', 'Terlambat karena kemacetan')
            ->set('analysis', 'Berulang')
            ->set('kesimpulan', 'Melanggar')
            ->set('followup_plan', '')
            ->call('sendForSigning')
            ->assertSee('Kirim untuk Tanda Tangan'); // rencana tindak lanjut kosong -> ditolak, tetap di status yang sama

        $this->assertSame(SpReportStatus::KLARIFIKASI_DIAJUKAN, $report->fresh()->status);

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report->fresh()])
            ->set('klarifikasi_parafrase', 'Terlambat karena kemacetan')
            ->set('analysis', 'Berulang')
            ->set('kesimpulan', 'Melanggar')
            ->set('followup_plan', 'SP1')
            ->call('sendForSigning');

        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->fresh()->status);
    }

    public function test_unrelated_user_and_legacy_report_are_blocked(): void
    {
        $report = app(SpReportService::class)->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'x', 'dasar_klarifikasi' => 'y',
        ]);

        Livewire::actingAs($this->employee)->test(BeritaAcaraDetailPage::class, ['record' => $report])->assertForbidden();

        $report->forceFill(['status' => null])->save();
        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report])->assertNotFound();
    }
}
