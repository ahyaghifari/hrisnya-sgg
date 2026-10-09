<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\Str;

/** Konteks tugas untuk notifikasi: Board apa, kartu apa, atau bagian Diskusi. Dipakai notifikasi yang punya $task dan/atau $project. */
trait DescribesTaskContext
{
    protected function context(): string
    {
        $task = $this->task ?? null;
        $project = $task?->project ?? ($this->project ?? null);

        $parts = [];
        if ($project) {
            $parts[] = 'Board "' . Str::limit($project->name, 40) . '"';
        }
        $parts[] = $task
            ? ($project ? 'Kartu' : 'Tugas') . ' "' . Str::limit($task->requirement, 50) . '"'
            : 'bagian Diskusi';

        return implode(' · ', $parts);
    }
}
