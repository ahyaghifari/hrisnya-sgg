<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportStatus;
use App\Livewire\Sp\BeritaAcaraSayaPage;
use App\Livewire\Sp\SuratPeringatanPage;
use App\Models\Master\Companies;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use App\Services\Document\DocumentSigningService;
use App\Services\Sp\SpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Sisi karyawan pada Berita Acara alur baru (instruksi user 2026-10-01) + notifikasinya. */
class SpReportEmployeePageTest extends TestCase
{
    use RefreshDatabase;

    private User $hc;
    private User $employee;
    private User $knower;
    private SpReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Companies::create(['code' => 'SPE', 'name' => 'Company SPE', 'location' => 'Test', 'is_active' => true]);

        $perms = [CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp'];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->hc = User::factory()->create(['company_id' => $company->id]);
        $this->hc->givePermissionTo($perms);
        $this->employee = User::factory()->create(['company_id' => $company->id]);
        $this->knower = User::factory()->create(['company_id' => $company->id]);
        $this->service = app(SpReportService::class);
    }

    private function draft(): SpReport
    {
        return $this->service->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat', 'dasar_klarifikasi' => 'Rekap',
        ]);
    }

    public function test_draft_is_hidden_and_other_employees_are_blocked(): void
    {
        $report = $this->draft();

        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report])->assertNotFound();

        $this->service->requestClarification($report, $this->hc);

        Livewire::actingAs($this->knower)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])->assertForbidden();
        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])->assertOk();
    }

    public function test_employee_submits_clarification_and_hc_is_notified(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);

        Notification::fake();

        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->set('klarifikasi', '')
            ->call('submitClarification');
        $this->assertSame(SpReportStatus::MENUNGGU_KLARIFIKASI, $report->fresh()->status); // kosong ditolak

        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->set('klarifikasi', 'macet di jalan')
            ->call('submitClarification');

        $this->assertSame(SpReportStatus::KLARIFIKASI_DIAJUKAN, $report->fresh()->status);
        $this->assertSame('macet di jalan', $report->fresh()->subject_clarification);
        Notification::assertSentTo($this->hc, SpReportUpdated::class);
    }

    public function test_employee_sees_hc_content_only_after_sent_for_signing_and_can_reclarify(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);
        $this->service->submitClarification($report->fresh(), $this->employee, 'macet');
        $this->service->saveAnalysis($report->fresh(), $this->hc, [
            'klarifikasi_parafrase' => 'PARAFRASE-RAHASIA', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f',
        ]);

        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->assertDontSee('PARAFRASE-RAHASIA');

        $this->service->sendForSigning($report->fresh(), $this->hc);

        $page = Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->assertSee('PARAFRASE-RAHASIA')
            ->assertSee('Setuju')
            ->set('alasan_ajukan_ulang', 'Bukan itu maksud saya')
            ->call('requestReclarification');

        $this->assertSame(SpReportStatus::MENUNGGU_KLARIFIKASI, $report->fresh()->status);
        $page->assertSee('Klarifikasi Anda');
    }

    public function test_after_employee_signs_creator_and_knower_are_notified_and_list_shows_report(): void
    {
        $report = $this->draft();
        $this->service->requestClarification($report, $this->hc);
        $this->service->submitClarification($report->fresh(), $this->employee, 'macet');
        $this->service->saveAnalysis($report->fresh(), $this->hc, [
            'klarifikasi_parafrase' => 'p', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f',
        ]);
        $this->service->sendForSigning($report->fresh(), $this->hc);

        Notification::fake();
        app(DocumentSigningService::class)->sign($report->fresh()->document, $this->employee);

        Notification::assertSentTo($this->hc, SpReportUpdated::class);
        Notification::assertSentTo($this->knower, SpReportUpdated::class);

        Livewire::actingAs($this->employee)->test(SuratPeringatanPage::class)->assertSee('Berita Acara');
    }
}
