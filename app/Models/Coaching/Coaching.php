<?php

namespace App\Models\Coaching;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coaching extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'user_id',
        'coach_by',
        'created_by',
        'title',
        'goals',
        'employee_condition',
        'solution',
        'improvement_action',
        'improvement_due_date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coachBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

}
