<?php

namespace Tests\Feature\Sikartap;

use App\Livewire\Sikartap\PengajuanKaryawanTetapPage;
use App\Models\Master\Companies;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PengajuanKaryawanTetapPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);

        return User::factory()->create(['company_id' => $company->id]);
    }

    public function test_user_without_application_sees_start_button_and_can_start(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->assertSet('sikartapId', null)
            ->call('startApplication')
            ->assertSet('sikartapId', fn ($id) => $id !== null);

        $this->assertDatabaseHas('sikartap', ['user_id' => $user->id, 'status_berkas' => 'pending']);
    }

    public function test_user_can_upload_document_and_reupload_resets_status_to_pending(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();
        $sikartap = Sikartap::create(['user_id' => $user->id]);
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf');

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->set('uploads.ktp_kk', $file)
            ->call('uploadDocument', 'ktp_kk')
            ->assertHasNoErrors();

        $doc = $sikartap->documents()->where('doc_type', 'ktp_kk')->first();
        $this->assertNotNull($doc);
        $this->assertSame('pending', $doc->status);
        Storage::disk('public')->assertExists($doc->file_path);

        // Simulasikan sudah ditolak, lalu reupload -> harus reset ke pending lagi.
        $doc->update(['status' => 'rejected', 'catatan_penolakan' => 'Buram']);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->set('uploads.ktp_kk', UploadedFile::fake()->create('ktp-baru.pdf', 100, 'application/pdf'))
            ->call('uploadDocument', 'ktp_kk')
            ->assertHasNoErrors();

        $doc->refresh();
        $this->assertSame('pending', $doc->status);
        $this->assertNull($doc->catatan_penolakan);
    }

    public function test_cannot_upload_after_berkas_approved(): void
    {
        $user = $this->makeUser();
        Sikartap::create(['user_id' => $user->id, 'status_berkas' => 'approved']);
        $this->actingAs($user);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->set('uploads.ktp_kk', UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'))
            ->call('uploadDocument', 'ktp_kk')
            ->assertForbidden();
    }

    /** Instruksi user 2026-08-26: ditolak (fase manapun) boleh reapply, rewrite tombol "Buat
     * Pengajuan Baru" history_pengajuan.php legacy - bikin baris Sikartap baru, riwayat lama
     * tetap tersimpan. */
    public function test_user_can_reapply_after_rejected_berkas(): void
    {
        $user = $this->makeUser();
        $old = Sikartap::create(['user_id' => $user->id, 'status_berkas' => 'rejected']);
        $this->actingAs($user);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->assertSet('canStartNew', true)
            ->call('startApplication');

        $this->assertDatabaseCount('sikartap', 2);
        $this->assertDatabaseHas('sikartap', ['id' => $old->id, 'status_berkas' => 'rejected']);
        $this->assertDatabaseHas('sikartap', ['user_id' => $user->id, 'status_berkas' => 'pending']);
    }

    public function test_user_cannot_reapply_while_pengajuan_still_in_progress(): void
    {
        $user = $this->makeUser();
        Sikartap::create(['user_id' => $user->id, 'status_berkas' => 'pending']);
        $this->actingAs($user);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->assertSet('canStartNew', false)
            ->call('startApplication')
            ->assertForbidden();

        $this->assertDatabaseCount('sikartap', 1);
    }

    public function test_disqualified_user_cannot_reapply(): void
    {
        $user = $this->makeUser();
        Sikartap::create(['user_id' => $user->id, 'status_berkas' => 'rejected', 'is_diskualifikasi' => true]);
        $this->actingAs($user);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->assertSet('canStartNew', false)
            ->call('startApplication')
            ->assertForbidden();

        $this->assertDatabaseCount('sikartap', 1);
    }
}
