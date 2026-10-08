<?php

namespace Tests\Feature\LMS;

use App\Enum\QuestionType;
use App\Livewire\LMS\CreateQuiz;
use App\Livewire\LMS\ManageQuizQuestions;
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
 * Test "Buat/Ubah Kuis" (CreateQuiz: judul + kategori) dan "Kelola Soal" (ManageQuizQuestions:
 * pilih soal dari Bank Soal difilter kategori, urutan sort_order, hapus dari kuis).
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

    private function makeQuestion(string $questionHtml, string $categoryId): LmsQuestionBanks
    {
        return LmsQuestionBanks::create([
            'category_id' => $categoryId,
            'question' => $questionHtml,
            'type' => QuestionType::MultipleChoice,
        ]);
    }

    public function test_user_without_sistem_lms_permission_is_forbidden(): void
    {
        Livewire::actingAs(User::factory()->create())->test(CreateQuiz::class)->assertForbidden();
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

    public function test_edit_and_manage_questions_without_update_permission_are_forbidden(): void
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'No Update Quiz', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $quiz = LmsQuizzes::create(['title' => 'Kuis Uji']);

        Livewire::actingAs($user)->test(CreateQuiz::class, ['quiz' => $quiz->id])->assertForbidden();
        Livewire::actingAs($user)->test(ManageQuizQuestions::class, ['quiz' => $quiz->id])->assertForbidden();
    }

    public function test_create_saves_quiz_and_redirects_to_manage_questions(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class)
            ->set('categoryId', $category->id)
            ->set('title', 'Kuis IT Dasar')
            ->call('save')
            ->assertHasNoErrors();

        $quiz = LmsQuizzes::where('title', 'Kuis IT Dasar')->firstOrFail();
        $this->assertEquals($category->id, $quiz->category_id);
    }

    public function test_edit_changes_title_and_keeps_questions_and_category(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $category = LmsCategories::create(['name' => 'IT']);
        $other = LmsCategories::create(['name' => 'Lain']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $quiz = LmsQuizzes::create(['title' => 'Kuis Lama', 'category_id' => $category->id]);
        $quiz->questions()->attach($q1->id, ['sort_order' => 0]);

        Livewire::actingAs($admin)
            ->test(CreateQuiz::class, ['quiz' => $quiz->id])
            ->assertSee('Ubah Kuis')
            ->set('title', 'Kuis Baru')
            ->set('categoryId', $other->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('pelatihan.kuis'));

        $quiz->refresh();
        $this->assertEquals('Kuis Baru', $quiz->title);
        $this->assertEquals($category->id, $quiz->category_id);
        $this->assertDatabaseHas('lms_quiz_questions', ['quiz_id' => $quiz->id, 'question_bank_id' => $q1->id]);
    }

    public function test_manage_questions_filters_available_by_quiz_category(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $catA = LmsCategories::create(['name' => 'Kategori A']);
        $catB = LmsCategories::create(['name' => 'Kategori B']);
        $this->makeQuestion('<p>Pertanyaan Anatomi</p>', $catA->id);
        $this->makeQuestion('<p>Pertanyaan Farmakologi</p>', $catB->id);
        $quiz = LmsQuizzes::create(['title' => 'Kuis A', 'category_id' => $catA->id]);

        Livewire::actingAs($admin)
            ->test(ManageQuizQuestions::class, ['quiz' => $quiz->id])
            ->assertSee('Pertanyaan Anatomi')
            ->assertDontSee('Pertanyaan Farmakologi');
    }

    public function test_manage_questions_add_reorder_remove_and_save(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $category = LmsCategories::create(['name' => 'IT']);
        $q1 = $this->makeQuestion('<p>Soal 1</p>', $category->id);
        $q2 = $this->makeQuestion('<p>Soal 2</p>', $category->id);
        $q3 = $this->makeQuestion('<p>Soal 3</p>', $category->id);
        $quiz = LmsQuizzes::create(['title' => 'Kuis IT', 'category_id' => $category->id]);
        $quiz->questions()->attach($q3->id, ['sort_order' => 0]);

        Livewire::actingAs($admin)
            ->test(ManageQuizQuestions::class, ['quiz' => $quiz->id])
            ->assertSet('selectedQuestionIds', [$q3->id])
            ->call('addQuestion', $q1->id)
            ->call('addQuestion', $q2->id)
            ->call('moveQuestionUp', 2)
            ->assertSet('selectedQuestionIds', [$q3->id, $q2->id, $q1->id])
            ->call('removeQuestion', $q3->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('pelatihan.kuis'));

        $this->assertDatabaseMissing('lms_quiz_questions', ['quiz_id' => $quiz->id, 'question_bank_id' => $q3->id]);
        $this->assertDatabaseHas('lms_quiz_questions', ['quiz_id' => $quiz->id, 'question_bank_id' => $q2->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('lms_quiz_questions', ['quiz_id' => $quiz->id, 'question_bank_id' => $q1->id, 'sort_order' => 1]);
    }

    public function test_manage_questions_save_requires_at_least_one_question(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuizzes']);
        $category = LmsCategories::create(['name' => 'IT']);
        $quiz = LmsQuizzes::create(['title' => 'Kuis Kosong', 'category_id' => $category->id]);

        Livewire::actingAs($admin)
            ->test(ManageQuizQuestions::class, ['quiz' => $quiz->id])
            ->call('save')
            ->assertHasErrors(['selectedQuestionIds']);
    }
}
