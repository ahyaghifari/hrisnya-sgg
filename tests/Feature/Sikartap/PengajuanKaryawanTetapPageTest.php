<?php

namespace Tests\Feature\Sikartap;

use App\Enum\EmployeeTypeCategory;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Livewire\Sikartap\PengajuanKaryawanTetapPage;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Setting;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Models\Employee\EmployeeContract;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PengajuanKaryawanTetapPageTest extends TestCase
{
    use RefreshDatabase;

    /** Karyawan Kontrak yang sudah bergabung $joinedMonthsAgo bulan lalu (default 24 > ambang
     * default 15 bulan, jadi eligible di mode masa kerja). */
    private function makeUser(int $joinedMonthsAgo = 24): User
    {
        $company = Companies::create(['code' => 'T' . strtoupper(substr(md5(uniqid('', true)), 0, 5)), 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $type = EmployeeTypes::create(['name' => 'Karyawan Kontrak', 'category' => EmployeeTypeCategory::KONTRAK->value, 'company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        UserProfiles::create(['user_id' => $user->id, 'employee_type_id' => $type->id, 'joining_date' => now()->subMonths($joinedMonthsAgo)->toDateString()]);
        EmployeeContract::create([
            'employee_id' => $user->employee->id, 'type' => 'kontrak', 'company_id' => $company->id, 'employee_type_id' => $type->id,
            'start_date' => now()->subMonths(6)->toDateString(), 'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        return $user->fresh(['profile.employeeType']);
    }

    private function setBukaTutup(User $user, bool $modeBukaTutup, bool $dibuka): void
    {
        Setting::create([
            'company_id' => $user->company_id, 'module' => 'kepegawaian',
            'key' => KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->value,
            'type' => 'string', 'label' => KepegawaianSettingEnum::PENGANGKATAN_MODE_BUKA_TUTUP->label(),
            'value' => $modeBukaTutup ? 'buka_tutup' : 'masa_kerja',
        ]);
        Setting::create([
            'company_id' => $user->company_id, 'module' => 'kepegawaian',
            'key' => KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->value,
            'type' => 'boolean', 'label' => KepegawaianSettingEnum::PENGANGKATAN_DIBUKA->label(),
            'value' => $dibuka ? '1' : '0',
        ]);
    }

    public function test_mode_masa_kerja_eligible_only_after_minimum_tenure(): void
    {
        $this->assertTrue($this->makeUser(24)->isEligibleForPengangkatanTetap());
        $this->assertFalse($this->makeUser(6)->isEligibleForPengangkatanTetap());
    }

    public function test_mode_buka_tutup_closed_blocks_even_when_tenure_is_long(): void
    {
        $user = $this->makeUser(60);
        $this->setBukaTutup($user, modeBukaTutup: true, dibuka: false);

        $this->assertFalse($user->isEligibleForPengangkatanTetap());

        $this->actingAs($user);
        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->assertSet('canStartNew', false)
            ->call('startApplication')
            ->assertForbidden();

        $this->assertDatabaseCount('sikartap', 0);
    }

    public function test_mode_buka_tutup_open_allows_even_when_tenure_is_short(): void
    {
        $user = $this->makeUser(2);
        $this->setBukaTutup($user, modeBukaTutup: true, dibuka: true);

        $this->assertTrue($user->isEligibleForPengangkatanTetap());

        $this->actingAs($user);
        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->assertSet('canStartNew', true)
            ->call('startApplication');

        $this->assertDatabaseHas('sikartap', ['user_id' => $user->id]);
    }

    public function test_dibuka_flag_is_ignored_when_mode_buka_tutup_is_off(): void
    {
        $user = $this->makeUser(2);
        $this->setBukaTutup($user, modeBukaTutup: false, dibuka: true);

        $this->assertFalse($user->isEligibleForPengangkatanTetap());
    }

    public function test_closing_does_not_lock_an_application_already_in_progress(): void
    {
        Storage::fake('public');

        $user = $this->makeUser(60);
        Sikartap::create(['user_id' => $user->id]);
        $this->setBukaTutup($user, modeBukaTutup: true, dibuka: false);
        $this->actingAs($user);

        Livewire::test(PengajuanKaryawanTetapPage::class)
            ->set('uploads.ktp_kk', UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'))
            ->call('uploadDocument', 'ktp_kk')
            ->assertHasNoErrors();
    }

    public function test_non_kontrak_user_is_never_eligible_even_when_open(): void
    {
        $company = Companies::create(['code' => 'TS2', 'name' => 'Test 2', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->setBukaTutup($user, modeBukaTutup: true, dibuka: true);

        $this->assertFalse($user->isEligibleForPengangkatanTetap());
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
