<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCandidateDocuments
 * @property string $id
 * @property string $candidate_id
 * @property string $doc_type
 * @property string|null $document_label
 * @property string $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Candidate\Candidates|null $candidate
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereCandidateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereDocType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereDocumentLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateDocuments whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CandidateDocuments extends Model
{
    use HasUlids;

    protected $table = 'candidate_documents';

    protected $fillable = [
        'candidate_id',
        'doc_type',
        'document_label',
        'file_path',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidates::class, 'candidate_id');
    }
}
