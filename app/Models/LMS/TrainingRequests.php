<?php

namespace App\Models\LMS;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\LMS\TrainingCategoryEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\BelongsToCompany;
use App\Models\LMS\Trainer\LmsHonorarium;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingRequests extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $table = 'training_requests';

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'organizer',
        'description',
        'start_date',
        'end_date',
        'estimated_cost',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'total_score',
        'ikatan_dinas_tahun',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ikatan_dinas_tahun' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::TRAINING_REQUEST->value);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id', 'id')
            ->where('requestable_type', ApprovalModuleEnum::TRAINING_REQUEST->value);
    }

    public function honorDetails()
    {
        return $this->hasMany(TrainingRequestHonorariumDetail::class, 'training_request_id', 'id');
    }

    /**
     * Skor tiap variabel (lms_honorarium_variables, context "eksternal") dijumlah lalu
     * dicocokkan ke band lms_honorarium (min_score-max_score). Mesin & tabelnya sama persis
     * dengan honor trainer Dokter Spesialis (LmsMeetingHonorariumAssessment::recalculate()),
     * tapi outputnya beda arti per context: Dokter Spesialis -> honor_amount Rupiah (gaji
     * trainer), Eksternal -> honor_amount ditafsir sebagai LAMA IKATAN DINAS (tahun), bukan
     * uang. Tanpa tabel assessment terpisah karena training_requests sudah 1:1 (bukan 1
     * course banyak meeting).
     */
    public function recalculateIkatanDinas(): void
    {
        $totalScore = (int) $this->honorDetails()->sum('score');

        $tahun = (float) (LmsHonorarium::where('context', TrainingCategoryEnum::EKSTERNAL->value)
            ->where('min_score', '<=', $totalScore)
            ->where('max_score', '>=', $totalScore)
            ->value('honor_amount') ?? 0);

        $this->updateQuietly(['total_score' => $totalScore, 'ikatan_dinas_tahun' => $tahun]);
    }

    /** Tanggal Pelatihan (start_date) + Lama Ikatan Dinas (tahun x 365 hari) — sama rumus
     * dengan contoh spreadsheet user (kolom J = I + H*365). */
    public function getIkatanDinasSelesaiAttribute(): ?\Illuminate\Support\Carbon
    {
        if (! $this->start_date || ! $this->ikatan_dinas_tahun) {
            return null;
        }

        return $this->start_date->copy()->addDays((int) round($this->ikatan_dinas_tahun * 365));
    }
}
