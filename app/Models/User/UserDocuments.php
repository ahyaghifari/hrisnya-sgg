<?php

namespace App\Models\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserDocuments
 */
class UserDocuments extends Model
{
    use HasUlids;

    protected $table = 'user_documents';

    protected $fillable = [
        'user_id',
        'document_type',
        'user_name',
        'document_number',
        'issued_date',
        'file_path',
        'details',
        'uploaded_by_user',
    ];

    protected $casts = [
        'details' => 'array',
        'issued_date' => 'date',
        'uploaded_by_user' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
