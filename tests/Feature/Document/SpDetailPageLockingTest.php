<?php

namespace Tests\Feature\Document;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
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
 * Dokumen dikunci (SpDetailPage::isFullySigned) begitu semua penanda tangan yang diharapkan
 * sudah menandatangani (instruksi user 2026-09-28, menutup gap "bisa diedit diam-diam setelah
 * tanda tangan").
 */
class SpDetailPageLockingTest extends TestCase
{
    use RefreshDatabase;

    private function hcUser(Companies $company): User
    {
        foreach ([
            CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value,
            'View:EmployeeSp',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'View:EmployeeSp');

        return $user;
    }

    private function makeSp(Companies $company, User $hc, User $knower): EmployeeSp
    {
        $employee = User::factory()->create(['company_id' => $company->id]);

        $sp = EmployeeSp::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'created_by' => $hc->id,
            'knows_by' => $knower->id,
            'nomor' => 'SP-LOCK-001',
            'sp_level' => SpLevel::SP1,
            'source_type' => SpSourceType::MANUAL->value,
            'start_date' => now(),
        ]);

        $sp->items()->create(['text' => 'Pelanggaran', 'sort_order' => 1]);

        app(SpService::class)->afterIssued($sp);

        return $sp->fresh();
    }

    public function test_draft_without_signature_is_still_editable(): void
    {
        $company = Companies::create(['code' => 'LK0', 'name' => 'Lock Co 0', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $knower = User::factory()->create(['company_id' => $company->id]);
        $sp = \Tests\Support\SpFixtures::draftSp($hc, $company->id, User::factory()->create(['company_id' => $company->id])->id, $knower->id, 'SP-LOCK-D');

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->assertSet('isLocked', false)
            ->call('editSp')
            ->assertSet('editingSp', true);
    }

    public function test_edit_blocked_as_soon_as_creator_signed(): void
    {
        $company = Companies::create(['code' => 'LK1', 'name' => 'Lock Co 1', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $knower = User::factory()->create(['company_id' => $company->id]);
        // afterIssued() = pembuat langsung menandatangani; knows_by BELUM.
        $sp = $this->makeSp($company, $hc, $knower);

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->assertSet('isLocked', true);

        Livewire::actingAs($hc)->test(SpDetailPage::class, ['record' => $sp])->call('editSp')->assertForbidden();
        Livewire::actingAs($hc)->test(SpDetailPage::class, ['record' => $sp])->call('editItems')->assertForbidden();
        Livewire::actingAs($hc)->test(SpDetailPage::class, ['record' => $sp])->call('editReport')->assertForbidden();
    }

    /** "Mengetahui" DITUNJUK di SP ini boleh buka halamannya (instruksi user 2026-09-28: dia
     *  perlu baca isi surat yang mau ditandatanganinya) TAPI cuma baca - semua aksi tulis tetap
     *  403 walau blade sembunyikan tombolnya (Livewire action tetap bisa dipanggil langsung
     *  lepas dari tombol yang dirender). */
    public function test_knows_by_can_view_but_not_edit(): void
    {
        $company = Companies::create(['code' => 'LK2', 'name' => 'Lock Co 2', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $knower = User::factory()->create(['company_id' => $company->id]);
        $sp = $this->makeSp($company, $hc, $knower);

        $component = Livewire::actingAs($knower)
            ->test(SpDetailPage::class, ['record' => $sp]);

        $component->assertOk()->assertSet('canManage', false);

        Livewire::actingAs($knower)->test(SpDetailPage::class, ['record' => $sp])->call('editSp')->assertForbidden();
        Livewire::actingAs($knower)->test(SpDetailPage::class, ['record' => $sp])->call('openCancelSp')->assertForbidden();
    }

    /** Karyawan biasa yang tidak terlibat sama sekali (bukan HC, bukan knows_by) tetap 403 -
     *  keterbukaan ini SENGAJA sempit, cuma untuk pihak yang memang ditunjuk di SP itu sendiri. */
    public function test_unrelated_employee_cannot_view(): void
    {
        $company = Companies::create(['code' => 'LK3', 'name' => 'Lock Co 3', 'location' => 'X', 'is_active' => true]);
        $hc = $this->hcUser($company);
        $knower = User::factory()->create(['company_id' => $company->id]);
        $sp = $this->makeSp($company, $hc, $knower);

        $stranger = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($stranger)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->assertForbidden();
    }
}
