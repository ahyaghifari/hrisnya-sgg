<?php

namespace App\Models\Master\Task;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
