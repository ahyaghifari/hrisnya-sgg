<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsMaterials
 * @property string $id
 * @property int|null $sort_order
 * @property string $meeting_id
 * @property string $title
 * @property string|null $content
 * @property string|null $file_path
 * @property string|null $file_type
 * @property string|null $file_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsMeetings $meeting
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereFileType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMaterials whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
