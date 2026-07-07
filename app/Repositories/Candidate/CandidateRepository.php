<?php

namespace App\Repositories\Candidate;

use App\Models\Candidate\CandidateJobApplications;
use App\Models\Candidate\Candidates;

class CandidateRepository
{
    public function findById(string $id)
    {
        return Candidates::find($id);
    }

    public function findJobApplicationById(string $id)
    {
        return CandidateJobApplications::with('candidate')->find($id);
    }
}
