<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * List custom ala Trello di dalam 1 Board (`TaskProject`) — beda dari `TaskStatus` enum fixed yang
 * dipakai board personal (Todo Saya/Tugas Saya/Individu). `is_final` menandai List mana yang
 * dianggap "Selesai" (max 1 per board, lihat TaskService::setListFinal()) — task yang masuk List
 * ini dapat `status=complete` juga supaya alur rating/notifikasi lama (yang gate di status) bisa
 * dipakai ulang tanpa perubahan.
 *
 * @property string $id
 * @property string $project_id
 * @property string $name
 * @property string|null $color
 * @property int $position
 * @property bool $is_final
 * @property-read TaskProject $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Task> $tasks
 */
class TaskList extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id',
        'name',
        'color',
        'position',
        'is_final',
    ];

    protected $casts = [
        'is_final' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(TaskProject::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'list_id');
    }
}
