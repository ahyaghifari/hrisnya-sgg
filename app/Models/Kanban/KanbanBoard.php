<?php

namespace App\Models\Kanban;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KanbanBoard extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'color',
        'owner_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(KanbanBoardMember::class, 'board_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class, 'board_id');
    }
}
