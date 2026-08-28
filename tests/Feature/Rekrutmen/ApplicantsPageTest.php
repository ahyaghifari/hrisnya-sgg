<?php

namespace Tests\Feature\Rekrutmen;

use App\Livewire\Rekrutmen\ApplicantDetailPage;
use App\Livewire\Rekrutmen\ApplicantsPage;
use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 2 — halaman read-only pelamar/kandidat dari API Rekrutmen eksternal. */
class ApplicantsPageTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'services.rekrutmen.base_url' => 'http://rekrutmen.test/api',
            'services.rekrutmen.token' => 'tok',
            'livewire.temporary_file_upload.disk' => 'local',
        ]);
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
    }

    private function opening(): JobOpenings
    {
        $jp = JobPositions::create(['company_id' => $this->company->id, 'name' => 'Staff IT', 'planned_manpower' => 1]);

        return JobOpenings::create([
            'company_id' => $this->company->id,
            'job_position_id' => $jp->id,
            'alias' => 'Staff IT',
            'quota' => 1,
            'is_active' => true,
        ]);
    }

    private function actingAsRecruiter(bool $canDecide = false): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $role = Role::firstOrCreate(['name' => $canDecide ? 'RecruiterDecider' : 'Recruiter'], ['guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'View:JobOpenings', 'guard_name' => 'web']));
        if ($canDecide) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => 'Update:JobOpenings', 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeApplication(string $id, string $status, array $overrides = []): void
    {
        Http::fake([
            "*/applications/{$id}" => Http::response(array_merge([
                'job_application_id' => $id, 'status' => $status,
                'applicant_name' => 'Budi', 'applicant_email' => 'b@x.id',
                'job_opening' => ['hris_job_opening_id' => 'JO1', 'title' => 'Staff IT'],
                'has_candidate_data' => false, 'requires_mcu' => true, 'status_logs' => [],
                'offering' => ['recruiter_uploaded_at' => null, 'signed_uploaded_at' => null],
            ], $overrides)),
            "*/candidates/{$id}" => Http::response(['message' => 'not yet'], 409),
            "*/applications/{$id}/status" => Http::response([
                'message' => 'Updated', 'job_application_id' => $id, 'from' => $status, 'to' => 'x', 'candidate_created' => false,
            ]),
            "*/applications/{$id}/interview" => Http::response(['message' => 'Scheduled', 'status' => 'interview']),
            "*/applications/{$id}/interview-result" => Http::response(['message' => 'Recorded', 'status' => 'offering', 'interview_result' => 'lulus']),
            "*/applications/{$id}/offering-letter/advance" => Http::response(['message' => 'Advanced', 'status' => 'mcu']),
            "*/applications/{$id}/offering-letter" => Http::response(['message' => 'Uploaded']),
        ]);
    }

    public function test_blocked_without_permission(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));

        Livewire::test(ApplicantsPage::class, ['record' => $this->opening()])->assertForbidden();
    }

    public function test_lists_applicants_from_rekrutmen_api(): void
    {
        $this->actingAsRecruiter();
        $opening = $this->opening();

        Http::fake([
            "*/job-openings/{$opening->id}/applications*" => Http::response(['data' => [
                ['job_application_id' => 'APP1', 'status' => 'screening', 'applicant_name' => 'Budi', 'applicant_email' => 'b@x.id', 'updated_at' => '2026-08-20T00:00:00+00:00'],
                ['job_application_id' => 'APP2', 'status' => 'interview', 'applicant_name' => 'Sari', 'applicant_email' => null, 'updated_at' => null],
            ]]),
        ]);

        Livewire::test(ApplicantsPage::class, ['record' => $opening])
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('Sari');
    }

    public function test_empty_state_when_api_fails(): void
    {
        $this->actingAsRecruiter();
        $opening = $this->opening();

        Http::fake(["*/applications*" => Http::response('boom', 500)]);

        Livewire::test(ApplicantsPage::class, ['record' => $opening])
            ->assertOk()
            ->assertSee('tidak bisa dimuat');
    }

    public function test_detail_blocked_without_permission(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])->assertForbidden();
    }

    public function test_detail_shows_status_from_api(): void
    {
        $this->actingAsRecruiter();

        Http::fake([
            '*/applications/APP1' => Http::response([
                'job_application_id' => 'APP1', 'status' => 'interview',
                'applicant_name' => 'Budi', 'applicant_email' => 'b@x.id',
                'job_opening' => ['hris_job_opening_id' => 'JO1', 'title' => 'Staff IT'],
                'has_candidate_data' => false, 'status_logs' => [],
            ]),
            '*/candidates/APP1' => Http::response(['message' => 'not yet'], 409),
        ]);

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('setelah tahap Onboarding');
    }

    public function test_decision_buttons_hidden_without_update_permission(): void
    {
        $this->actingAsRecruiter(canDecide: false);
        $this->fakeApplication('APP1', 'screening');

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->assertOk()
            ->assertDontSee('Loloskan');
    }

    public function test_loloskan_sends_shortlisted_to_rekrutmen(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        $this->fakeApplication('APP1', 'screening');

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->assertSee('Loloskan')
            ->call('loloskanBerkas');

        Http::assertSent(fn ($req) => $req->method() === 'PATCH'
            && str_contains($req->url(), '/applications/APP1/status')
            && $req['status'] === 'shortlisted');
    }

    public function test_transition_failure_does_not_crash(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        Http::fake([
            '*/applications/APP1' => Http::response([
                'job_application_id' => 'APP1', 'status' => 'applied', 'applicant_name' => 'Budi',
                'applicant_email' => null, 'job_opening' => ['hris_job_opening_id' => 'JO1', 'title' => 'X'],
                'has_candidate_data' => false, 'status_logs' => [],
            ]),
            '*/candidates/APP1' => Http::response(['message' => 'x'], 409),
            '*/applications/APP1/status' => Http::response(['message' => "Transisi 'applied' → 'shortlisted' tidak diizinkan."], 422),
        ]);

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->call('loloskanBerkas')
            ->assertOk();
    }

    public function test_jadwalkan_wawancara_sends_schedule(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        $this->fakeApplication('APP1', 'shortlisted');

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->set('intvDate', '2026-09-10')
            ->set('intvTime', '09:30')
            ->set('intvLocation', 'Ruang HRD')
            ->call('jadwalkanWawancara');

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/applications/APP1/interview')
            && ! str_contains($req->url(), 'interview-result')
            && $req['interview_date'] === '2026-09-10'
            && $req['interview_time'] === '09:30');
    }

    public function test_jadwalkan_wawancara_requires_date(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        $this->fakeApplication('APP1', 'shortlisted');

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->call('jadwalkanWawancara')
            ->assertHasErrors('intvDate');

        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/interview'));
    }

    public function test_catat_hasil_wawancara_lulus_sends_result(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        $this->fakeApplication('APP1', 'interview');

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->call('catatHasilWawancara', 'lulus');

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/applications/APP1/interview-result')
            && $req['result'] === 'lulus');
    }

    public function test_offering_upload_field_visible_and_validates(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        $this->fakeApplication('APP1', 'offering');

        // Upload multipart tidak bisa di-assert lewat harness Livewire file-upload di sini
        // (TemporaryUploadedFile fake tidak persist readable) — cukup pastikan field muncul
        // & aksi jalan tanpa error validasi ketika file valid di-set.
        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->assertOk()
            ->assertSee('Unggah surat penawaran')
            ->set('offeringFile', \Illuminate\Http\UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'))
            ->call('unggahOfferingLetter')
            ->assertHasNoErrors('offeringFile');
    }

    public function test_lanjut_setelah_offering_calls_advance(): void
    {
        $this->actingAsRecruiter(canDecide: true);
        $this->fakeApplication('APP1', 'offering', [
            'offering' => ['recruiter_uploaded_at' => '2026-09-01T00:00:00+00:00', 'signed_uploaded_at' => '2026-09-03T00:00:00+00:00'],
        ]);

        Livewire::test(ApplicantDetailPage::class, ['jobApplication' => 'APP1'])
            ->assertSee('Lanjut ke MCU')
            ->call('lanjutSetelahOffering');

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/applications/APP1/offering-letter/advance'));
    }
}
