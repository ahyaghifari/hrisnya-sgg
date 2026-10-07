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
        \Tests\Support\SpFixtures::makeDepartmentHead($this->knower);
    }

    public function test_create_page_makes_draft_and_requires_dasar_and_kejadian(): void
    {
        Livewire::actingAs($this->hc)->test(CreateBeritaAcaraPage::class)
            ->set('user_id', $this->employee->id)
            ->set('knows_by', $this->knower->id)
            ->call('save')
            ->assertHasErrors(['dasar_items.0.reason', 'kejadian']);

        $reason = \App\Models\Sp\SpReason::create(['company_id' => $this->company->id, 'name' => 'Keterlambatan']);

        Livewire::actingAs($this->hc)->test(CreateBeritaAcaraPage::class)
            ->set('user_id', $this->employee->id)
            ->set('knows_by', $this->knower->id)
            ->set('dasar_items', [['reason' => $reason->id, 'note' => 'Rekap presensi']])
            ->set('kejadian', 'Terlambat 5 kali')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SpReportStatus::DRAFT, SpReport::firstOrFail()->status);
        $this->assertSame('Keterlambatan — Rekap presensi', (string) SpReport::firstOrFail()->dasarKlarifikasiHtml());
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
            'kejadian' => 'Terlambat', 'dasar_items' => ['Rekap'],
        ]);

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report])
            ->assertSee('FIX Berita Acara')
            ->assertSee($this->employee->name)
            ->set('subject_clarification', 'macet di jalan')
            ->call('saveDraft');

        $this->assertSame('macet di jalan', $report->fresh()->subject_clarification);

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report->fresh()])
            ->assertSee('macet di jalan')
            ->set('analysis', 'Berulang')
            ->set('kesimpulan', 'Melanggar')
            ->set('followup_plan', '')
            ->call('sendForSigning')
            ->assertSee('Rencana Tindak Lanjut'); // rencana tindak lanjut kosong -> ditolak, tetap di status yang sama

        $this->assertSame(SpReportStatus::DRAFT, $report->fresh()->status);

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report->fresh()])
            ->set('analysis', 'Berulang')
            ->set('kesimpulan', 'Melanggar')
            ->set('followup_plan', 'SP1')
            ->call('sendForSigning');

        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->fresh()->status);
    }

    public function test_signed_roles_show_qr_box_and_unsigned_stay_pending(): void
    {
        $service = app(SpReportService::class);
        $report = $service->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'x', 'dasar_items' => ['y'],
        ]);
        $service->updateDraft($report, $this->hc, ['subject_clarification' => 'z', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f']);
        $service->sendForSigning($report->fresh(), $this->hc);

        $page = fn () => Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report->fresh()]);

        $page()->assertSee('Dibuat oleh')->assertSee('Karyawan yang Bersangkutan')->assertSee('Belum menandatangani')->assertDontSee('data:image/svg+xml', false);

        app(\App\Services\Document\DocumentSigningService::class)->sign($report->fresh()->document, $this->hc);

        $page()->assertSee('data:image/svg+xml', false)->assertSee($this->hc->name)->assertSee('Belum menandatangani');
    }

    public function test_finished_ba_shows_one_section_and_pdf_only_after_selesai(): void
    {
        $service = app(SpReportService::class);
        $report = $service->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'Kejadian-X', 'dasar_items' => ['Dasar-Y'],
        ]);
        $service->updateDraft($report, $this->hc, ['subject_clarification' => 'Klarifikasi-Z', 'analysis' => 'Analisis-A', 'kesimpulan' => 'k', 'followup_plan' => 'f']);
        $service->sendForSigning($report->fresh(), $this->hc);

        $this->actingAs($this->hc)->get(route('ba.preview', $report))->assertForbidden();

        $signing = app(\App\Services\Document\DocumentSigningService::class);
        foreach ([$this->hc, $this->knower, $this->employee] as $user) {
            $signing->sign($report->fresh()->document, $user);
        }

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report->fresh()])
            ->assertSee('Lihat PDF')->assertSee('Kejadian-X')->assertSee('Analisis-A')->assertSee('Klarifikasi-Z')
            ->assertDontSee('Parafrase &amp; Analisis HC', false);

        // Karyawan yang bersangkutan juga melihat BA lengkap + tanda tangan (QR) + PDF.
        Livewire::actingAs($this->employee)->test(\App\Livewire\Sp\BeritaAcaraSayaPage::class, ['record' => $report->fresh()])
            ->assertSee('Lihat PDF')->assertSee('Kejadian-X')->assertSee('Analisis-A')->assertSee('Karyawan yang Bersangkutan')
            ->assertSee('data:image/svg+xml', false);

        $this->actingAs($this->hc)->get(route('ba.preview', $report))->assertOk();
        $this->actingAs($this->employee)->get(route('sp.saya.ba.preview', $report))->assertOk();
        $this->actingAs($this->knower)->get(route('sp.saya.ba.preview', $report))->assertForbidden();
    }

    public function test_list_shows_report_and_its_sp_as_one_row_with_stages(): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:EmployeeSp', 'guard_name' => 'web']);
        $this->hc->givePermissionTo('ViewAny:EmployeeSp');

        $report = app(SpReportService::class)->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'k', 'dasar_items' => ['d'],
        ]);

        $page = Livewire::actingAs($this->hc)->test(\App\Livewire\Sp\SpPage::class);

        $page->assertSee($this->employee->name)->assertSee('Berita Acara')->assertSee('Keputusan')->assertSee('Surat Peringatan');
        $this->assertCount(1, $page->instance()->cases->items());
    }

    public function test_unrelated_user_and_legacy_report_are_blocked(): void
    {
        $report = app(SpReportService::class)->create($this->hc, [
            'user_id' => $this->employee->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'x', 'dasar_items' => ['y'],
        ]);

        Livewire::actingAs($this->employee)->test(BeritaAcaraDetailPage::class, ['record' => $report])->assertForbidden();

        $report->forceFill(['status' => null])->save();
        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report])->assertNotFound();
    }
}
