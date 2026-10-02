<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

class TaskComment extends Model
{
    use HasUlids;

    protected $fillable = [
        'task_id',
        'user_id',
        'parent_id',
        'comment',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Teks aman-XSS dengan "@Nama" (nama dari $names) ditebalkan. */
    public function bodyHtml(array $names): HtmlString
    {
        $html = e($this->comment);

        if ($names) {
            // Satu regex dengan nama terpanjang dulu: "@Budi Santoso" tidak terpecah jadi "@Budi" + sisa.
            usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $alternation = implode('|', array_map(fn ($n) => preg_quote(e($n), '/'), $names));
            $html = preg_replace('/@(?:' . $alternation . ')/iu', '<span style="font-weight:700;color:var(--brand);background:var(--brand-soft);border-radius:4px;padding:0 3px;">$0</span>', $html);
        }

        return new HtmlString($html);
    }
}
