<?php

namespace Tests\Feature\LMS;

use App\Enum\CourseUserStatus;
use App\Livewire\LMS\LmsCourseDetailPage;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsQuizzes;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsCourseDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $code, string $name): Companies
    {
        $company = new Companies();
        $company->fill([
            'code' => $code,
            'name' => $name,
            'location' => 'Test',
            'is_active' => true,
        ]);
        $company->save();

        return $company;
    }

    private function actingAsLmsAdmin(string $companyId): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Course Detail Test', 'guard_name' => 'web']);
        $role->givePermissionTo('sistem_lms');

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCourse(string $companyId): LmsCourses
    {
        return LmsCourses::create([
            'company_id' => $companyId,
            'code' => 'TRN-001',
            'title' => 'Pelatihan Uji',
            'source_type' => 'internal',
            'status' => 'published',
        ]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $user = User::factory()->create(['company_id' => $company->id]);
        $course = $this->makeCourse($company->id);

        Livewire::actingAs($user)->test(LmsCourseDetailPage::class, ['course' => $course])->assertForbidden();
    }

    public function test_user_from_other_company_is_forbidden(): void
    {
        $companyA = $this->makeCompany('CPA', 'Company A');
        $companyB = $this->makeCompany('CPB', 'Company B');
        $admin = $this->actingAsLmsAdmin($companyB->id);
        $course = $this->makeCourse($companyA->id);

        Livewire::actingAs($admin)->test(LmsCourseDetailPage::class, ['course' => $course])->assertForbidden();
    }

    public function test_adds_participants_to_course(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->set('selectedUserIds', [$employee->id])
            ->call('addParticipants')
            ->assertSee($employee->name);

        $this->assertDatabaseHas('lms_course_users', [
            'course_id' => $course->id,
            'user_id' => $employee->id,
            'status' => CourseUserStatus::APPROVED->value,
        ]);
    }

    public function test_add_participant_form_toggles_and_excludes_already_enrolled(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Uji']);

        $component = Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('openAddParticipantForm')
            ->assertSet('showAddParticipantForm', true);

        $this->assertTrue($component->instance()->availableUserOptions->has($employee->id));

        $component->set('selectedUserIds', [$employee->id])
            ->call('addParticipants')
            ->assertSet('showAddParticipantForm', false);

        $this->assertFalse($component->instance()->availableUserOptions->has($employee->id));
    }

    public function test_cancels_participant(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $participant = LmsCourseUsers::create([
            'course_id' => $course->id,
            'user_id' => $employee->id,
            'is_mandatory' => true,
            'status' => CourseUserStatus::APPROVED->value,
            'score' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('cancelParticipant', $participant->id);

        $this->assertDatabaseHas('lms_course_users', [
            'id' => $participant->id,
            'status' => CourseUserStatus::CANCELED->value,
        ]);
    }

    public function test_shows_meetings_tab(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->meetings()->create([
            'title' => 'Sesi Utama',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->assertSee('Sesi Utama');
    }

    public function test_add_meeting_button_hidden_for_single_day_course(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        // LmsCourses::boot() 'saving' nyinkronin start_date/end_date dari meetings — course tanpa
        // meeting akan selalu ke-null-kan lagi kalau di-update() langsung, jadi bikin meeting-nya
        // supaya tanggal course ke-derive dengan benar (sama seperti alur CreateLmsCourse asli).
        $course->meetings()->create(['title' => 'Sesi Utama', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $course->refresh();

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->assertDontSee('Tambah Pertemuan');
    }

    public function test_add_meeting_button_shown_and_creates_meeting_for_multi_day_course(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->meetings()->create(['title' => 'Sesi Hari Pertama', 'start_date' => '2026-08-10', 'end_date' => '2026-08-12']);
        $course->refresh();
        $trainer = \App\Models\LMS\Trainer\Trainers::create(['type' => 'internal', 'name' => 'Trainer Uji']);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->assertSee('Tambah Pertemuan')
            ->call('openAddMeetingForm')
            ->set('meetingTitle', 'Sesi Hari Kedua')
            ->set('meetingTrainerId', $trainer->id)
            ->set('meetingStartDate', '2026-08-11')
            ->set('meetingEndDate', '2026-08-11')
            ->call('saveMeeting')
            ->assertSee('Sesi Hari Kedua');

        $this->assertDatabaseHas('lms_meetings', [
            'course_id' => $course->id,
            'title' => 'Sesi Hari Kedua',
            'trainer_id' => $trainer->id,
            'attendance_type' => 'offline',
        ]);
    }

    public function test_add_meeting_requires_trainer(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->meetings()->create(['title' => 'Sesi Hari Pertama', 'start_date' => '2026-08-10', 'end_date' => '2026-08-12']);
        $course->refresh();

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->call('openAddMeetingForm')
            ->set('meetingTitle', 'Sesi Hari Kedua')
            ->set('meetingStartDate', '2026-08-11')
            ->set('meetingEndDate', '2026-08-11')
            ->call('saveMeeting')
            ->assertHasErrors(['meetingTrainerId']);
    }

    public function test_meeting_form_hides_title_and_dates_for_single_day_course(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $meeting = $course->meetings()->create([
            'title' => 'Pelatihan Uji',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $course->refresh();
        $trainer = \App\Models\LMS\Trainer\Trainers::create(['type' => 'internal', 'name' => 'Trainer Uji']);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->assertDontSee('Judul Pertemuan')
            ->set('meetingTrainerId', $trainer->id)
            ->call('saveMeeting');

        $this->assertDatabaseHas('lms_meetings', [
            'id' => $meeting->id,
            'title' => 'Pelatihan Uji',
            'trainer_id' => $trainer->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
    }

    public function test_adds_and_removes_material_on_meeting(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->update(['start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $meeting = $course->meetings()->create([
            'title' => 'Sesi Utama',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);

        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('materi.pdf', 100, 'application/pdf');

        $component = Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->call('toggleMeetingAccordion', $meeting->id)
            ->call('openAddMaterialForm', $meeting->id)
            ->set('materialTitle', 'Slide Materi')
            ->set('materialFile', $file)
            ->call('saveMaterial')
            ->assertSee('Slide Materi');

        $this->assertDatabaseHas('lms_materials', [
            'meeting_id' => $meeting->id,
            'title' => 'Slide Materi',
        ]);

        $material = \App\Models\LMS\LmsMaterials::where('meeting_id', $meeting->id)->first();

        $component->call('removeMaterial', $material->id)
            ->assertDontSee('Slide Materi');

        $this->assertDatabaseMissing('lms_materials', ['id' => $material->id]);
    }

    public function test_adds_and_removes_quiz_filtered_by_course_category(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $categoryA = LmsCategories::create(['name' => 'Kategori A']);
        $categoryB = LmsCategories::create(['name' => 'Kategori B']);
        $course = $this->makeCourse($company->id);
        $course->update(['category_id' => $categoryA->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $meeting = $course->meetings()->create([
            'title' => 'Sesi Utama',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);

        $quizInCategory = LmsQuizzes::create(['category_id' => $categoryA->id, 'title' => 'Quiz A']);
        LmsQuizzes::create(['category_id' => $categoryB->id, 'title' => 'Quiz B']);

        $component = Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->call('toggleMeetingAccordion', $meeting->id)
            ->call('openAddQuizForm', $meeting->id);

        $this->assertTrue($component->instance()->quizOptions->has($quizInCategory->id));
        $this->assertFalse($component->instance()->quizOptions->contains('Quiz B'));

        $component->set('quizId', $quizInCategory->id)
            ->set('quizDuration', 15)
            ->call('saveQuiz')
            ->assertSee('Quiz A');

        $this->assertDatabaseHas('lms_meeting_quizzes', [
            'meeting_id' => $meeting->id,
            'quiz_id' => $quizInCategory->id,
            'duration' => 15,
        ]);

        $meetingQuiz = \App\Models\LMS\LmsMeetingQuizzes::where('meeting_id', $meeting->id)->first();

        $component->call('removeQuiz', $meetingQuiz->id)
            ->assertDontSee('Quiz A');
    }

    public function test_shows_feedback_tab(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $course->feedbacks()->create([
            'user_id' => $employee->id,
            'rating' => 5,
            'feedback' => 'Sangat bagus',
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'umpan-balik')
            ->assertSee('Sangat bagus');
    }
}
