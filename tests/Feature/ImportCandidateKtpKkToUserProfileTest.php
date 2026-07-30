<?php

namespace Tests\Feature;

use App\Models\Candidate\CandidateDocuments;
use App\Models\Candidate\Candidates;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfileDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCandidateKtpKkToUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_ktp_and_kk_for_candidate_matching_an_existing_user_by_email(): void
    {
        Storage::fake('public');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'email' => 'sama@syifaglobal.com']);

        $candidate = Candidates::create([
            'name' => 'Kandidat Sama',
            'email' => 'sama@syifaglobal.com',
            'password' => 'secret',
        ]);

        Storage::disk('public')->put('candidate-documents/' . $candidate->id . '/ktp.jpg', 'fake-ktp-content');
        Storage::disk('public')->put('candidate-documents/' . $candidate->id . '/kk.pdf', 'fake-kk-content');

        CandidateDocuments::create([
            'candidate_id' => $candidate->id,
            'doc_type' => 'ktp',
            'file_path' => 'candidate-documents/' . $candidate->id . '/ktp.jpg',
        ]);
        CandidateDocuments::create([
            'candidate_id' => $candidate->id,
            'doc_type' => 'kk',
            'file_path' => 'candidate-documents/' . $candidate->id . '/kk.pdf',
        ]);
        // Tipe lain (ijazah) sengaja TIDAK ikut disalin - command ini khusus KTP/KK.
        CandidateDocuments::create([
            'candidate_id' => $candidate->id,
            'doc_type' => 'ijazah',
            'file_path' => 'candidate-documents/' . $candidate->id . '/ijazah.pdf',
        ]);

        $this->artisan('candidates:import-ktp-kk')->assertSuccessful();

        $labels = UserProfileDocument::where('user_id', $user->id)->pluck('label');
        $this->assertEqualsCanonicalizing(['KTP', 'KK'], $labels->all());

        $ktp = UserProfileDocument::where('user_id', $user->id)->where('label', 'KTP')->first();
        Storage::disk('public')->assertExists($ktp->file_path);
    }

    public function test_skips_candidate_with_no_matching_user(): void
    {
        Storage::fake('public');

        $candidate = Candidates::create([
            'name' => 'Kandidat Tanpa Akun',
            'email' => 'belum-jadi-karyawan@syifaglobal.com',
            'password' => 'secret',
        ]);

        CandidateDocuments::create([
            'candidate_id' => $candidate->id,
            'doc_type' => 'ktp',
            'file_path' => 'candidate-documents/' . $candidate->id . '/ktp.jpg',
        ]);

        $this->artisan('candidates:import-ktp-kk')->assertSuccessful();

        $this->assertSame(0, UserProfileDocument::count());
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        Storage::fake('public');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'email' => 'sama@syifaglobal.com']);

        $candidate = Candidates::create([
            'name' => 'Kandidat Sama',
            'email' => 'sama@syifaglobal.com',
            'password' => 'secret',
        ]);

        Storage::disk('public')->put('candidate-documents/' . $candidate->id . '/ktp.jpg', 'fake-ktp-content');
        CandidateDocuments::create([
            'candidate_id' => $candidate->id,
            'doc_type' => 'ktp',
            'file_path' => 'candidate-documents/' . $candidate->id . '/ktp.jpg',
        ]);

        $this->artisan('candidates:import-ktp-kk')->assertSuccessful();
        $this->artisan('candidates:import-ktp-kk')->assertSuccessful();

        $this->assertSame(1, UserProfileDocument::where('user_id', $user->id)->where('label', 'KTP')->count());
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        Storage::fake('public');

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        User::factory()->create(['company_id' => $company->id, 'email' => 'sama@syifaglobal.com']);

        $candidate = Candidates::create([
            'name' => 'Kandidat Sama',
            'email' => 'sama@syifaglobal.com',
            'password' => 'secret',
        ]);

        Storage::disk('public')->put('candidate-documents/' . $candidate->id . '/ktp.jpg', 'fake-ktp-content');
        CandidateDocuments::create([
            'candidate_id' => $candidate->id,
            'doc_type' => 'ktp',
            'file_path' => 'candidate-documents/' . $candidate->id . '/ktp.jpg',
        ]);

        $this->artisan('candidates:import-ktp-kk --dry-run')->assertSuccessful();

        $this->assertSame(0, UserProfileDocument::count());
    }
}
