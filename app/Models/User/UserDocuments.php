<?php

namespace App\Models\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperUserDocuments
 * @property string $id
 * @property string $user_id
 * @property string $document_type
 * @property string $user_name
 * @property string|null $document_number
 * @property \Illuminate\Support\Carbon|null $issued_date
 * @property string $file_path
 * @property array<array-key, mixed>|null $details
 * @property bool $uploaded_by_user
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $file_url
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereIssuedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereUploadedByUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDocuments withoutTrashed()
 * @mixin \Eloquent
 */
class UserDocuments extends Model
{
    use HasUlids, SoftDeletes;

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

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
