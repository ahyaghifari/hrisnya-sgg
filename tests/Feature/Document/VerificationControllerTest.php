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
 * Halaman verifikasi publik /verify/{code} (TTE Fase 1, §5, direvisi instruksi user 2026-09-29:
 * 1 kode = 1 TTD personal) - tanpa login, status dihitung live dari dokumen sumbernya.
 */
class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function codeFor(EmployeeSp $sp, string $role): string
    {
        return $sp->document->signatures->firstWhere('signer_role', $role)->verification->verification_code;
    }

    private function issueSignedSp(): EmployeeSp
    {
        $company = Companies::create(['code' => 'VER', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

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
            ->set('nomor', 'SP-VER-001')
            ->set('sp_level', SpLevel::SP1->value)
            ->set('knows_by', $knower->id)
            ->set('start_date', now()->toDateString())
            ->set('items', ['Pelanggaran'])
            ->call('save');

        $sp = EmployeeSp::where('nomor', 'SP-VER-001')->firstOrFail();

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->call('openConfirmIssue')
            ->set('issuePassword', 'password')
            ->call('issue');

        return $sp->fresh();
    }

    public function test_valid_code_shows_sah(): void
    {
        $sp = $this->issueSignedSp();
        $code = $this->codeFor($sp, 'created_by');

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('SAH')
            ->assertSee($sp->document->document_number)
            ->assertSee($code);
    }

    public function test_cancelled_sp_shows_dicabut(): void
    {
        $sp = $this->issueSignedSp();
        $code = $this->codeFor($sp, 'created_by');

        app(SpService::class)->cancel($sp, 'Salah input', $sp->created_by);

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('DICABUT')
            ->assertSee('Salah input');
    }

    /** 1 kode = 1 TTD personal (instruksi user 2026-09-29) - kode created_by SAH sendiri walau
     *  knows_by belum konfirmasi apa-apa (beda dari desain lama yang nunjukin "Belum Lengkap"
     *  di halaman yang sama). Begitu knows_by konfirmasi, dia dapat kode SENDIRI, beda dari
     *  kode created_by. */
    public function test_each_signature_has_its_own_independent_code(): void
    {
        $sp = $this->issueSignedSp();
        $createdByCode = $this->codeFor($sp, 'created_by');

        $this->get(route('public.verify', ['code' => $createdByCode]))
            ->assertOk()
            ->assertSee('SAH')
            ->assertSee('Dibuat oleh');

        app(DocumentSigningService::class)->sign($sp->document, $sp->knowsBy);

        $knowsByCode = $this->codeFor($sp->fresh(), 'knows_by');
        $this->assertNotSame($createdByCode, $knowsByCode);

        $this->get(route('public.verify', ['code' => $knowsByCode]))
            ->assertOk()
            ->assertSee('SAH')
            ->assertSee('Mengetahui');
    }

    public function test_unknown_code_shows_not_found(): void
    {
        $this->get(route('public.verify', ['code' => 'SP-2610-ZZZZZZ']))
            ->assertOk()
            ->assertSee('tidak ditemukan');
    }

    public function test_verify_page_does_not_require_login(): void
    {
        $sp = $this->issueSignedSp();
        $code = $this->codeFor($sp, 'created_by');

        // Tidak ada actingAs() sama sekali di test ini - guest harus tetap bisa buka halamannya.
        $this->get(route('public.verify', ['code' => $code]))->assertOk();
    }
}
