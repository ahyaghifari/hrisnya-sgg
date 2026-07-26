<?php

namespace Tests\Feature\LMS;

use App\Enum\QuestionType;
use App\Livewire\LMS\CreateBankSoal;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsQuestionBanks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test halaman Create Bank Soal (multi-soal repeater). Mencakup: gate akses, inisialisasi
 * form, tambah/hapus row, tambah/hapus opsi, validasi (essay wajib max_score, MC wajib ≥1
 * opsi benar), simpan banyak soal sekaligus, redirect setelah simpan.
 */
class CreateBankSoalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsLmsAdmin(): User
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:LmsQuestionBanks', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'LMS Create Bank Soal Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms', 'Create:LmsQuestionBanks']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_without_sistem_lms_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(CreateBankSoal::class)->assertForbidden();
    }

    public function test_user_with_sistem_lms_but_without_create_permission_is_forbidden(): void
    {
        Permission::firstOrCreate(['name' => 'sistem_lms', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LMS No Create Test', 'guard_name' => 'web']);
        $role->givePermissionTo(['sistem_lms']);

        $user = User::factory()->create();
        $user->assignRole($role);

        Livewire::actingAs($user)->test(CreateBankSoal::class)->assertForbidden();
    }

    public function test_form_initializes_with_one_empty_row(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->assertSet('soalRows', function ($rows) {
                return count($rows) === 1
                    && $rows[0]['type'] === QuestionType::MultipleChoice->value
                    && count($rows[0]['options']) === 2;
            });
    }

    public function test_add_soal_row_increments_count(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->call('addSoalRow')
            ->assertSet('soalRows', fn ($rows) => count($rows) === 2);
    }

    public function test_remove_soal_row_decrements_count(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->call('addSoalRow')
            ->call('removeSoalRow', 0)
            ->assertSet('soalRows', fn ($rows) => count($rows) === 1);
    }

    public function test_cannot_remove_last_soal_row(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->call('removeSoalRow', 0) // cuma 1 row
            ->assertSet('soalRows', fn ($rows) => count($rows) === 1);
    }

    public function test_add_option_to_row(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->call('addOption', 0) // row 0 default 2 opsi → jadi 3
            ->assertSet('soalRows.0.options', fn ($opts) => count($opts) === 3);
    }

    public function test_cannot_add_more_than_10_options(): void
    {
        $admin = $this->actingAsLmsAdmin();

        $component = Livewire::actingAs($admin)->test(CreateBankSoal::class);

        // Tambah opsi sampai 10 (default 2, perlu 8 kali tambah)
        for ($i = 0; $i < 8; $i++) {
            $component->call('addOption', 0);
        }
        $component->assertSet('soalRows.0.options', fn ($opts) => count($opts) === 10);

        // Coba tambah ke-11 — harus tetap 10
        $component->call('addOption', 0)
            ->assertSet('soalRows.0.options', fn ($opts) => count($opts) === 10);
    }

    public function test_cannot_remove_option_below_2(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->call('removeOption', 0, 0) // default 2 opsi, hapus 1 → tetap 2 (guard)
            ->assertSet('soalRows.0.options', fn ($opts) => count($opts) === 2);
    }

    public function test_set_correct_option_unchecks_others_in_same_row(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            // Tambah opsi ke-3 supaya ada 3 opsi untuk test radio behavior
            ->call('addOption', 0)
            // Centang opsi 0
            ->call('setCorrectOption', 0, 0)
            ->assertSet('soalRows.0.options.0.is_correct', true)
            ->assertSet('soalRows.0.options.1.is_correct', false)
            ->assertSet('soalRows.0.options.2.is_correct', false)
            // Centang opsi 1 → opsi 0 harus uncheck otomatis
            ->call('setCorrectOption', 0, 1)
            ->assertSet('soalRows.0.options.0.is_correct', false)
            ->assertSet('soalRows.0.options.1.is_correct', true)
            ->assertSet('soalRows.0.options.2.is_correct', false);
    }

    public function test_set_correct_option_stays_checked_when_clicked_again(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->call('setCorrectOption', 0, 0) // centang
            ->assertSet('soalRows.0.options.0.is_correct', true)
            ->call('setCorrectOption', 0, 0) // klik lagi → radio, tetap tercentang
            ->assertSet('soalRows.0.options.0.is_correct', true)
            ->assertSet('soalRows.0.options.1.is_correct', false);
    }

    public function test_switch_type_to_essay_clears_options(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('soalRows.0.type', QuestionType::Essay->value)
            ->assertSet('soalRows.0.options', [])
            ->assertSet('soalRows.0.min_score', '')
            ->assertSet('soalRows.0.max_score', '');
    }

    public function test_switch_type_back_to_multiple_choice_ensures_min_2_options(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('soalRows.0.type', QuestionType::Essay->value) // options jadi []
            ->set('soalRows.0.type', QuestionType::MultipleChoice->value) // balik ke MC
            ->assertSet('soalRows.0.options', fn ($opts) => count($opts) === 2);
    }

    public function test_save_creates_single_multiple_choice_question_with_options(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'IT']);

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', $category->id)
            ->set('soalRows.0.question', '<p>Berapa hasil 2+2?</p>')
            ->set('soalRows.0.type', QuestionType::MultipleChoice->value)
            ->set('soalRows.0.options.0.text', '4')
            ->set('soalRows.0.options.0.is_correct', true)
            ->set('soalRows.0.options.1.text', '5')
            ->set('soalRows.0.options.1.is_correct', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('pelatihan.bank-soal'));

        $this->assertDatabaseHas('lms_question_banks', [
            'category_id' => $category->id,
            'question' => '<p>Berapa hasil 2+2?</p>',
            'type' => QuestionType::MultipleChoice->value,
        ]);

        $question = LmsQuestionBanks::first();
        $this->assertEquals(2, $question->options()->count(), 'Harus ada 2 opsi');
        $this->assertTrue((bool) $question->options[0]->is_correct);
        $this->assertFalse((bool) $question->options[1]->is_correct);
    }

    public function test_save_creates_essay_question_with_score(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'Umum']);

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', $category->id)
            ->set('soalRows.0.question', '<p>Jelaskan apa itu OOP</p>')
            ->set('soalRows.0.type', QuestionType::Essay->value)
            ->set('soalRows.0.max_score', '80')
            ->set('soalRows.0.min_score', '0')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('pelatihan.bank-soal'));

        $this->assertDatabaseHas('lms_question_banks', [
            'question' => '<p>Jelaskan apa itu OOP</p>',
            'type' => QuestionType::Essay->value,
            'min_score' => 0,
            'max_score' => 80,
        ]);

        $question = LmsQuestionBanks::first();
        $this->assertEquals(0, $question->options()->count(), 'Essay tidak boleh punya opsi');
    }

    public function test_save_creates_multiple_questions_at_once(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'Umum']);

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', $category->id)
            ->set('soalRows.0.question', '<p>Soal pertama</p>')
            ->set('soalRows.0.options.0.text', 'A')
            ->set('soalRows.0.options.0.is_correct', true)
            ->set('soalRows.0.options.1.text', 'B')
            ->call('addSoalRow')
            ->set('soalRows.1.question', '<p>Soal kedua</p>')
            ->set('soalRows.1.options.0.text', 'C')
            ->set('soalRows.1.options.0.is_correct', true)
            ->set('soalRows.1.options.1.text', 'D')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(2, LmsQuestionBanks::count(), 'Harus ada 2 soal tersimpan');
        $this->assertDatabaseHas('lms_question_banks', ['question' => '<p>Soal pertama</p>']);
        $this->assertDatabaseHas('lms_question_banks', ['question' => '<p>Soal kedua</p>']);
    }

    public function test_validation_fails_when_question_empty(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('soalRows.0.question', '') // kosong
            ->set('soalRows.0.options.0.text', 'A')
            ->set('soalRows.0.options.0.is_correct', true)
            ->set('soalRows.0.options.1.text', 'B')
            ->call('save')
            ->assertHasErrors(['soalRows.0.question']);
    }

    public function test_validation_fails_when_option_text_empty(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('soalRows.0.question', '<p>Soal opsi kosong</p>')
            ->set('soalRows.0.options.0.text', '') // kosong
            ->set('soalRows.0.options.0.is_correct', true)
            ->set('soalRows.0.options.1.text', 'B')
            ->call('save')
            ->assertHasErrors(['soalRows.0.options.0.text']);
    }

    public function test_validation_fails_when_no_correct_option_in_multiple_choice(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'Umum']);

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', $category->id)
            ->set('soalRows.0.question', '<p>Soal tanpa jawaban benar</p>')
            ->set('soalRows.0.options.0.text', 'A')
            ->set('soalRows.0.options.0.is_correct', false) // tidak ada yang benar
            ->set('soalRows.0.options.1.text', 'B')
            ->set('soalRows.0.options.1.is_correct', false)
            ->call('save')
            ->assertHasErrors(['soalRows.0.options']);
    }

    public function test_validation_fails_when_essay_has_no_max_score(): void
    {
        $admin = $this->actingAsLmsAdmin();
        $category = LmsCategories::create(['name' => 'Umum']);

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', $category->id)
            ->set('soalRows.0.question', '<p>Soal essay tanpa skor</p>')
            ->set('soalRows.0.type', QuestionType::Essay->value)
            ->set('soalRows.0.max_score', '') // kosong
            ->call('save')
            ->assertHasErrors(['soalRows.0.max_score']);
    }

    public function test_created_by_auto_set_to_authenticated_user(): void
    {
        $admin = $this->actingAsLmsAdmin();

        $category = LmsCategories::create(['name' => 'Umum']);

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', $category->id)
            ->set('soalRows.0.question', '<p>Soal cek created_by</p>')
            ->set('soalRows.0.options.0.text', 'A')
            ->set('soalRows.0.options.0.is_correct', true)
            ->set('soalRows.0.options.1.text', 'B')
            ->call('save');

        $question = LmsQuestionBanks::first();
        $this->assertEquals($admin->id, $question->created_by);
    }

    public function test_category_is_required(): void
    {
        $admin = $this->actingAsLmsAdmin();

        Livewire::actingAs($admin)
            ->test(CreateBankSoal::class)
            ->set('categoryId', null)
            ->set('soalRows.0.question', '<p>Soal tanpa kategori</p>')
            ->set('soalRows.0.options.0.text', 'A')
            ->set('soalRows.0.options.0.is_correct', true)
            ->set('soalRows.0.options.1.text', 'B')
            ->call('save')
            ->assertHasErrors(['categoryId' => 'required']);

        $this->assertDatabaseMissing('lms_question_banks', [
            'question' => '<p>Soal tanpa kategori</p>',
        ]);
    }
}
