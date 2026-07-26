<?php

namespace Tests\Feature\LMS;

use App\Enum\QuestionType;
use App\Livewire\LMS\CreateQuiz;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\LMS\LmsQuizzes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test halaman "Buat Kuis" (CreateQuiz) — kategori wajib, pilih soal dari Bank Soal (difilter
 * kategori), urutan soal (sort_order), dan modal "+ Buat Soal Baru" yang langsung menyimpan ke
 * Bank Soal lalu masuk ke daftar terpilih.
 */
class CreateQuizTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(array $extraPermissions = []): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        foreach ($extraPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'LMS Create Quiz Test', 'guard_name' => 'web']);
        $role->givePermissionTo(array_merge(['sistem_lms', 'Create:LmsQuizzes'], $extraPermissions));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeQuestion(string $questionHtml, string $categoryId, array $overrides = []): LmsQuestionBanks
    {
        return LmsQuestionBanks::create(array_merge([
            'category_id' => $categoryId,
            'question' => $questionHtml,
            'type' => QuestionType::MultipleChoice,
        ], $overrides));
    }

    public function test_user_without_sistem_lms_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(CreateQuiz::class)->assertForbidden();
    }

    public function test_user_with_sistem_lms_but_without_create_permission_is_forbidden(): void
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'No Create Quiz', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms']);
        $user = User::factory()->create();
        $user->assignRole($role);

        Livewire::actingAs($user)->test(CreateQuiz::class)->assertForbidden();
    }

    public function test_edit_mode_without_update_permission_is_forbidden(): void
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'No Update Quiz', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $quiz = LmsQuizzes::create(['title' => 'Kuis Uji']);

        Livewire::actingAs($user)->test(CreateQuiz::class, ['quiz' => $quiz->id])->assertForbidden();
    }

    public function test_edit_mode_preloads_category_title_and_ordered_questions(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $category = LmsCategories::create(['name' => 'IT']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $q2 = $this->makeQuestion('<p>Soal 2</p>', $category->id);
        $quiz = LmsQuizzes::create(['title' => 'Kuis Lama', 'category_id' => $category->id]);
        $quiz->questions()->attach($q2->id, ['sort_order' => 0]);
        $quiz->questions()->attach($q1->id, ['sort_order' => 1]);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class, ['quiz' => $quiz->id])
            ->assertSet('editingQuizId', $quiz->id)
            ->assertSet('categoryId', $category->id)
            ->assertSet('title', 'Kuis Lama')
            ->assertSet('selectedQuestionIds', [$q2->id, $q1->id])
            ->assertSee('Ubah Kuis');
    }

    public function test_edit_mode_saves_changes_to_existing_quiz(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $category = LmsCategories::create(['name' => 'IT']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $q2 = $this->makeQuestion('<p>Soal 2</p>', $category->id);
        $quiz = LmsQuizzes::create(['title' => 'Kuis Lama', 'category_id' => $category->id]);
        $quiz->questions()->attach($q1->id, ['sort_order' => 0]);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class, ['quiz' => $quiz->id])
            ->set('title', 'Kuis Baru')
            ->call('addQuestion', $q2->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('pelatihan.kuis'));

        $quiz->refresh();
        $this->assertEquals('Kuis Baru', $quiz->title);
        $this->assertDatabaseHas('lms_quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_bank_id' => $q1->id,
        ]);
        $this->assertDatabaseHas('lms_quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_bank_id' => $q2->id,
        ]);
    }

    public function test_edit_mode_removing_question_syncs_out(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $category = LmsCategories::create(['name' => 'IT']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $q2 = $this->makeQuestion('<p>Soal 2</p>', $category->id);
        $quiz = LmsQuizzes::create(['title' => 'Kuis Lama', 'category_id' => $category->id]);
        $quiz->questions()->attach($q1->id, ['sort_order' => 0]);
        $quiz->questions()->attach($q2->id, ['sort_order' => 1]);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class, ['quiz' => $quiz->id])
            ->call('removeQuestion', $q2->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lms_quiz_questions', ['quiz_id' => $quiz->id, 'question_bank_id' => $q1->id]);
        $this->assertDatabaseMissing('lms_quiz_questions', ['quiz_id' => $quiz->id, 'question_bank_id' => $q2->id]);
    }

    public function test_available_questions_are_filtered_by_selected_category(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $catA = LmsCategories::create(['name' => 'Kategori A']);
        $catB = LmsCategories::create(['name' => 'Kategori B']);
        $this->makeQuestion('<p>Pertanyaan Anatomi</p>', $catA->id);
        $this->makeQuestion('<p>Pertanyaan Farmakologi</p>', $catB->id);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $catA->id)
            ->assertSee('Pertanyaan Anatomi')
            ->assertDontSee('Pertanyaan Farmakologi');
    }

    public function test_switching_category_clears_selected_questions(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $catA = LmsCategories::create(['name' => 'Kategori A']);
        $catB = LmsCategories::create(['name' => 'Kategori B']);
        $question = $this->makeQuestion('<p>Soal A</p>', $catA->id);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $catA->id)
            ->call('addQuestion', $question->id)
            ->assertSet('selectedQuestionIds', [$question->id])
            ->set('categoryId', $catB->id)
            ->assertSet('selectedQuestionIds', []);
    }

    public function test_can_add_and_reorder_and_remove_selected_questions(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $q2 = $this->makeQuestion('<p>Soal 2</p>', $category->id);

        $component = Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $category->id)
            ->call('addQuestion', $q1->id)
            ->call('addQuestion', $q2->id)
            ->assertSet('selectedQuestionIds', [$q1->id, $q2->id]);

        $component->call('moveQuestionUp', 1)
            ->assertSet('selectedQuestionIds', [$q2->id, $q1->id]);

        $component->call('removeQuestion', $q2->id)
            ->assertSet('selectedQuestionIds', [$q1->id]);
    }

    public function test_saves_quiz_with_ordered_questions(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $q2 = $this->makeQuestion('<p>Soal 2</p>', $category->id);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $category->id)
            ->set('title', 'Kuis IT Dasar')
            ->call('addQuestion', $q2->id)
            ->call('addQuestion', $q1->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('pelatihan.kuis'));

        $quiz = LmsQuizzes::where('title', 'Kuis IT Dasar')->firstOrFail();
        $this->assertEquals($category->id, $quiz->category_id);

        $this->assertDatabaseHas('lms_quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_bank_id' => $q2->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('lms_quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_bank_id' => $q1->id,
            'sort_order' => 1,
        ]);
    }

    public function test_save_requires_at_least_one_question(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $category->id)
            ->set('title', 'Kuis Kosong')
            ->call('save')
            ->assertHasErrors(['selectedQuestionIds']);
    }

    public function test_new_question_modal_creates_question_and_adds_to_selection(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);

        $component = Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $category->id)
            ->call('openNewQuestionModal')
            ->set('newQuestion', '<p>Soal baru dari modal</p>')
            ->set('newOptions.0.text', 'A')
            ->set('newOptions.0.is_correct', true)
            ->set('newOptions.1.text', 'B')
            ->call('saveNewQuestion')
            ->assertHasNoErrors()
            ->assertSet('showNewQuestionModal', false);

        $question = LmsQuestionBanks::where('question', '<p>Soal baru dari modal</p>')->firstOrFail();
        $this->assertEquals($category->id, $question->category_id);
        $component->assertSet('selectedQuestionIds', [$question->id]);
    }

    public function test_new_question_modal_validates_at_least_one_correct_option(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $category->id)
            ->call('openNewQuestionModal')
            ->set('newQuestion', '<p>Soal tanpa jawaban benar</p>')
            ->set('newOptions.0.text', 'A')
            ->set('newOptions.1.text', 'B')
            ->call('saveNewQuestion')
            ->assertHasErrors(['newOptions']);
    }
}
