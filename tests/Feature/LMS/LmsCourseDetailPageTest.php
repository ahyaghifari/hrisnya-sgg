<?php

namespace Tests\Feature\LMS;

use App\Enum\CourseUserStatus;
use App\Enum\LMS\TrainingCategoryEnum;
use App\Livewire\LMS\LmsCourseDetailPage;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsQuizResults;
use App\Models\LMS\LmsQuizzes;
use App\Models\LMS\LmsRespondent;
use App\Models\LMS\Trainer\LmsHonorarium;
use App\Models\LMS\Trainer\LmsHonorariumCategory;
use App\Models\LMS\Trainer\LmsHonorariumVariable;
use App\Models\LMS\Trainer\Trainers;
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
        Permission::firstOrCreate(['name' => 'edit_detail_kursus_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS Course Detail Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms', 'edit_detail_kursus_lms']);

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

    public function test_saves_fixed_honor_amount_for_non_scored_category(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $category = LmsCategories::create(['name' => 'Umum', 'training_category' => TrainingCategoryEnum::UMUM]);
        $course = $this->makeCourse($company->id);
        $course->update(['category_id' => $category->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $trainer = Trainers::create(['type' => 'internal', 'name' => 'Trainer Uji', 'is_active' => true]);
        $course->meetings()->create([
            'title' => 'Pelatihan Uji',
            'trainer_id' => $trainer->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $course->refresh();

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'penilaian')
            ->assertSet('usesScoredHonor', false)
            ->set('honorFixedAmount', '500000')
            ->call('saveHonorFixed')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('trainer_assessment_results', [
            'course_id' => $course->id,
            'trainer_id' => $trainer->id,
            'honor_amount' => 500000,
        ]);
    }

    public function test_saves_scored_honor_amount_matching_range(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $category = LmsCategories::create(['name' => 'Eksternal', 'training_category' => TrainingCategoryEnum::EKSTERNAL]);
        $course = $this->makeCourse($company->id);
        $course->update(['category_id' => $category->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $trainer = Trainers::create(['type' => 'external', 'name' => 'Trainer Eksternal', 'is_active' => true]);
        $course->meetings()->create([
            'title' => 'Pelatihan Uji',
            'trainer_id' => $trainer->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $course->refresh();

        $variable = LmsHonorariumVariable::create(['context' => 'eksternal', 'name' => 'Lama Praktik']);
        $catLow = LmsHonorariumCategory::create(['variable_id' => $variable->id, 'name' => '< 10 Tahun', 'score' => 1]);
        $catHigh = LmsHonorariumCategory::create(['variable_id' => $variable->id, 'name' => '10-19 Tahun', 'score' => 5]);
        LmsHonorarium::create(['context' => 'eksternal', 'min_score' => 0, 'max_score' => 2, 'honor_amount' => 100000]);
        LmsHonorarium::create(['context' => 'eksternal', 'min_score' => 3, 'max_score' => 10, 'honor_amount' => 300000]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'penilaian')
            ->assertSet('usesScoredHonor', true)
            ->set("honorCategorySelections.{$variable->id}", $catHigh->id)
            ->call('saveHonorScored')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('trainer_assessment_results', [
            'course_id' => $course->id,
            'trainer_id' => $trainer->id,
            'honor_amount' => 300000,
        ]);

        $this->assertDatabaseHas('lms_meeting_honorarium_details', [
            'variable_id' => $variable->id,
            'category_id' => $catHigh->id,
            'score' => 5,
        ]);
    }

    public function test_honorarium_tab_requires_selecting_a_meeting_for_multi_day_course(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->meetings()->create(['title' => 'Sesi 1', 'start_date' => '2026-08-10', 'end_date' => '2026-08-11']);
        $course->refresh();

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'penilaian')
            ->assertSee('Pilih pertemuan dulu');
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

    public function test_delete_participant_button_hidden_and_blocked_when_course_published(): void
    {
        $company = $this->makeCompany('CPG', 'Company G');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id); // makeCourse() -> status published by default
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Peserta Published']);

        $participant = LmsCourseUsers::create([
            'course_id' => $course->id,
            'user_id' => $employee->id,
            'is_mandatory' => true,
            'status' => CourseUserStatus::APPROVED->value,
            'score' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->assertSee('Peserta Published')
            ->assertDontSeeHtml("wire:click=\"removeParticipant('{$participant->id}')\"")
            ->call('removeParticipant', $participant->id)
            ->assertForbidden();

        $this->assertDatabaseHas('lms_course_users', ['id' => $participant->id]);
    }

    public function test_delete_participant_button_shown_and_works_when_course_draft(): void
    {
        $company = $this->makeCompany('CPH', 'Company H');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->update(['status' => \App\Enum\LsmCourseStatus::Draft->value]);
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
            ->assertSeeHtml("wire:click=\"removeParticipant('{$participant->id}')\"")
            ->call('removeParticipant', $participant->id);

        $this->assertDatabaseMissing('lms_course_users', ['id' => $participant->id]);
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
            ->set('meetingStartTime', '08:00')
            ->set('meetingEndTime', '10:00')
            ->call('saveMeeting')
            ->assertSee('Sesi Hari Kedua');

        $this->assertDatabaseHas('lms_meetings', [
            'course_id' => $course->id,
            'title' => 'Sesi Hari Kedua',
            'trainer_id' => $trainer->id,
            'attendance_type' => 'offline',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
    }

    public function test_trainer_options_excludes_inactive_trainers(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $active = \App\Models\LMS\Trainer\Trainers::create(['type' => 'internal', 'name' => 'Trainer Aktif', 'is_active' => true]);
        $inactive = \App\Models\LMS\Trainer\Trainers::create(['type' => 'internal', 'name' => 'Trainer Nonaktif', 'is_active' => false]);

        $options = Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->instance()->trainerOptions;

        $this->assertTrue($options->has($active->id));
        $this->assertFalse($options->has($inactive->id));
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
            ->set('meetingStartTime', '08:00')
            ->set('meetingEndTime', '10:00')
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

    public function test_saving_quiz_with_graduation_requirement_checked_persists_flag(): void
    {
        $company = $this->makeCompany('CPF', 'Company F');
        $admin = $this->actingAsLmsAdmin($company->id);
        $category = LmsCategories::create(['name' => 'Kategori F']);
        $course = $this->makeCourse($company->id);
        $course->update(['category_id' => $category->id, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $meeting = $course->meetings()->create([
            'title' => 'Sesi Utama',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $quiz = LmsQuizzes::create(['category_id' => $category->id, 'title' => 'Quiz Kelulusan']);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->call('toggleMeetingAccordion', $meeting->id)
            ->call('openAddQuizForm', $meeting->id)
            ->set('quizId', $quiz->id)
            ->set('quizDuration', 20)
            ->set('quizIsGraduationRequirement', true)
            ->call('saveQuiz')
            ->assertSee('Penentu Kelulusan');

        $this->assertDatabaseHas('lms_meeting_quizzes', [
            'meeting_id' => $meeting->id,
            'quiz_id' => $quiz->id,
            'is_graduation_requirement' => true,
        ]);
    }

    public function test_quiz_results_summary_and_list_modals_show_employee_and_public_results(): void
    {
        $company = $this->makeCompany('CPI', 'Company I');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $meeting = $course->meetings()->create(['title' => 'Sesi Utama']);
        $quiz = LmsQuizzes::create(['title' => 'Quiz Hasil']);
        $meetingQuiz = \App\Models\LMS\LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);

        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Karyawan Uji']);
        $employeeRespondent = LmsRespondent::create([
            'course_id' => $course->id,
            'user_id' => $employee->id,
            'name' => $employee->name,
            'category' => LmsRespondent::CATEGORY_KUIS,
        ]);
        LmsQuizResults::create([
            'meeting_quiz_id' => $meetingQuiz->id,
            'quiz_id' => $quiz->id,
            'respondent_id' => $employeeRespondent->id,
            'total_score' => 80,
            'max_score' => 100,
            'correct_count' => 4,
            'wrong_count' => 1,
            'is_completed' => true,
            'status' => 'completed',
        ]);

        $publicRespondent = LmsRespondent::create([
            'course_id' => $course->id,
            'name' => 'Tamu Publik',
            'category' => LmsRespondent::CATEGORY_KUIS,
        ]);
        LmsQuizResults::create([
            'meeting_quiz_id' => $meetingQuiz->id,
            'quiz_id' => $quiz->id,
            'respondent_id' => $publicRespondent->id,
            'total_score' => 60,
            'max_score' => 100,
            'correct_count' => 3,
            'wrong_count' => 2,
            'is_completed' => true,
            'status' => 'completed',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('showQuizResultsSummary', $meetingQuiz->id)
            ->assertSet('quizResultsMode', 'summary')
            ->assertSee('Rangkuman Hasil Kuis')
            ->assertSee('70'); // rata-rata (80+60)/2

        $summary = $component->instance()->quizResultsSummary;
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['employee_count']);
        $this->assertSame(1, $summary['public_count']);

        $component->call('showQuizResultsList', $meetingQuiz->id)
            ->assertSet('quizResultsMode', 'list')
            ->assertSee('Karyawan Uji')
            ->assertSee('Tamu Publik')
            ->assertSee('Karyawan')
            ->assertSee('Publik');

        $component->call('closeQuizResultsModal')
            ->assertSet('quizResultsMeetingQuizId', null);
    }

    public function test_delete_quiz_button_hidden_and_blocked_once_answered(): void
    {
        $company = $this->makeCompany('CPJ', 'Company J');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $meeting = $course->meetings()->create(['title' => 'Sesi Utama']);
        $quiz = LmsQuizzes::create(['title' => 'Quiz Terjawab']);
        $meetingQuiz = \App\Models\LMS\LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);

        $respondent = LmsRespondent::create([
            'course_id' => $course->id,
            'name' => 'Tamu',
            'category' => LmsRespondent::CATEGORY_KUIS,
        ]);
        LmsQuizResults::create([
            'meeting_quiz_id' => $meetingQuiz->id,
            'quiz_id' => $quiz->id,
            'respondent_id' => $respondent->id,
            'total_score' => 100,
            'max_score' => 100,
            'is_completed' => true,
            'status' => 'completed',
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->call('toggleMeetingAccordion', $meeting->id)
            ->assertDontSeeHtml("wire:click=\"removeQuiz('{$meetingQuiz->id}')\"")
            ->call('removeQuiz', $meetingQuiz->id)
            ->assertForbidden();

        $this->assertDatabaseHas('lms_meeting_quizzes', ['id' => $meetingQuiz->id]);
    }

    public function test_delete_quiz_button_shown_and_works_when_unanswered(): void
    {
        $company = $this->makeCompany('CPK', 'Company K');
        $admin = $this->actingAsLmsAdmin($company->id);
        $category = LmsCategories::create(['name' => 'Kategori K']);
        $course = $this->makeCourse($company->id);
        $course->update(['category_id' => $category->id]);
        $meeting = $course->meetings()->create(['title' => 'Sesi Utama']);
        $quiz = LmsQuizzes::create(['category_id' => $category->id, 'title' => 'Quiz Belum Terjawab']);
        $meetingQuiz = \App\Models\LMS\LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'pertemuan')
            ->call('toggleMeetingAccordion', $meeting->id)
            ->assertSeeHtml("wire:click=\"removeQuiz('{$meetingQuiz->id}')\"")
            ->call('removeQuiz', $meetingQuiz->id);

        $this->assertDatabaseMissing('lms_meeting_quizzes', ['id' => $meetingQuiz->id]);
    }

    public function test_shows_feedback_tab(): void
    {
        $company = $this->makeCompany('CPA', 'Company A');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->update(['requires_feedback' => true]);
        $employee = User::factory()->create(['company_id' => $company->id]);

        $respondent = LmsRespondent::create([
            'course_id' => $course->id,
            'user_id' => $employee->id,
            'name' => $employee->name,
            'category' => LmsRespondent::CATEGORY_UMPAN_BALIK,
        ]);

        $course->feedbacks()->create([
            'respondent_id' => $respondent->id,
            'rating' => 5,
            'feedback' => 'Sangat bagus',
        ]);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('setTab', 'umpan-balik')
            ->assertSee('Sangat bagus');
    }

    public function test_umpan_balik_tab_and_qr_button_hidden_when_requires_feedback_false(): void
    {
        $company = $this->makeCompany('CPD', 'Company D');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->update(['requires_feedback' => false, 'feedback_public_token' => 'leftover-token']);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->assertDontSee('Umpan Balik')
            ->assertDontSee('QR Umpan Balik')
            ->call('setTab', 'umpan-balik')
            ->assertSet('tab', 'peserta');
    }

    public function test_umpan_balik_tab_and_qr_button_shown_when_requires_feedback_true(): void
    {
        $company = $this->makeCompany('CPE', 'Company E');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->update(['requires_feedback' => true, 'feedback_public_token' => 'active-token']);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->assertSee('Umpan Balik')
            ->assertSee('QR Umpan Balik')
            ->call('setTab', 'umpan-balik')
            ->assertSet('tab', 'umpan-balik');
    }

    public function test_publishing_course_generates_public_tokens_for_quiz_and_feedback(): void
    {
        $company = $this->makeCompany('CPB', 'Company B');
        $admin = $this->actingAsLmsAdmin($company->id);
        $course = $this->makeCourse($company->id);
        $course->update(['status' => \App\Enum\LsmCourseStatus::Draft->value, 'requires_feedback' => true]);

        $trainer = Trainers::create(['type' => 'internal', 'name' => 'Trainer Uji', 'is_active' => true]);
        $meeting = $course->meetings()->create([
            'title' => 'Sesi 1',
            'trainer_id' => $trainer->id,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
        $quiz = LmsQuizzes::create(['title' => 'Quiz Publish Test']);
        $meetingQuiz = \App\Models\LMS\LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        LmsCourseUsers::create(['course_id' => $course->id, 'user_id' => $employee->id, 'status' => 'approved']);

        Livewire::actingAs($admin)
            ->test(LmsCourseDetailPage::class, ['course' => $course])
            ->call('publishCourse')
            ->assertHasNoErrors();

        $course->refresh();
        $meetingQuiz->refresh();

        $this->assertNotNull($meetingQuiz->public_token);
        $this->assertNotNull($course->feedback_public_token);
        $this->assertSame(\App\Enum\LsmCourseStatus::Published->value, $course->status);
    }

    public function test_opening_already_published_course_self_heals_missing_feedback_token(): void
    {
        $company = $this->makeCompany('CPC', 'Company C');
        $admin = $this->actingAsLmsAdmin($company->id);
        // requires_feedback diaktifkan SETELAH course sudah published (mis. lewat toggle di form
        // Tambah Pelatihan yang baru dibuat belakangan) — tombol Publish sudah tidak ada lagi,
        // jadi token cuma bisa dilengkapi lewat self-healing di mount().
        $course = $this->makeCourse($company->id);
        $course->update(['status' => \App\Enum\LsmCourseStatus::Published->value, 'requires_feedback' => true]);

        $this->assertNull($course->feedback_public_token);

        Livewire::actingAs($admin)->test(LmsCourseDetailPage::class, ['course' => $course]);

        $this->assertNotNull($course->fresh()->feedback_public_token);
    }
}
