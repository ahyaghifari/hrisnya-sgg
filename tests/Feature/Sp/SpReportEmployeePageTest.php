<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportStatus;
use App\Livewire\Sp\BeritaAcaraSayaPage;
use App\Livewire\Pembinaan\PembinaanSayaList;
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
use Tests\Support\SpFixtures;
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
        config(['sp.digital_signing' => true]); // alur TTD digital BA (default ditahan, diuji di SpReportUploadTest)

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
            'invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat', 'dasar_items' => ['Rekap'],
        ]);
    }

    /** Draft dengan seluruh isian HC lengkap (siap FIX). */
    private function completeDraft(array $override = []): SpReport
    {
        $report = $this->draft();
        $this->service->updateDraft($report, $this->hc, $override + [
            'subject_clarification' => 'macet', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f',
        ]);

        return $report->fresh();
    }

    public function test_draft_is_hidden_and_other_employees_are_blocked(): void
    {
        $report = $this->completeDraft();

        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report])->assertNotFound();

        $this->service->sendForSigning($report->fresh(), $this->hc);

        Livewire::actingAs($this->knower)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])->assertForbidden();
        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])->assertOk();
    }

    public function test_employee_sees_hc_content_only_after_sent_for_signing_and_can_reclarify(): void
    {
        $report = $this->completeDraft(['analysis' => 'ANALISIS-RAHASIA']);

        Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->assertDontSee('ANALISIS-RAHASIA');

        $this->service->sendForSigning($report->fresh(), $this->hc);

        $page = Livewire::actingAs($this->employee)->test(BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->assertSee('ANALISIS-RAHASIA')
            ->assertSee('Menunggu pembuat')
            ->set('alasan_ajukan_ulang', 'Bukan itu maksud saya')
            ->call('requestReclarification');

        $this->assertSame(SpReportStatus::DRAFT, $report->fresh()->status);
    }

    public function test_draft_sp_is_hidden_from_the_employee_until_issued(): void
    {
        $sp = \Tests\Support\SpFixtures::draftSp($this->hc, $this->hc->company_id, $this->employee->id, $this->knower->id, 'SP-DRAFT-777');

        Livewire::actingAs($this->employee)->test(PembinaanSayaList::class)->assertDontSee('SP-DRAFT-777')->assertSee('Belum ada data');
        Livewire::actingAs($this->employee)->test(\App\Livewire\Sp\SuratPeringatanDetailPage::class, ['record' => $sp])->assertNotFound();
        $this->actingAs($this->employee)->get(route('sp.saya.preview', $sp))->assertForbidden();
    }

    public function test_after_creator_signs_employee_and_knower_are_notified_and_list_shows_report(): void
    {
        $report = $this->completeDraft();
        $this->service->sendForSigning($report->fresh(), $this->hc);

        Notification::fake();
        app(DocumentSigningService::class)->sign($report->fresh()->document, $this->hc);

        Notification::assertSentTo($this->knower, SpReportUpdated::class);
        Notification::assertNotSentTo($this->employee, SpReportUpdated::class);

        app(DocumentSigningService::class)->sign($report->fresh()->document, $this->knower);
        Notification::assertSentTo($this->employee, SpReportUpdated::class);

        Livewire::actingAs($this->employee)->test(PembinaanSayaList::class)->assertSee('Berita Acara');
    }
}
