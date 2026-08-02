<?php

namespace App\Models\Task;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property string $color
 * @property string $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Companies $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\TaskProjectMember> $members
 * @property-read int|null $members_count
 * @property-read User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\Task> $tasks
 * @property-read int|null $tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProject withoutTrashed()
 * @mixin \Eloquent
 */
class TaskProject extends Model
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
        return $this->hasMany(TaskProjectMember::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'project_id')->orderBy('position');
    }
}
