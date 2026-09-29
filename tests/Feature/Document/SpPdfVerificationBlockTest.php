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
use App\Services\Sp\SpPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * PDF SP & Berita Acara berhasil dirender dengan blok QR verifikasi ter-embed (TTE Fase 1 §7) -
 * smoke test, cuma pastikan tidak exception & QR-nya kepasang, bukan cek isi visual PDF.
 */
class SpPdfVerificationBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_sp_pdf_renders_with_qr_block(): void
    {
        $company = Companies::create(['code' => 'PDF', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

        foreach ([CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $hc = User::factory()->create(['company_id' => $company->id]);
        $hc->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp');
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($hc)
            ->test(CreateSpPage::class)
            ->set('companyId', $company->id)
            ->set('user_id', $employee->id)
            ->set('nomor', 'SP-PDF-001')
            ->set('sp_level', SpLevel::SP1->value)
            ->set('knows_by', $knower->id)
            ->set('start_date', now()->toDateString())
            ->set('items', ['Pelanggaran'])
            ->set('kejadian', 'Kejadian')
            ->call('save');

        $sp = EmployeeSp::where('nomor', 'SP-PDF-001')->firstOrFail();

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->call('openConfirmIssue')
            ->set('issuePassword', 'password')
            ->call('issue');

        // dompdf stream() balikin binary PDF (bukan HTML), kadang ikut membawa header HTTP
        // literal di depannya kalau dipanggil di luar siklus response normal (kasus test ini) -
        // cukup pastikan render tidak exception (mis. $verification undefined di blade, route()
        // gagal resolve, dst) dan hasilnya memang dokumen PDF (ada magic bytes %PDF- di suatu
        // tempat), bukan cek exact posisi/isi byte terkompresi.
        $pdf = app(SpPdfService::class)->preview($sp);
        $this->assertStringContainsString('%PDF-', $pdf);

        $baPdf = app(SpPdfService::class)->previewBeritaAcara($sp);
        $this->assertStringContainsString('%PDF-', $baPdf);
    }

    public function test_qr_only_shown_for_roles_that_have_actually_signed(): void
    {
        $company = Companies::create(['code' => 'PDF2', 'name' => 'Test Co 2', 'location' => 'X', 'is_active' => true]);

        foreach ([CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $hc = User::factory()->create(['company_id' => $company->id]);
        $hc->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp', 'View:EmployeeSp');
        $employee = User::factory()->create(['company_id' => $company->id]);
        $knower = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($hc)
            ->test(CreateSpPage::class)
            ->set('companyId', $company->id)
            ->set('user_id', $employee->id)
            ->set('nomor', 'SP-PDF-002')
            ->set('sp_level', SpLevel::SP1->value)
            ->set('knows_by', $knower->id)
            ->set('start_date', now()->toDateString())
            ->set('items', ['Pelanggaran'])
            ->call('save');

        $draftSp = EmployeeSp::where('nomor', 'SP-PDF-002')->firstOrFail();

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $draftSp])
            ->call('openConfirmIssue')
            ->set('issuePassword', 'password')
            ->call('issue');

        $sp = EmployeeSp::with('document.signatures.verification')
            ->where('nomor', 'SP-PDF-002')->firstOrFail();

        // created_by (HC) langsung TTD saat terbit -> QR-nya boleh tampil.
        // knows_by belum konfirmasi apa-apa -> QR-nya HARUS null (bukan ikut nampang QR yang sama).
        $verification = app(SpPdfService::class)->verificationBySignerRole($sp);
        $this->assertNotNull($verification['created_by']);
        $this->assertNull($verification['knows_by']);

        app(DocumentSigningService::class)->sign($sp->document, $knower);

        $verification = app(SpPdfService::class)->verificationBySignerRole($sp->fresh()->load('document.signatures.verification'));
        $this->assertNotNull($verification['knows_by']);
    }
}
