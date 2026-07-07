<?php

namespace App\Services\Assessment;

use App\Models\Assessment\AssessmentScores;
use App\Models\Assessment\AssessmentUsers;
use App\Repositories\Assessment\AssessmentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentService
{
    public function __construct(
        protected AssessmentRepository $assessmentRepository
    ) {}

    public function calculateUserAssessmentScore(string $assessmentUserId): array
    {
        try {
            return DB::transaction(function () use ($assessmentUserId) {
                $assessmentUser = AssessmentUsers::with([
                    'assessments.template.templateItems'
                ])->find($assessmentUserId);

                if (!$assessmentUser) {
                    throw new \Exception('Assessment user not found');
                }

                $templateItems = $assessmentUser->assessments->template->templateItems;
                $totalWeightedScore = 0;
                $totalWeight = 0;

                foreach ($templateItems as $templateItem) {
                    $assessmentScore = AssessmentScores::where('assessment_user_id', $assessmentUserId)
                        ->where('template_item_id', $templateItem->id)
                        ->first();

                    if ($assessmentScore && $assessmentScore->score !== null) {
                        $normalizedScore = ($assessmentScore->score / $templateItem->max_score) * 100;
                        $weightedScore = ($normalizedScore * $templateItem->weight_percent) / 100;

                        $assessmentScore->update([
                            'weighted_score' => $weightedScore
                        ]);

                        $totalWeightedScore += $weightedScore;
                        $totalWeight += $templateItem->weight_percent;
                    }
                }

                $finalScore = $totalWeight > 0 ? $totalWeightedScore : 0;

                $finalCategory = $this->determineScoreCategory($finalScore);

                $assessmentUser->update([
                    'final_score' => round($finalScore, 2),
                    'final_category' => $finalCategory,
                ]);

                return [
                    'success' => true,
                    'final_score' => round($finalScore, 2),
                    'final_category' => $finalCategory,
                    'total_weight' => $totalWeight,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error calculating assessment score: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function determineScoreCategory(float $score): string
    {
        if ($score >= 90) {
            return 'Sangat Baik';
        } elseif ($score >= 80) {
            return 'Baik';
        } elseif ($score >= 70) {
            return 'Cukup';
        } elseif ($score >= 60) {
            return 'Kurang';
        } else {
            return 'Sangat Kurang';
        }
    }
}
