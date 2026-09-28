<?php

namespace Tests\Feature\Document;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Livewire\Sp\CreateSpPage;
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
 * Halaman verifikasi publik /verify/{code} (TTE Fase 1, §5) - tanpa login, status dihitung
 * live dari dokumen sumbernya.
 */
class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function issueSignedSp(): EmployeeSp
    {
        $company = Companies::create(['code' => 'VER', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

        foreach ([CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $hc = User::factory()->create(['company_id' => $company->id]);
        $hc->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp');

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
            ->call('chooseConfirmSign')
            ->set('signPassword', 'password')
            ->call('save');

        return EmployeeSp::where('nomor', 'SP-VER-001')->firstOrFail();
    }

    public function test_valid_code_shows_sah(): void
    {
        $sp = $this->issueSignedSp();
        $code = $sp->document->verification->verification_code;

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('SAH')
            ->assertSee($sp->document->document_number)
            ->assertSee($code);
    }

    public function test_cancelled_sp_shows_dicabut(): void
    {
        $sp = $this->issueSignedSp();
        $code = $sp->document->verification->verification_code;

        app(SpService::class)->cancel($sp, 'Salah input', $sp->created_by);

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('DICABUT')
            ->assertSee('Salah input');
    }

    public function test_pending_knows_by_signature_shown_until_confirmed(): void
    {
        $sp = $this->issueSignedSp();
        $code = $sp->document->verification->verification_code;

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertSee('Belum Lengkap')
            ->assertSee('Mengetahui');

        app(DocumentSigningService::class)->sign($sp->document, $sp->knowsBy);

        $this->get(route('public.verify', ['code' => $code]))
            ->assertOk()
            ->assertDontSee('Belum Lengkap');
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
        $code = $sp->document->verification->verification_code;

        // Tidak ada actingAs() sama sekali di test ini - guest harus tetap bisa buka halamannya.
        $this->get(route('public.verify', ['code' => $code]))->assertOk();
    }
}
