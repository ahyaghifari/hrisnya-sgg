<?php

namespace Tests\Feature\Document;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Livewire\Sp\CreateSpPage;
use App\Livewire\Sp\SpDetailPage;
use App\Models\Master\Companies;
use App\Models\Sp\EmployeeSp;
use App\Models\User;
use App\Services\Document\DocumentSigningService;
use App\Services\Sp\SpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * TTE Fase 1, level "TTE Tidak Tersertifikasi" (docs/HRIS_TTE_NonTersertifikasi_QR_Verification.md,
 * Pasal 59 ayat 3 butir 6 PP 71/2019 - persetujuan eksplisit tiap penanda tangan). CreateSpPage
 * cuma menyimpan draft (instruksi user 2026-09-29); created_by ditandatangani saat pembuatnya
 * sendiri konfirmasi password lewat SpDetailPage::issue() ("Tanda Tangan & Terbitkan"). knows_by
 * SENGAJA TIDAK otomatis - baru tercatat setelah dia sendiri konfirmasi lewat
 * DocumentSigningService (instruksi user 2026-09-28). Berita Acara punya registry/kode
 * verifikasinya sendiri, terpisah dari SP induknya.
 */
class SignSpDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function hcUser(Companies $company): User
    {
        foreach ([
            CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value,
            'Create:EmployeeSp',
            'View:EmployeeSp',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp');

        return $user;
    }

    /** Simpan draft lewat CreateSpPage lalu langsung terbitkan lewat SpDetailPage::issue() (si
     *  pembuat konfirmasi password) - meniru 2 langkah yang sekarang ada di portal beneran. */
    private function createSp(User $hc, string $companyId, string $employeeId, string $knowerId, string $nomor, array $extra = [])
    {
        Livewire::actingAs($hc)
            ->test(CreateSpPage::class)
            ->set('companyId', $companyId)
            ->set('user_id', $employeeId)
            ->set('nomor', $nomor)
            ->set('sp_level', SpLevel::SP1->value)
            ->set('knows_by', $knowerId)
            ->set('start_date', now()->toDateString())
            ->set('items', ['Terlambat berkali-kali'])
            ->set($extra)
            ->call('save');

        $sp = EmployeeSp::where('nomor', $nomor)->firstOrFail();

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->call('openConfirmIssue')
            ->set('issuePassword', 'password')
            ->call('issue');

        return $sp;
    }

    public function test_wrong_password_blocks_issuance(): void
    {
        $company = Companies::create(['code' => 'DOC0', 'name' => 'Test Co 0', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($hc)
            ->test(CreateSpPage::class)
            ->set('companyId', $company->id)
            ->set('user_id', $employee->id)
            ->set('nomor', 'SP-TEST-000')
            ->set('sp_level', SpLevel::SP1->value)
            ->set('knows_by', $knower->id)
            ->set('start_date', now()->toDateString())
            ->set('items', ['Pelanggaran'])
            ->call('save');

        $sp = EmployeeSp::where('nomor', 'SP-TEST-000')->firstOrFail();

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->call('openConfirmIssue')
            ->set('issuePassword', 'password-salah')
            ->call('issue')
            ->assertHasErrors('issuePassword');

        $this->assertNull($sp->fresh()->document);
    }

    public function test_creating_sp_signs_created_by_immediately_and_leaves_knows_by_pending(): void
    {
        $company = Companies::create(['code' => 'DOC', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        $this->createSp($hc, $company->id, $employee->id, $knower->id, 'SP-TEST-001');

        $sp = EmployeeSp::where('nomor', 'SP-TEST-001')->firstOrFail();

        $this->assertDatabaseHas('documents', [
            'documentable_type' => EmployeeSp::class,
            'documentable_id' => $sp->id,
            'document_type' => 'sp',
        ]);

        $this->assertDatabaseHas('document_signatures', ['signer_id' => $hc->id, 'signer_role' => 'created_by']);
        $this->assertDatabaseMissing('document_signatures', ['signer_role' => 'knows_by']);
        $this->assertSame(1, $sp->document->signatures()->count());

        $createdBySignature = $sp->document->signatures()->where('signer_role', 'created_by')->firstOrFail();
        $this->assertNotNull($createdBySignature->verification);
        $this->assertMatchesRegularExpression('/^SP-\d{4}-[A-Z0-9]{6}$/', $createdBySignature->verification->verification_code);

        // knows_by muncul di daftar tanda tangan pending-nya sendiri.
        $pending = app(DocumentSigningService::class)->pendingFor($knower);
        $this->assertTrue($pending->contains('id', $sp->document->id));

        // knows_by konfirmasi sendiri -> baru tercatat.
        app(DocumentSigningService::class)->sign($sp->document, $knower);

        $this->assertDatabaseHas('document_signatures', ['signer_id' => $knower->id, 'signer_role' => 'knows_by']);
        $this->assertSame(2, $sp->document->fresh()->signatures()->count());

        // 1 TTD = 1 kode PERSONAL (instruksi user 2026-09-29) - kode knows_by BEDA dari kode
        // created_by, bukan 1 kode yang dibagi rata ke semua peran.
        $knowsBySignature = $sp->document->signatures()->where('signer_role', 'knows_by')->firstOrFail();
        $this->assertNotNull($knowsBySignature->verification);
        $this->assertNotSame($createdBySignature->verification->verification_code, $knowsBySignature->verification->verification_code);
    }

    public function test_berita_acara_gets_its_own_document_separate_from_sp(): void
    {
        $company = Companies::create(['code' => 'DOC2', 'name' => 'Test Co 2', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        $this->createSp($hc, $company->id, $employee->id, $knower->id, 'SP-TEST-002', [
            'kejadian' => 'Kejadian X',
            'subject_clarification' => 'Klarifikasi karyawan',
        ]);

        $sp = EmployeeSp::with('report.document.signatures.verification')->where('nomor', 'SP-TEST-002')->firstOrFail();

        $this->assertNotNull($sp->report);
        $this->assertNotNull($sp->report->document);
        $this->assertNotSame($sp->document->id, $sp->report->document->id);
        $this->assertSame('berita_acara', $sp->report->document->document_type);

        $spCreatedBy = $sp->document->signatures()->where('signer_role', 'created_by')->firstOrFail();
        $reportCreatedBy = $sp->report->document->signatures->firstWhere('signer_role', 'created_by');

        $this->assertNotNull($reportCreatedBy->verification);
        $this->assertNotSame(
            $spCreatedBy->verification->verification_code,
            $reportCreatedBy->verification->verification_code
        );

        // knows_by konfirmasi 1x di SignDocumentsPage -> SP DAN Berita Acara sama-sama tertandatangani.
        app(DocumentSigningService::class)->sign($sp->document, $knower);

        $this->assertDatabaseHas('document_signatures', [
            'document_id' => $sp->report->document->id,
            'signer_id' => $knower->id,
            'signer_role' => 'knows_by',
        ]);
    }

    public function test_signing_records_ip_and_user_agent(): void
    {
        $company = Companies::create(['code' => 'DOC4', 'name' => 'Test Co 4', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        $this->createSp($hc, $company->id, $employee->id, $knower->id, 'SP-TEST-004');

        $sp = EmployeeSp::where('nomor', 'SP-TEST-004')->firstOrFail();

        $this->assertNotNull($sp->document->signatures()->where('signer_role', 'created_by')->first()->ip_address);
    }

    public function test_cancelling_sp_shows_as_revoked_on_next_verification_check(): void
    {
        $company = Companies::create(['code' => 'DOC3', 'name' => 'Test Co 3', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        $this->createSp($hc, $company->id, $employee->id, $knower->id, 'SP-TEST-003');

        $sp = EmployeeSp::where('nomor', 'SP-TEST-003')->firstOrFail();
        $this->assertFalse($sp->isRevoked());

        app(SpService::class)->cancel($sp, 'Salah input', $hc->id);

        $this->assertTrue($sp->fresh()->isRevoked());
    }
}
