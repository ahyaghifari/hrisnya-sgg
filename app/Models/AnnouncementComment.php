<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AnnouncementComment extends Model
{
    use HasUlids;

    protected $fillable = ['announcement_id', 'user_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
