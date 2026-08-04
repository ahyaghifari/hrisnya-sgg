<?php

namespace App\Models\Candidate;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
