<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Livewire\Sp\BeritaAcaraDetailPage;
use App\Livewire\Sp\CreateSpFromReportPage;
use App\Models\Master\Companies;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use App\Services\Document\DocumentSigningService;
use App\Services\Sp\SpReportService;
use App\Services\Sp\SpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Support\SpFixtures;
use Tests\TestCase;

/** SP dibuat dari BA selesai (instruksi user 2026-10-01): syarat BA, urutan TTD 3 pihak, hash BA stabil. */
class SpFromReportTest extends TestCase
{
    use RefreshDatabase;

    private User $hc;
    private User $employee;
    private User $knower;
    private \App\Models\Sp\SpReason $reason;
    private SpReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Companies::create(['code' => 'SFR', 'name' => 'Company SFR', 'location' => 'Test', 'is_active' => true]);

        $perms = [CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp'];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->hc = User::factory()->create(['company_id' => $company->id]);
        $this->hc->givePermissionTo($perms);
        $this->employee = User::factory()->create(['company_id' => $company->id]);
        $this->knower = User::factory()->create(['company_id' => $company->id]);
        \Tests\Support\SpFixtures::makeDepartmentHead($this->knower);
        $this->reason = \App\Models\Sp\SpReason::create(['company_id' => $company->id, 'name' => 'Keterlambatan']);
        $this->service = app(SpReportService::class);
    }

    private function sign($document, User $user): void
    {
        app(DocumentSigningService::class)->sign($document->fresh(), $user);
    }

    /** BA selesai (3 TTD) dengan keputusan tertentu. */
    private function finishedReport(?SpReportDecision $decision = SpReportDecision::LANJUT_SP): SpReport
    {
        $report = $this->service->create($this->hc, [
            'invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat', 'dasar_items' => ['Rekap'],
        ]);
        $this->service->updateDraft($report, $this->hc, ['subject_clarification' => 'macet', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f']);
        $this->service->sendForSigning($report, $this->hc);
        foreach ([$this->hc, $this->knower, $this->employee] as $user) {
            $this->sign($report->fresh()->document, $user);
        }
        if ($decision) {
            $this->service->decide($report->fresh(), $this->hc, $decision);
        }

        return $report->fresh();
    }

    private function spData(array $over = []): array
    {
        return ['nomor' => 'SP-FR-1', 'sp_level' => 'sp1', 'start_date' => now()->toDateString(), 'knows_by' => $this->knower->id, 'items' => ['Terlambat 5 kali'], ...$over];
    }

    public function test_sp_can_only_be_created_from_finished_report_decided_lanjut_sp_and_only_once(): void
    {
        $notDecided = $this->finishedReport(null);
        try {
            $this->service->createSp($notDecided, $this->hc, $this->spData());
            $this->fail('BA tanpa keputusan tidak boleh jadi SP');
        } catch (ValidationException) {
            $this->assertNull($notDecided->fresh()->sp_id);
        }

        $tidak = $this->finishedReport(SpReportDecision::TIDAK_LAYAK_SP);
        $this->expectException(ValidationException::class);
        $this->service->createSp($tidak, $this->hc, $this->spData());
    }

    public function test_second_sp_from_same_report_is_rejected_and_report_hash_stays_valid(): void
    {
        $report = $this->finishedReport();
        $storedHash = $report->document->signatures()->first()->document_hash;

        $sp = $this->service->createSp($report, $this->hc, $this->spData());

        $this->assertSame($sp->id, $report->fresh()->sp_id);
        // sp_id terisi SETELAH TTD - tidak boleh mengubah hash yang tersimpan.
        $this->assertSame($storedHash, hash('sha256', json_encode($report->fresh()->signablePayload())));

        try {
            $this->service->createSp($report->fresh(), $this->hc, $this->spData(['nomor' => 'SP-FR-2']));
            $this->fail('BA yang sudah punya SP tidak boleh dipakai lagi');
        } catch (ValidationException) {
            $this->assertSame(1, EmployeeSp::count());
        }
    }

    public function test_sp_from_new_report_needs_three_signatures_in_order_and_notifies_employee(): void
    {
        $report = $this->finishedReport();
        $sp = $this->service->createSp($report, $this->hc, $this->spData());
        $sp->update(['nomor' => 'SP-FR-1']);

        app(SpService::class)->afterIssued($sp->fresh()); // created_by menandatangani saat terbit
        $sp = $sp->fresh();
        $this->assertSame(['created_by', 'knows_by', 'employee'], array_keys($sp->expectedSignerRoles()));
        $this->assertNull($sp->assignedSigners()['employee']);

        Notification::fake();
        $this->sign($sp->document, $this->knower);
        Notification::assertSentTo($this->employee, SpReportUpdated::class);

        $sp = $sp->fresh();
        $this->assertSame($this->employee->id, $sp->assignedSigners()['employee']->id);

        $this->sign($sp->document, $this->employee);
        $this->assertTrue($sp->fresh()->document->fresh()->isFullySigned());
    }

    public function test_create_step_saves_draft_and_is_embedded_in_ba_detail(): void
    {
        $eligible = $this->finishedReport();

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $eligible])
            ->assertSeeLivewire(CreateSpFromReportPage::class);

        Livewire::actingAs($this->hc)->test(CreateSpFromReportPage::class, ['reportId' => $eligible->id])
            ->set('nomor', 'SP-FR-9')
            ->set('sp_level', 'sp2')
            ->set('knows_by', $this->knower->id)
            ->set('start_date', now()->toDateString())
            ->set('items', [['reason' => $this->reason->id, 'note' => '']])
            ->call('save')
            ->assertHasNoErrors();

        $sp = EmployeeSp::where('nomor', 'SP-FR-9')->firstOrFail();
        $this->assertSame($this->employee->id, $sp->user_id);
        $this->assertSame($sp->id, $eligible->fresh()->sp_id);
        $this->assertSame('Keterlambatan', $sp->items->first()->text);
    }

