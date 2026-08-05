<?php

namespace App\Services\LMS;

use App\Enum\LMS\TrainingCategoryEnum;
use App\Models\LMS\Trainer\LmsHonorariumVariable;
use App\Models\LMS\TrainingRequests;

/**
 * Honorarium pengajuan pelatihan eksternal karyawan (halaman admin "Pelatihan Eksternal") —
 * jalur scored sama seperti honorarium pemateri (lihat MeetingHonorariumService), context
 * selalu "eksternal" karena training_requests memang cuma untuk pelatihan di luar perusahaan.
 */
class TrainingRequestHonorariumService
{
    public function variables()
    {
        return LmsHonorariumVariable::where('context', TrainingCategoryEnum::EKSTERNAL->value)
            ->with(['categories' => fn ($q) => $q->orderBy('score')])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, string>  $categoryIdsByVariable  variable_id => category_id
     */
    public function saveScoredAssessment(TrainingRequests $trainingRequest, array $categoryIdsByVariable): TrainingRequests
    {
        $keptVariableIds = array_keys($categoryIdsByVariable);
        $trainingRequest->honorDetails()->whereNotIn('variable_id', $keptVariableIds)->delete();

        foreach ($categoryIdsByVariable as $variableId => $categoryId) {
            $variable = LmsHonorariumVariable::with('categories')->find($variableId);
            $category = $variable?->categories->firstWhere('id', $categoryId);

            if (! $category) {
                continue;
            }

            $trainingRequest->honorDetails()->updateOrCreate(
                ['variable_id' => $variableId],
                ['category_id' => $categoryId, 'score' => $category->score]
            );
        }

        return $trainingRequest->fresh(['honorDetails']);
    }
}
