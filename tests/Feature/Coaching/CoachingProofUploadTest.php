<?php

namespace Tests\Feature\Coaching;

use App\Livewire\Coaching\CoachingDetailPage;
use App\Models\Coaching\Coaching;
use App\Models\Master\Companies;
use App\Models\User;
use App\Notifications\CoachingProofSubmitted;
use App\Notifications\CoachingReviewDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Coaching Saya" sebelumnya cuma list read-only tanpa link kemana pun — karyawan yang
 * dicoaching tidak bisa upload bukti pelaksanaan sama sekali. Sekarang reuse CoachingDetailPage
 * yang sama dengan sisi coach (bukan halaman terpisah), tambah form upload buat subject-nya.
 */
class CoachingProofUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoaching(string $status = 'in_progress'): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $coach = User::factory()->create(['company_id' => $company->id]);

        $coaching = Coaching::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'coach_by' => $coach->id,
            'title' => 'Perbaikan performa',
            'improvement_due_date' => now()->addWeek(),
            'status' => $status,
        ]);

        return [$employee, $coach, $coaching];
    }

    public function test_subject_can_view_own_coaching_detail(): void
    {
        [$employee, , $coaching] = $this->makeCoaching();

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertSee('Perbaikan performa')
            ->assertSet('isSubject', true)
            ->assertSet('canUpload', true)
            ->assertSet('canReview', false);
    }

    public function test_subject_can_upload_proof_and_status_becomes_submitted(): void
    {
        Storage::fake('public');
        [$employee, , $coaching] = $this->makeCoaching();

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->set('proofs', [UploadedFile::fake()->image('bukti.jpg')])
            ->call('submitProofs');

        $this->assertSame('submitted', $coaching->fresh()->status);
        $this->assertDatabaseHas('coaching_proofs', [
            'coaching_id' => $coaching->id,
            'status' => 'submitted',
        ]);
    }

    public function test_coach_gets_notified_when_proof_submitted(): void
    {
        Notification::fake();
        Storage::fake('public');
        [$employee, $coach, $coaching] = $this->makeCoaching();

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->set('proofs', [UploadedFile::fake()->image('bukti.jpg')])
            ->call('submitProofs');

        Notification::assertSentTo($coach, CoachingProofSubmitted::class);
    }

    public function test_subject_cannot_upload_when_status_already_submitted(): void
    {
        [$employee, , $coaching] = $this->makeCoaching('submitted');

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertSet('canUpload', false);
    }

    public function test_coach_cannot_upload_proof_only_subject_can(): void
    {
        [, $coach, $coaching] = $this->makeCoaching();

        Livewire::actingAs($coach)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertSet('isSubject', false)
            ->assertSet('canUpload', false);
    }

    public function test_subject_can_resubmit_after_rejected_and_old_proof_keeps_rejected_status(): void
    {
        Storage::fake('public');
        [$employee, $coach, $coaching] = $this->makeCoaching();

        // Ronde 1: ajukan bukti, coach tolak.
        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->set('proofs', [UploadedFile::fake()->image('bukti-1.jpg')])
            ->call('submitProofs');

        Livewire::actingAs($coach)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->call('reject');

        $this->assertSame('rejected', $coaching->fresh()->status);

        // Ronde 2: karyawan masih bisa upload ulang. Bukti lama TETAP tercatat "rejected" (jejak
        // riwayat per-bukti dikembalikan lagi, instruksi user 2026-07-26) — bukti baru mulai
        // dari status "submitted" lagi.
        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertSet('canUpload', true)
            ->set('proofs', [UploadedFile::fake()->image('bukti-2.jpg')])
            ->call('submitProofs');

        $this->assertSame('submitted', $coaching->fresh()->status);
        $this->assertSame(2, $coaching->proofs()->count());
        $this->assertSame(1, $coaching->fresh()->proofs()->where('status', 'rejected')->count());
        $this->assertSame(1, $coaching->fresh()->proofs()->where('status', 'submitted')->count());
    }

    public function test_approve_marks_submitted_proofs_completed(): void
    {
        Storage::fake('public');
        [$employee, $coach, $coaching] = $this->makeCoaching();

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->set('proofs', [UploadedFile::fake()->image('bukti.jpg')])
            ->call('submitProofs');

        Livewire::actingAs($coach)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->call('approve');

        $this->assertSame('completed', $coaching->fresh()->status);
    }

    public function test_subject_gets_notified_when_approved(): void
    {
        Notification::fake();
        Storage::fake('public');
        [$employee, $coach, $coaching] = $this->makeCoaching();

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->set('proofs', [UploadedFile::fake()->image('bukti.jpg')])
            ->call('submitProofs');

        Livewire::actingAs($coach)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->call('approve');

        Notification::assertSentTo($employee, CoachingReviewDecided::class, fn ($n) => $n->toDatabase($employee)['title'] === 'Coaching Selesai');
    }

    public function test_subject_gets_notified_when_rejected(): void
    {
        Notification::fake();
        Storage::fake('public');
        [$employee, $coach, $coaching] = $this->makeCoaching();

        Livewire::actingAs($employee)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->set('proofs', [UploadedFile::fake()->image('bukti.jpg')])
            ->call('submitProofs');

        Livewire::actingAs($coach)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->call('reject');

        Notification::assertSentTo($employee, CoachingReviewDecided::class, fn ($n) => $n->toDatabase($employee)['title'] === 'Bukti Coaching Ditolak');
    }

    public function test_unrelated_user_cannot_view_coaching_detail(): void
    {
        [, , $coaching] = $this->makeCoaching();
        $stranger = User::factory()->create();

        Livewire::actingAs($stranger)
            ->test(CoachingDetailPage::class, ['record' => $coaching])
            ->assertForbidden();
    }
}
