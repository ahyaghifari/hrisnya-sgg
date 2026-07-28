<?php

namespace App\Models\LMS\Trainer;

use App\Models\LMS\LmsMeetings;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Penilaian honorarium 1 pertemuan (engine baru, lms_honorarium_variables/categories) — sesudah
 * detail berubah, total_score & honor_amount dihitung ulang (recalculate()) lalu hasil akhirnya
 * di-upsert ke TrainerAssessmentResults (course_id+trainer_id, tabel yang sama dipakai halaman
 * admin "Gaji Trainer") supaya tidak perlu tabel tampilan honor terpisah.
 *
 * @property string $id
 * @property string $meeting_id
 * @property string|null $trainer_id
 * @property int $total_score
 * @property float $honor_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\Trainer\LmsMeetingHonorariumDetail> $details
 * @property-read int|null $details_count
 * @property-read LmsMeetings $meeting
 * @property-read \App\Models\LMS\Trainer\Trainers|null $trainer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereHonorAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereTotalScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumAssessment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsMeetingHonorariumAssessment extends Model
{
    use HasUlids;

    protected $table = 'lms_meeting_honorarium_assessments';

    protected $fillable = [
        'meeting_id',
        'trainer_id',
        'total_score',
        'honor_amount',
    ];

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id', 'id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainers::class, 'trainer_id', 'id');
    }

    public function details()
    {
        return $this->hasMany(LmsMeetingHonorariumDetail::class, 'assessment_id', 'id');
    }

    public function recalculate(): void
    {
        $totalScore = (int) $this->details()->sum('score');
        $context = $this->meeting?->course?->category?->training_category;

        $honorAmount = 0.0;
        if ($context) {
            $honorAmount = (float) (LmsHonorarium::where('context', $context->value)
                ->where('min_score', '<=', $totalScore)
                ->where('max_score', '>=', $totalScore)
                ->value('honor_amount') ?? 0);
        }

        $this->updateQuietly(['total_score' => $totalScore, 'honor_amount' => $honorAmount]);

        $this->syncToTrainerAssessmentResult();
    }

    private function syncToTrainerAssessmentResult(): void
    {
        $course = $this->meeting?->course;

        if (! $course || ! $this->trainer_id) {
            return;
        }

        TrainerAssessmentResults::updateOrCreate(
            ['course_id' => $course->id, 'trainer_id' => $this->trainer_id],
            ['company_id' => $course->company_id, 'honor_amount' => $this->honor_amount]
        );
    }
}
