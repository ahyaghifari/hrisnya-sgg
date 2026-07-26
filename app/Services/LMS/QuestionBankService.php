<?php

namespace App\Services\LMS;

use App\Enum\QuestionType;
use App\Models\LMS\LmsQuestionBanks;
use Illuminate\Support\Facades\DB;

/**
 * Service tipis untuk Bank Soal LMS — padanan portal dari CRUD yang sebelumnya hanya ditangani
 * langsung oleh Filament QuestionBanksResource (CreateRecord/EditRecord bawaan, tanpa service).
 *
 * Dibuat agar halaman portal Livewire (BankSoalPage / CreateBankSoal) bisa reuse logika yang sama
 * tanpa duplikasi, terutama createMany() untuk simpan banyak soal sekaligus (form multi-soal).
 *
 * Boot events LmsQuestionBanks tetap jalan: `creating` auto-set created_by, `saving` auto-handle
 * switch type (essay → hapus options + reset skor, multiple_choice → reset skor 0).
 */
class QuestionBankService
{
    /**
     * Simpan banyak soal sekaligus dalam satu transaction. Tiap item:
     *  - 'category_id' (?string)
     *  - 'question'    (string, HTML dari Trix)
     *  - 'type'        (string nilai enum QuestionType)
     *  - 'min_score'   (?int, hanya essay)
     *  - 'max_score'   (?int, hanya essay)
     *  - 'options'     (array of ['text'=>string,'is_correct'=>bool], hanya multiple_choice)
     *
     * @param  array  $questions  array of soal, lihat struktur di atas
     * @return LmsQuestionBanks[] list record yang baru dibuat
     */
    public function createMany(array $questions): array
    {
        $created = [];

        DB::transaction(function () use ($questions, &$created) {
            foreach ($questions as $item) {
                $created[] = $this->createOne($item);
            }
        });

        return $created;
    }

    /**
     * Simpan satu soal. Tidak wrap transaction sendiri (createMany yang wrap).
     */
    public function createOne(array $item): LmsQuestionBanks
    {
        $type = QuestionType::from($item['type']);

        $question = LmsQuestionBanks::create([
            'category_id' => $item['category_id'] ?? null,
            'question' => $item['question'] ?? '',
            'type' => $type,
            // skor hanya untuk essay; multiple_choice dipaksa 0 oleh boot event model
            'min_score' => $type === QuestionType::Essay ? (int) ($item['min_score'] ?? 0) : 0,
            'max_score' => $type === QuestionType::Essay ? (int) ($item['max_score'] ?? 0) : 0,
        ]);

        if ($type === QuestionType::MultipleChoice && ! empty($item['options'])) {
            $this->syncOptions($question, $item['options']);
        }

        return $question;
    }

    /**
     * Update satu soal + sync options (delete + recreate, sesuai pola repeater Filament).
     */
    public function update(string $id, array $item): LmsQuestionBanks
    {
        $question = LmsQuestionBanks::findOrFail($id);
        $type = QuestionType::from($item['type']);

        return DB::transaction(function () use ($question, $item, $type) {
            $question->update([
                'category_id' => $item['category_id'] ?? null,
                'question' => $item['question'] ?? '',
                'type' => $type,
                'min_score' => $type === QuestionType::Essay ? (int) ($item['min_score'] ?? 0) : 0,
                'max_score' => $type === QuestionType::Essay ? (int) ($item['max_score'] ?? 0) : 0,
            ]);

            // refresh relasi supaya refleksi perubahan type dari boot event (essay hapus options)
            $question->refresh();

            if ($type === QuestionType::MultipleChoice) {
                $question->options()->delete();
                if (! empty($item['options'])) {
                    $this->syncOptions($question, $item['options']);
                }
            }

            return $question->refresh();
        });
    }

    /**
     * Hapus soal — options ikut terhapus via FK cascadeOnDelete di migration.
     *
     * Guard: soal yang sudah pernah dipakai di kuis manapun tidak boleh dihapus. FK
     * lms_quiz_questions & lms_quiz_user_answers ke lms_question_banks pakai cascadeOnDelete
     * (bukan restrict), jadi kalau tidak dijaga di sini, hapus soal akan diam-diam ikut
     * menghapus riwayat jawaban peserta kuis. Quiz tidak pernah detach soal (syncWithoutDetaching
     * di QuizForm), jadi quizzes()->exists() sudah cukup, tapi answers()->exists() dicek juga
     * untuk jaga-jaga kalau ada jalur lain yang detach di masa depan.
     */
    public function delete(string $id): void
    {
        $question = LmsQuestionBanks::findOrFail($id);

        if ($question->quizzes()->exists() || $question->answers()->exists()) {
            throw new \RuntimeException('Soal ini sudah digunakan di kuis dan tidak bisa dihapus.');
        }

        $question->delete();
    }

    /**
     * Tulis ulang semua options soal. Tiap option: ['text'=>string, 'is_correct'=>bool].
     * Catatan: field `order` ADA di LmsQuestionOptions::$fillable TAPI tidak ada kolom migration-nya
     * (bug已知, di luar scope ini) — di-skip di sini supaya tidak error mass-assignment.
     */
    protected function syncOptions(LmsQuestionBanks $question, array $options): void
    {
        $rows = [];
        foreach ($options as $opt) {
            $text = trim((string) ($opt['text'] ?? ''));
            if ($text === '') {
                continue; // skip opsi kosong
            }
            $rows[] = [
                'option_text' => $text,
                'is_correct' => ! empty($opt['is_correct']),
            ];
        }

        if (! empty($rows)) {
            $question->options()->createMany($rows);
        }
    }
}
