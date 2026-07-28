<?php

namespace App\Models\Master\Task;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTaskService
 * @property string $id
 * @property string|null $company_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskService whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskService extends Model
{
    use HasUlids;

    protected $table = 'm_task_services';
    protected $fillable = ['name', 'company_id'];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