    public function test_pelanggaran_options_follow_selected_sp_level_and_wrong_rows_are_cleared(): void
    {
        $sp1 = \App\Models\Sp\SpReason::create(['company_id' => $this->hc->company_id, 'name' => 'Terlambat', 'sp_level' => 'sp1']);
        $sp3 = \App\Models\Sp\SpReason::create(['company_id' => $this->hc->company_id, 'name' => 'Mangkir', 'sp_level' => 'sp3']);

        $page = Livewire::actingAs($this->hc)->test(CreateSpFromReportPage::class, ['reportId' => $this->finishedReport()->id])
            ->set('sp_level', 'sp1');

        $options = $page->instance()->reasonOptions;
        $this->assertTrue($options->has($sp1->id));
        $this->assertFalse($options->has($sp3->id));
        // master lama tanpa level tetap muncul di semua level
        $this->assertTrue($options->has($this->reason->id));

        $page->set('items.0.reason', $sp1->id)->set('sp_level', 'sp3')->assertSet('items.0.reason', '');
        $this->assertTrue($page->instance()->reasonOptions->has($sp3->id));
    }

    public function test_repeat_of_active_sp_violation_is_offered_suggests_next_level_and_is_stored(): void
    {
        $first = $this->service->createSp($this->finishedReport(), $this->hc, [
            'nomor' => 'SP-1', 'sp_level' => 'sp1', 'start_date' => now()->toDateString(), 'knows_by' => $this->knower->id,
            'items' => [['reason_id' => $this->reason->id, 'note' => '3 kali']],
        ]);
        $first->issueDocument('SP 1', $this->hc);
        $choice = "repeat:{$first->id}:{$this->reason->id}";

        $page = Livewire::actingAs($this->hc)->test(CreateSpFromReportPage::class, ['reportId' => $this->finishedReport()->id])
            ->assertSee('berulang dari SP 1')
            ->set('items.0.reason', $choice)
            ->assertSet('sp_level', 'sp2');

        $page->set('nomor', 'SP-2')->set('items.0.note', 'lagi')->call('save')->assertHasNoErrors();

        $item = EmployeeSp::where('nomor', 'SP-2')->firstOrFail()->items->first();
        $this->assertSame($first->id, $item->repeat_sp_id);
        $this->assertStringContainsString('pelanggaran berulang', $item->text);
    }

    public function test_repeat_choice_from_sp_that_is_not_offered_is_rejected(): void
    {
        $other = $this->service->createSp($this->finishedReport(), $this->hc, [
            'nomor' => 'SP-X', 'sp_level' => 'sp1', 'start_date' => now()->toDateString(), 'knows_by' => $this->knower->id,
            'items' => [['reason_id' => $this->reason->id]],
        ]); // draft, belum terbit -> tidak ditawarkan

        Livewire::actingAs($this->hc)->test(CreateSpFromReportPage::class, ['reportId' => $this->finishedReport()->id])
            ->set('nomor', 'SP-Y')->set('sp_level', 'sp2')->set('start_date', now()->toDateString())
            ->set('items', [['reason' => "repeat:{$other->id}:{$this->reason->id}", 'note' => '']])
            ->call('save')
            ->assertForbidden();
    }
}
