<?php

namespace Tests\Feature\Document;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Livewire\Document\SignDocumentsPage;
use App\Livewire\Sp\CreateSpPage;
use App\Livewire\Sp\SpDetailPage;
use App\Models\Master\Companies;
use App\Models\Sp\EmployeeSp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Halaman GENERIK "Tanda Tangan Dokumen" (routes/documents.php) - instruksi user 2026-09-28:
 * bukan halaman khusus SP, siapa pun yang ditugaskan menandatangani dokumen jenis apa pun
 * (lewat Signable::assignedSigners()) muncul di sini. Tes ini pakai SP sebagai satu-satunya
 * jenis dokumen yang ada sekarang, tapi tidak mengasumsikan apa pun soal SP di luar itu.
 */
class SignDocumentsPageTest extends TestCase
{
    use RefreshDatabase;

    private function issueSp(string $nomor = 'SP-SIGN-001'): array
    {
        $company = Companies::create(['code' => 'SGN', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

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
            ->set('nomor', $nomor)
            ->set('sp_level', SpLevel::SP1->value)
            ->set('knows_by', $knower->id)
            ->set('start_date', now()->toDateString())
            ->set('items', ['Pelanggaran'])
            ->call('save');

        $sp = EmployeeSp::where('nomor', $nomor)->firstOrFail();

        Livewire::actingAs($hc)
            ->test(SpDetailPage::class, ['record' => $sp])
            ->call('openConfirmIssue')
            ->set('issuePassword', 'password')
            ->call('issue');

        return [$sp->fresh(), $knower, $hc];
    }

    public function test_knows_by_sees_pending_document_in_list(): void
    {
        [$sp, $knower] = $this->issueSp();

        Livewire::actingAs($knower)
            ->test(SignDocumentsPage::class)
            ->assertSee($sp->document->document_number)
            ->assertSee('Mengetahui');
    }

    public function test_unrelated_user_does_not_see_it(): void
    {
        [$sp] = $this->issueSp();
        $stranger = User::factory()->create(['company_id' => $sp->company_id]);

        Livewire::actingAs($stranger)
            ->test(SignDocumentsPage::class)
            ->assertDontSee($sp->document->document_number);
    }

    public function test_wrong_password_rejected(): void
    {
        [$sp, $knower] = $this->issueSp();

        Livewire::actingAs($knower)
            ->test(SignDocumentsPage::class)
            ->call('openSignModal', $sp->document->id)
            ->set('password', 'password-salah')
            ->call('confirmSign')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('document_signatures', ['signer_id' => $knower->id]);
    }

    public function test_correct_password_signs_sp_and_removes_it_from_list(): void
    {
        [$sp, $knower] = $this->issueSp();

        Livewire::actingAs($knower)
            ->test(SignDocumentsPage::class)
            ->call('openSignModal', $sp->document->id)
            ->set('password', 'password')
            ->call('confirmSign')
            ->assertHasNoErrors()
            ->assertSet('signingId', null)
            ->assertDontSee($sp->document->document_number);

        $this->assertDatabaseHas('document_signatures', [
            'document_id' => $sp->document->id,
            'signer_id' => $knower->id,
            'signer_role' => 'knows_by',
        ]);
    }

    /** Stranger tidak muncul di pendingDocuments-nya sendiri (test di atas), jadi
     *  signingDocument selalu null buat dia -> 404 ("bukan dokumen Anda"), BUKAN 403 - lebih
     *  aman (tidak membocorkan bahwa dokumen dengan ID itu memang ada). */
    public function test_unrelated_user_cannot_sign_via_direct_call(): void
    {
        [$sp] = $this->issueSp();
        $stranger = User::factory()->create(['company_id' => $sp->company_id]);

        Livewire::actingAs($stranger)
            ->test(SignDocumentsPage::class)
            ->call('openSignModal', $sp->document->id)
            ->set('password', 'password')
            ->call('confirmSign')
            ->assertNotFound();

        $this->assertDatabaseMissing('document_signatures', ['signer_id' => $stranger->id]);
    }

    public function test_notification_link_opens_the_modal_directly(): void
    {
        [$sp, $knower] = $this->issueSp();

        Livewire::actingAs($knower)
            ->test(SignDocumentsPage::class, ['document' => $sp->document])
            ->assertSet('signingId', $sp->document->id);
    }
}
