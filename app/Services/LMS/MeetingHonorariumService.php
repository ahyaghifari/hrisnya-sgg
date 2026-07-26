<?php

namespace App\Services\LMS;

use App\Models\LMS\LmsMeetings;
use App\Models\LMS\Trainer\LmsHonorariumVariable;
use App\Models\LMS\Trainer\LmsMeetingHonorariumAssessment;
use App\Models\LMS\Trainer\TrainerAssessmentResults;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menghitung & menyimpan honorarium per pertemuan (tab "Penilaian dan Gaji Pemateri" di
 * LmsCourseDetailPage). Dua jalur, tergantung LmsCategories::training_category dari course:
 *  - Scored (Dokter Spesialis / Eksternal): pilih 1 kategori per variabel lms_honorarium_variables,
 *    skor dijumlah, dicocokkan ke range lms_honorarium -> honor_amount (lihat
 *    LmsMeetingHonorariumAssessment::recalculate()).
 *  - Fixed (Umum / Sertifikasi Dokter Umum / tanpa kategori): admin input nominal langsung.
 * Kedua jalur berujung ke TrainerAssessmentResults (course_id+trainer_id) — tabel yang sama
 * dipakai halaman admin "Gaji Trainer" (Filament TrainerSalaryResource).
 */
class MeetingHonorariumService
{
    public function variablesForContext(string $context): Collection
    {
        return LmsHonorariumVariable::where('context', $context)
            ->with(['categories' => fn ($q) => $q->orderBy('score')])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, string>  $categoryIdsByVariable  variable_id => category_id
     */
    public function saveScoredAssessment(LmsMeetings $meeting, array $categoryIdsByVariable): LmsMeetingHonorariumAssessment
    {
        $assessment = LmsMeetingHonorariumAssessment::firstOrNew(['meeting_id' => $meeting->id]);
        $assessment->trainer_id = $meeting->trainer_id;
        $assessment->save();

        $keptVariableIds = array_keys($categoryIdsByVariable);
        $assessment->details()->whereNotIn('variable_id', $keptVariableIds)->delete();

        foreach ($categoryIdsByVariable as $variableId => $categoryId) {
            $variable = LmsHonorariumVariable::with('categories')->find($variableId);
            $category = $variable?->categories->firstWhere('id', $categoryId);

            if (! $category) {
                continue;
            }

            $assessment->details()->updateOrCreate(
                ['variable_id' => $variableId],
                ['category_id' => $categoryId, 'score' => $category->score]
            );
        }

        return $assessment->fresh(['details']);
    }

    public function saveFixedAmount(LmsMeetings $meeting, float $honorAmount): TrainerAssessmentResults
    {
        $course = $meeting->course;

        return TrainerAssessmentResults::updateOrCreate(
            ['course_id' => $course->id, 'trainer_id' => $meeting->trainer_id],
            ['company_id' => $course->company_id, 'honor_amount' => $honorAmount]
        );
    }
}
