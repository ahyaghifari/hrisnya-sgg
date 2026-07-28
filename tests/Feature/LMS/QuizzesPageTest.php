<?php

namespace Tests\Feature\LMS;

use App\Enum\QuestionType;
use App\Livewire\LMS\QuizzesPage;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsMeetings;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuizzes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test fitur halaman Kuis portal (QuizzesPage) — padanan portal dari Filament QuizzesResource.
 * Mencakup: gate akses, list, filter kategori, search, modal view, modal edit, dan guard hapus
 * (tidak bisa dihapus kalau sudah dipakai di pelatihan/pertemuan).
 */
class QuizzesPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(array $extraPermissions = []): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        foreach ($extraPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'LMS Quiz Test', 'guard_name' => 'web']);
        $role->givePermissionTo(array_merge(['sistem_lms'], $extraPermissions));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeQuiz(string $title, array $overrides = []): LmsQuizzes
    {
        return LmsQuizzes::create(array_merge(['title' => $title], $overrides));
    }

    private function makeQuestion(string $questionHtml, array $overrides = []): LmsQuestionBanks
    {
        return LmsQuestionBanks::create(array_merge([
            'question' => $questionHtml,
            'type' => QuestionType::MultipleChoice,
        ], $overrides));
    }

    public function test_user_without_sistem_lms_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(QuizzesPage::class)->assertForbidden();
    }

    public function test_user_with_sistem_lms_can_access_page(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)->test(QuizzesPage::class)->assertOk();
    }

    public function test_list_shows_quizzes(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $this->makeQuiz('Kuis Dasar Keperawatan');

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->assertSee('Kuis Dasar Keperawatan');
    }

    public function test_search_filters_by_title(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $this->makeQuiz('Kuis Anatomi');
        $this->makeQuiz('Kuis Farmakologi');

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->set('search', 'anatomi')
            ->assertSee('Kuis Anatomi')
            ->assertDontSee('Kuis Farmakologi');
    }

    public function test_category_filter(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $catA = LmsCategories::create(['name' => 'Kategori A']);
        $catB = LmsCategories::create(['name' => 'Kategori B']);
        $this->makeQuiz('Kuis A', ['category_id' => $catA->id]);
        $this->makeQuiz('Kuis B', ['category_id' => $catB->id]);

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->set('categoryId', $catA->id)
            ->assertSee('Kuis A')
            ->assertDontSee('Kuis B');
    }

    public function test_view_modal_loads_quiz_detail(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $quiz = $this->makeQuiz('Kuis Detail');
        $question = $this->makeQuestion('<p>Pertanyaan 1</p>');
        $quiz->questions()->attach($question->id, ['sort_order' => 0]);

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->call('openViewModal', $quiz->id)
            ->assertSet('viewingId', $quiz->id)
            ->assertSee('Kuis Detail')
            ->assertSee('Pertanyaan 1');
    }

    public function test_edit_link_hidden_without_update_permission(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $quiz = $this->makeQuiz('Kuis Uji');

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->assertDontSee(route('pelatihan.kuis.edit', $quiz));
    }

    public function test_edit_link_visible_with_update_permission(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $quiz = $this->makeQuiz('Kuis Uji');

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->assertSee(route('pelatihan.kuis.edit', $quiz));
    }

    public function test_delete_without_permission_is_forbidden(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $quiz = $this->makeQuiz('Kuis Hapus');

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->call('removeQuiz', $quiz->id)
            ->assertForbidden();
    }

    public function test_delete_with_permission_removes_quiz(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuizzes']);
        $quiz = $this->makeQuiz('Kuis Hapus');

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->call('removeQuiz', $quiz->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('lms_quizzes', ['id' => $quiz->id]);
    }

    public function test_cannot_delete_quiz_used_in_a_meeting(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuizzes']);
        $quiz = $this->makeQuiz('Kuis Dipakai');
        $course = LmsCourses::create(['code' => 'TRN-Q1', 'title' => 'Pelatihan Q']);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Sesi 1']);
        LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->call('removeQuiz', $quiz->id);

        $this->assertDatabaseHas('lms_quizzes', ['id' => $quiz->id]);
    }

    /**
     * Regresi: QuizzesPage tidak pakai WithPagination trait, tapi pagination-nya tetap pakai
     * wire:navigate (full page-visit) — komponen di-mount ULANG dari URL tiap pindah halaman.
     * Tanpa #[Url], filter `categoryId` hilang. Test GET langsung (bukan Livewire::test) biar
     * simulasi mount dari URL yang sebenarnya kejadian saat wire:navigate.
     */
    public function test_category_filter_is_read_from_url_on_page_load(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $catA = LmsCategories::create(['name' => 'Kategori A']);
        $catB = LmsCategories::create(['name' => 'Kategori B']);

        for ($i = 1; $i <= 15; $i++) {
            $this->makeQuiz("Kuis A {$i}", ['category_id' => $catA->id]);
        }
        $this->makeQuiz('Kuis B 1', ['category_id' => $catB->id]);

        $response = $this->actingAs($admin)->get(route('pelatihan.kuis', ['categoryId' => $catA->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('Kuis A 11');
        $response->assertDontSee('Kuis B 1');
    }

    public function test_delete_button_hidden_when_quiz_used_in_a_meeting(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuizzes']);
        $quiz = $this->makeQuiz('Kuis Dipakai Tombol');
        $course = LmsCourses::create(['code' => 'TRN-Q2', 'title' => 'Pelatihan Q2']);
        $meeting = LmsMeetings::create(['course_id' => $course->id, 'title' => 'Sesi 1']);
        LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);

        Livewire::actingAs($admin)
            ->test(QuizzesPage::class)
            ->assertDontSee('wire:click="removeQuiz(\'' . $quiz->id . '\')"', false);
    }
}
