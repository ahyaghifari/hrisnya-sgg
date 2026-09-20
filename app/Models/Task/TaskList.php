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
 */
class TaskList extends Model
{
    use HasUlids;

    /**
     * Preset warna List (key => label) - gradient-nya didefinisikan di task-list-page.blade.php
     * (.task-hero-column.{key}). 4 pertama sama dengan warna kolom status Todo/Proses/Review/Selesai,
     * sisanya warna sistem. Kolom `color` juga masih boleh berisi hex (List lama).
     */
    public const COLOR_PRESETS = [
        'navy' => 'Biru',
        'gold' => 'Proses',
        'gold-dark' => 'Review',
        'green' => 'Selesai',
        'pink' => 'Pink',
        'teal' => 'Teal',
        'sky' => 'Biru Muda',
        'red' => 'Merah',
        'purple' => 'Ungu',
        'indigo' => 'Indigo',
        'orange' => 'Oranye',
        'rose' => 'Mawar',
        'lime' => 'Hijau Lumut',
        'slate' => 'Abu-abu',
    ];

    public static function isValidColor(?string $color): bool
    {
        return $color === null || isset(self::COLOR_PRESETS[$color]) || (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
    }

    protected $fillable = [
        'project_id',
        'name',
        'color',
        'position',
        'is_final',
        'is_review',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'is_review' => 'boolean',
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
