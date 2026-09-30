<?php

namespace Tests\Feature;

use App\Livewire\SubmitPermissionRequest;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Izin Sakit hari pertama tidak wajib lampiran, tapi begitu masuk hari ke-2 (total_days > 1)
 * wajib surat/bukti sakit - dikonfirmasi user 2026-07-31, lihat komentar di
 * SubmitPermissionRequest::rules().
 */
class SubmitPermissionRequestSakitAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_day_sakit_does_not_require_attachment(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $component = Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10');

        $this->assertFalse($component->get('lampiranWajib'), 'Company generik tidak dapat bintang wajib.');

        $component->call('submit')->assertHasNoErrors();
    }

    public function test_two_day_sakit_without_attachment_fails_validation(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-11')
            ->call('submit')
            ->assertHasErrors(['attachments']);
    }

    public function test_two_day_sakit_with_attachment_passes_validation(): void
    {
        Storage::fake('public');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-11')
            ->set('attachments', [UploadedFile::fake()->create('surat-sakit.pdf', 100, 'application/pdf')])
            ->call('submit')
            ->assertHasNoErrors();
    }

    /** 2 company override (Banjarbaru/Barabai, lihat HasCompanyLabelOverride) - Izin Sakit
     * ("Izin Sakit Bersurat") wajib lampiran dari HARI PERTAMA, tidak nunggu >1 hari seperti
     * company lain (instruksi user 2026-09-30). Label section juga polos "Lampiran" tanpa syarat
     * hari (lihat SubmitPermissionRequest::getLampiranLabelSuffixProperty()). */
    public function test_single_day_sakit_requires_attachment_for_the_two_override_companies(): void
    {
        $banjarbaru = new Companies();
        $banjarbaru->forceFill(['id' => '01k8271xa4nq8cezg3dq9vfr14', 'code' => '0001', 'name' => 'RSU Syifa Medika Banjarbaru', 'location' => 'X', 'is_active' => true]);
        $banjarbaru->save();

        $user = User::factory()->create(['company_id' => $banjarbaru->id]);
        $this->actingAs($user);

        $component = Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10');

        $this->assertSame('', $component->get('lampiranLabelSuffix'), 'Label section jadi polos "Lampiran" tanpa syarat hari.');
        $this->assertTrue($component->get('lampiranWajib'), 'Bintang wajib tampil buat 2 company override.');

        $component->call('submit')->assertHasErrors(['attachments']);

        Storage::fake('public');
        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10')
            ->set('attachments', [UploadedFile::fake()->create('surat-sakit.pdf', 100, 'application/pdf')])
            ->call('submit')
            ->assertHasNoErrors();
    }
}
