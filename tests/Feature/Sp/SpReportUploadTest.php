<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Enum\Sp\SpReportStatus;
use App\Livewire\Sp\BeritaAcaraDetailPage;
use App\Models\Master\Companies;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Services\Document\DocumentSigningService;
use App\Services\Sp\SpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Support\SpFixtures;
use Tests\TestCase;

/** TTD digital BA ditahan: setelah FIX, HC mengunggah BA bertanda tangan -> selesai -> keputusan SP. */
class SpReportUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $hc;
    private User $employee;
    private User $knower;
    private SpReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sp.digital_signing' => false]); // default test = TTD digital aktif (phpunit.xml); di sini alur unggah berkas
        Storage::fake('local');
        $company = Companies::create(['code' => 'SPU', 'name' => 'Company SPU', 'location' => 'Test', 'is_active' => true]);
        $perms = [CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp'];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->hc = User::factory()->create(['company_id' => $company->id]);
        $this->hc->givePermissionTo($perms);
        $this->employee = User::factory()->create(['company_id' => $company->id]);
        $this->knower = User::factory()->create(['company_id' => $company->id]);
        $this->service = app(SpReportService::class);
    }

    private function fixedReport(): SpReport
    {
        $report = $this->service->create($this->hc, [
            'invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'Terlambat', 'dasar_items' => ['Rekap'],
        ]);
        $this->service->updateDraft($report, $this->hc, ['subject_clarification' => 'macet', 'analysis' => 'a', 'kesimpulan' => 'k', 'followup_plan' => 'f']);

        return $this->service->sendForSigning($report, $this->hc)->fresh();
    }

    private function pdf(string $name = 'ba.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    public function test_digital_signing_is_off_so_nobody_is_assigned_to_sign(): void
    {
        $report = $this->fixedReport();

        $this->assertSame(SpReportStatus::MENUNGGU_TTD, $report->status);
        $this->assertTrue(collect($report->assignedSigners())->every(fn ($user) => $user === null));
        $this->assertCount(0, app(DocumentSigningService::class)->pendingFor($this->knower));
    }

    public function test_upload_completes_the_report_and_unlocks_decision(): void
    {
        $report = $this->fixedReport();

        $this->service->uploadSigned($report, $this->hc, $this->pdf('ba-ttd.pdf'));

        $report = $report->fresh();
        $this->assertSame(SpReportStatus::SELESAI, $report->status);
        $this->assertSame('ba-ttd.pdf', $report->signed_file_name);
        $this->assertSame($this->hc->id, $report->signed_uploaded_by);
        Storage::disk('local')->assertExists($report->signed_file_path);

        $this->service->decide($report, $this->hc, SpReportDecision::LANJUT_SP);
        $this->assertSame(SpReportDecision::LANJUT_SP, $report->fresh()->keputusan);

        // Pembuat dan karyawan membuka berkas yang diunggah lewat route preview yang sama.
        $this->actingAs($this->hc)->get(route('ba.preview', $report))->assertOk();
        $this->actingAs($this->employee)->get(route('sp.saya.ba.preview', $report))->assertOk();
    }

    public function test_upload_is_only_allowed_while_waiting_and_file_can_be_replaced_until_decision(): void
    {
        $draft = $this->service->create($this->hc, [
            'invitation_id' => SpFixtures::invitation($this->hc, $this->employee)->id, 'knows_by' => $this->knower->id,
            'kejadian' => 'x', 'dasar_items' => ['d'],
        ]);

        try {
            $this->service->uploadSigned($draft, $this->hc, $this->pdf());
            $this->fail('draft tidak boleh diunggah');
        } catch (ValidationException) {
        }

        $report = $this->fixedReport();
        $this->service->uploadSigned($report, $this->hc, $this->pdf('lama.pdf'));
        $oldPath = $report->fresh()->signed_file_path;

        $this->service->uploadSigned($report->fresh(), $this->hc, $this->pdf('baru.pdf'));
        $report = $report->fresh();
        $this->assertSame('baru.pdf', $report->signed_file_name);
        Storage::disk('local')->assertMissing($oldPath);

        $this->service->decide($report, $this->hc, SpReportDecision::TIDAK_LAYAK_SP);
        $this->expectException(ValidationException::class);
        $this->service->uploadSigned($report->fresh(), $this->hc, $this->pdf('terlambat.pdf'));
    }

    public function test_detail_page_uploads_and_validates_the_file_type(): void
    {
        $report = $this->fixedReport();

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report])
            ->assertSee('Unggah Berita Acara')
            ->set('signedFile', UploadedFile::fake()->create('virus.exe', 10))
            ->call('uploadSigned')
            ->assertHasErrors(['signedFile']);

        Livewire::actingAs($this->hc)->test(BeritaAcaraDetailPage::class, ['record' => $report])
            ->set('signedFile', $this->pdf())
            ->call('uploadSigned')
            ->assertHasNoErrors();

        $this->assertSame(SpReportStatus::SELESAI, $report->fresh()->status);
    }
    public function test_sp_is_issued_only_after_signed_file_is_uploaded_and_notifications_run(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $sp = SpFixtures::draftSp($this->hc, $this->hc->company_id, $this->employee->id, $this->knower->id, 'SP-U1');
        $service = app(\App\Services\Sp\SpService::class);

        // Terbit = FIX dokumen + yang mengetahui dikabari; belum resmi, karyawan belum melihat.
        $service->afterIssued($sp);
        $sp = $sp->fresh();
        $this->assertNotNull($sp->document->fixed_at);
        $this->assertFalse($sp->isIssued());
        $this->assertTrue(collect($sp->assignedSigners())->every(fn ($u) => $u === null));
        \Illuminate\Support\Facades\Notification::assertSentTo($this->knower, \App\Notifications\SpAwaitingSignature::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($this->employee, \App\Notifications\SpIssued::class);
        $this->assertNull(\App\Models\UserHrBalance::where('user_id', $this->employee->id)->value('active_sp_level'));

        // Unggahan pertama meresmikan SP: level aktif, notifikasi karyawan.
        $service->uploadSigned($sp, $this->hc, $this->pdf('sp-ttd.pdf'));

        $sp = $sp->fresh();
        $this->assertTrue($sp->isIssued());
        $this->assertSame(\App\Enum\Sp\SpLevel::SP1, \App\Models\UserHrBalance::where('user_id', $this->employee->id)->first()->active_sp_level);
        \Illuminate\Support\Facades\Notification::assertSentTo($this->employee, \App\Notifications\SpIssued::class);
        $this->actingAs($this->hc)->get(route('sp.preview', $sp))->assertOk();
        $this->actingAs($this->employee)->get(route('sp.saya.preview', $sp))->assertOk();

        // Ganti berkas: tidak menerapkan efek dua kali.
        $service->uploadSigned($sp, $this->hc, $this->pdf('sp-ttd-2.pdf'));
        \Illuminate\Support\Facades\Notification::assertSentToTimes($this->employee, \App\Notifications\SpIssued::class, 1);
    }

    public function test_sp_detail_page_shows_upload_panel_and_cannot_be_uploaded_before_issue(): void
    {
        $sp = SpFixtures::draftSp($this->hc, $this->hc->company_id, $this->employee->id, $this->knower->id, 'SP-U2');

        Livewire::actingAs($this->hc)->test(\App\Livewire\Sp\SpDetailPage::class, ['record' => $sp])
            ->set('signedFile', $this->pdf())
            ->call('uploadSigned')
            ->assertHasErrors(['status']); // belum diterbitkan

        app(\App\Services\Sp\SpService::class)->afterIssued($sp);

        Livewire::actingAs($this->hc)->test(\App\Livewire\Sp\SpDetailPage::class, ['record' => $sp->fresh()])
            ->assertSee('Surat Peringatan Bertanda Tangan')
            ->assertSee('Cetak Surat Peringatan')
            ->set('signedFile', $this->pdf())
            ->call('uploadSigned')
            ->assertHasNoErrors();

        $this->assertTrue($sp->fresh()->isIssued());
    }
}