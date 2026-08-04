<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsMaterials extends Model
{
    use HasUlids;

    protected $table = 'lms_materials';

    protected $fillable = [
        'sort_order',
        'meeting_id',
        'title',
        'content',
        'file_path',
        'file_type',
        'file_name',
    ];

    protected $casts = [
        'content' => 'string',
    ];

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id', 'id');
    }

    protected static function booted()
    {
        static::saving(function ($meeting) {
            if (is_null($meeting->sort_order)) {
            $meeting->sort_order = static::max('sort_order') + 1;
            }
        });
    }
}
