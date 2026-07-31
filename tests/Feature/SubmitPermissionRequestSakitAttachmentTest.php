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

        Livewire::test(SubmitPermissionRequest::class)
            ->set('group', 'sakit')
            ->set('date', '2026-08-10')
            ->set('end_date', '2026-08-10')
            ->call('submit')
            ->assertHasNoErrors();
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
}
