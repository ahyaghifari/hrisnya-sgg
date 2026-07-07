<?php

namespace App\Repositories\Assessment;

use App\Models\Assessment\AssessmentScores;
use App\Models\Assessment\AssessmentUsers;

class AssessmentRepository
{
    public function updateUserAssessment(AssessmentUsers $assessmentUser, array $data): AssessmentUsers
    {
        $assessmentUser->update($data);
        return $assessmentUser->refresh();
    }

    public function findUserAssessmentById(string $id): ?AssessmentUsers
    {
        return AssessmentUsers::find($id);
    }

    public function updateUserAssessmentScores(AssessmentUsers $assessmentUser, array $data): AssessmentUsers
    {
        AssessmentScores::updateOrCreate(
            [
                'assessment_user_id' => $assessmentUser->id,
                'template_item_id' => $data['template_item_id'],
            ],
            $data
        );
        return $assessmentUser->refresh();
    }
}
