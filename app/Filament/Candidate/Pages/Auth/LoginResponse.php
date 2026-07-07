<?php

namespace App\Filament\Candidate\Pages\Auth;

use App\Filament\Candidate\Resources\JobVacancies\JobVacancyResource;
use Filament\Facades\Filament;

class LoginResponse implements \Filament\Auth\Http\Responses\Contracts\LoginResponse
{
    public function toResponse($request)
    {
        if (session()->has('after_login_job_id')) {
            $jobId = session()->pull('after_login_job_id');
            
            if ($jobId) {
                return redirect()->to(
                    JobVacancyResource::getUrl('edit', ['record' => $jobId])
                );
            }
        }

        // Default redirect to panel URL
        return redirect()->intended(Filament::getUrl());
    }
}
