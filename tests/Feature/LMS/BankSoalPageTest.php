<?php

namespace Tests\Feature\LMS;

use App\Enum\QuestionType;
use App\Livewire\LMS\BankSoalPage;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test fitur halaman Bank Soal portal (BankSoalPage) — padanan portal dari Filament
 * QuestionBanksResource. Mencakup: gate akses, list, filter kategori, search, modal view,
 * modal edit, dan hapus.
 */
class BankSoalPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(array $extraPermissions = []): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        foreach ($extraPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'LMS Bank Soal Test', 'guard_name' => 'web']);
        $role->givePermissionTo(array_merge(['sistem_lms'], $extraPermissions));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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

        Livewire::actingAs($user)->test(BankSoalPage::class)->assertForbidden();
    }

    public function test_user_with_sistem_lms_can_access_page(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)->test(BankSoalPage::class)->assertOk();
    }

    public function test_list_shows_questions(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $this->makeQuestion('<p>Apa itu HTML?</p>');
        $this->makeQuestion('<p>Apa itu CSS?</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->assertSee('Apa itu HTML?')
            ->assertSee('Apa itu CSS?');
    }

    public function test_search_filters_by_question_text(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $this->makeQuestion('<p>Pertanyaan tentang HTML dasar</p>');
        $this->makeQuestion('<p>Pertanyaan tentang CSS lanjutan</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->set('search', 'HTML')
            ->assertSee('Pertanyaan tentang HTML dasar')
            ->assertDontSee('Pertanyaan tentang CSS lanjutan');
    }

    public function test_search_is_case_insensitive_and_ignores_html_tags(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $this->makeQuestion('<p><strong>JavaScript</strong> itu apa?</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->set('search', 'javascript') // lowercase, harus match walau di tag <strong>
            ->assertSee('JavaScript');
    }

    public function test_category_filter(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $catIt = LmsCategories::create(['name' => 'IT']);
        $catHr = LmsCategories::create(['name' => 'Kepegawaian']);

        $this->makeQuestion('<p>Soal IT 1</p>', ['category_id' => $catIt->id]);
        $this->makeQuestion('<p>Soal HR 1</p>', ['category_id' => $catHr->id]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->set('categoryId', $catIt->id)
            ->assertSee('Soal IT 1')
            ->assertDontSee('Soal HR 1');
    }

    public function test_view_modal_loads_question_detail(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $question = $this->makeQuestion('<p>Detail pertanyaan view test</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openViewModal', $question->id)
            ->assertSet('viewingId', $question->id)
            ->assertSee('Detail pertanyaan view test');
    }

    public function test_close_view_modal_clears_state(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $question = $this->makeQuestion('<p>Soal untuk close modal</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openViewModal', $question->id)
            ->call('closeViewModal')
            ->assertSet('viewingId', null);
    }

    public function test_edit_without_update_permission_is_forbidden(): void
    {
        $admin = $this->actingAsLmsAdmin(); // cuma sistem_lms, tanpa Update:LmsQuestionBanks
        $question = $this->makeQuestion('<p>Soal yang mau diedit</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openEditModal', $question->id)
            ->assertForbidden();
    }

    public function test_edit_modal_loads_question_into_form_fields(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuestionBanks']);
        $category = LmsCategories::create(['name' => 'IT']);
        $question = LmsQuestionBanks::create([
            'category_id' => $category->id,
            'question' => '<p>Soal edit test</p>',
            'type' => QuestionType::MultipleChoice,
            'min_score' => 0,
            'max_score' => 0,
        ]);
        $question->options()->createMany([
            ['option_text' => 'Opsi A', 'is_correct' => true],
            ['option_text' => 'Opsi B', 'is_correct' => false],
        ]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openEditModal', $question->id)
            ->assertSet('editingId', $question->id)
            ->assertSet('formCategoryId', $category->id)
            ->assertSet('formQuestion', '<p>Soal edit test</p>')
            ->assertSet('formType', QuestionType::MultipleChoice->value)
            ->assertSet('showEditModal', true);
    }

    public function test_save_edit_updates_question_and_options(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuestionBanks']);
        $category = LmsCategories::create(['name' => 'IT']);
        $question = $this->makeQuestion('<p>Soal lama</p>', ['category_id' => $category->id]);
        $question->options()->createMany([
            ['option_text' => 'Lama A', 'is_correct' => true],
            ['option_text' => 'Lama B', 'is_correct' => false],
        ]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openEditModal', $question->id)
            ->set('formQuestion', '<p>Soal baru hasil edit</p>')
            ->set('formOptions.0.text', 'Opsi Baru A')
            ->set('formOptions.0.is_correct', true)
            ->set('formOptions.1.text', 'Opsi Baru B')
            ->set('formOptions.1.is_correct', false)
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $question->refresh();
        $this->assertEquals('<p>Soal baru hasil edit</p>', $question->question);

        $options = $question->options()->orderBy('id')->get();
        $this->assertCount(2, $options);
        $this->assertEquals('Opsi Baru A', $options[0]->option_text);
        $this->assertTrue((bool) $options[0]->is_correct);
        $this->assertEquals('Opsi Baru B', $options[1]->option_text);
    }

    public function test_edit_can_switch_type_to_essay_and_clears_options(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuestionBanks']);
        $category = LmsCategories::create(['name' => 'Umum']);
        $question = $this->makeQuestion('<p>Soal MC ke essay</p>', ['category_id' => $category->id]);
        $question->options()->createMany([
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
        ]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openEditModal', $question->id)
            ->set('formType', QuestionType::Essay->value)
            ->set('formMaxScore', '80')
            ->set('formMinScore', '0')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $question->refresh();
        // `type` tidak di-cast ke enum di model (akses string mentah) — bandingkan dengan
        // nilai string enum langsung, bukan ->value (lihat catatan di LmsQuestionBanks).
        $this->assertEquals(QuestionType::Essay->value, $question->type);
        $this->assertEquals(80, $question->max_score);
        $this->assertEquals(0, $question->min_score);
        $this->assertEquals(0, $question->options()->count(), 'Essay tidak boleh punya options');
    }

    public function test_delete_without_permission_is_forbidden(): void
    {
        $admin = $this->actingAsLmsAdmin(); // tanpa Delete:LmsQuestionBanks
        $question = $this->makeQuestion('<p>Soal yang mau dihapus</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('removeSoal', $question->id)
            ->assertForbidden();

        $this->assertDatabaseHas('lms_question_banks', ['id' => $question->id]);
    }

    public function test_delete_with_permission_removes_question(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuestionBanks']);
        $question = $this->makeQuestion('<p>Soal yang akan dihapus</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('removeSoal', $question->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('lms_question_banks', ['id' => $question->id]);
    }

    public function test_delete_cascades_to_options(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuestionBanks']);
        $question = $this->makeQuestion('<p>Soal dengan opsi</p>');
        $question->options()->createMany([
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
        ]);

        $optionIds = $question->options()->pluck('id')->all();

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('removeSoal', $question->id);

        foreach ($optionIds as $optId) {
            $this->assertDatabaseMissing('lms_question_options', ['id' => $optId]);
        }
    }

    public function test_cannot_delete_question_used_in_a_quiz(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuestionBanks']);
        $question = $this->makeQuestion('<p>Soal dipakai di kuis</p>');
        $quiz = \App\Models\LMS\LmsQuizzes::create(['title' => 'Kuis A']);
        $quiz->questions()->syncWithoutDetaching([$question->id]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('removeSoal', $question->id);

        $this->assertDatabaseHas('lms_question_banks', ['id' => $question->id]);
    }

    public function test_cannot_delete_question_with_answer_history_even_without_quiz_link(): void
    {
        $admin = $this->actingAsLmsAdmin(['Delete:LmsQuestionBanks']);
        $question = $this->makeQuestion('<p>Soal punya riwayat jawaban</p>');
        $quiz = \App\Models\LMS\LmsQuizzes::create(['title' => 'Kuis B']);
        $course = \App\Models\LMS\LmsCourses::create(['code' => 'TRN-B', 'title' => 'Pelatihan B']);
        $meeting = \App\Models\LMS\LmsMeetings::create(['course_id' => $course->id, 'title' => 'Sesi B']);
        $meetingQuiz = \App\Models\LMS\LmsMeetingQuizzes::create(['meeting_id' => $meeting->id, 'quiz_id' => $quiz->id]);
        $user = User::factory()->create();
        $respondent = \App\Models\LMS\LmsRespondent::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'category' => \App\Models\LMS\LmsRespondent::CATEGORY_KUIS,
        ]);

        \App\Models\LMS\LmsQuizAnswers::create([
            'meeting_quiz_id' => $meetingQuiz->id,
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'respondent_id' => $respondent->id,
            'user_answer' => 'A',
            'is_correct' => true,
            'earned_points' => 100,
        ]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('removeSoal', $question->id);

        $this->assertDatabaseHas('lms_question_banks', ['id' => $question->id]);
    }

    public function test_can_create_property_reflects_permission(): void
    {
        $adminWithout = $this->actingAsLmsAdmin();
        Livewire::actingAs($adminWithout)
            ->test(BankSoalPage::class)
            ->assertSet('canCreate', false);

        $adminWith = $this->actingAsLmsAdmin(['Create:LmsQuestionBanks']);
        Livewire::actingAs($adminWith)
            ->test(BankSoalPage::class)
            ->assertSet('canCreate', true);
    }

    public function test_multiple_choice_options_min_2_in_edit_modal(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuestionBanks']);
        // Soal tanpa opsi sama sekali — edit modal harus auto-tambah 2 opsi kosong
        $question = $this->makeQuestion('<p>Soal MC tanpa opsi</p>');

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openEditModal', $question->id);

        $this->assertDatabaseHas('lms_question_banks', ['id' => $question->id]); // tidak crash
    }

    /**
     * Regresi: BankSoalPage tidak pakai WithPagination trait, tapi pagination-nya tetap pakai
     * wire:navigate (full page-visit) — komponen di-mount ULANG dari URL tiap pindah halaman.
     * Tanpa #[Url], filter `categoryId` hilang. Test GET langsung (bukan Livewire::test) biar
     * simulasi mount dari URL yang sebenarnya kejadian saat wire:navigate.
     */
    public function test_category_filter_is_read_from_url_on_page_load(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $catIt = LmsCategories::create(['name' => 'IT']);
        $catHr = LmsCategories::create(['name' => 'Kepegawaian']);

        for ($i = 1; $i <= 15; $i++) {
            $this->makeQuestion("<p>Soal IT {$i}</p>", ['category_id' => $catIt->id]);
        }
        $this->makeQuestion('<p>Soal HR 1</p>', ['category_id' => $catHr->id]);

        $response = $this->actingAs($admin)->get(route('pelatihan.bank-soal', ['categoryId' => $catIt->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('Soal IT 11');
        $response->assertDontSee('Soal HR 1');
    }

    public function test_edit_modal_set_correct_option_unchecks_others(): void
    {
        $admin = $this->actingAsLmsAdmin(['Update:LmsQuestionBanks']);
        $question = $this->makeQuestion('<p>Soal radio behavior</p>');
        $question->options()->createMany([
            ['option_text' => 'A', 'is_correct' => true],
            ['option_text' => 'B', 'is_correct' => false],
            ['option_text' => 'C', 'is_correct' => false],
        ]);

        Livewire::actingAs($admin)
            ->test(BankSoalPage::class)
            ->call('openEditModal', $question->id)
            // Opsi 0 awalnya correct (dari DB)
            ->assertSet('formOptions.0.is_correct', true)
            // Centang opsi 1 → opsi 0 uncheck
            ->call('setCorrectOption', 1)
            ->assertSet('formOptions.0.is_correct', false)
            ->assertSet('formOptions.1.is_correct', true)
            ->assertSet('formOptions.2.is_correct', false);
    }
}
