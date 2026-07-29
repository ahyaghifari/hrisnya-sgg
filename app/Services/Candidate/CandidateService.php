<?php

namespace App\Services\Candidate;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Candidate\CandidateRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Auth;

class CandidateService
{
    public function __construct(
        protected CandidateRepository $candidateRepository,
        protected UserRepository $userRepository
    ) {}

    public function syncDataCandidateToUser(string $jobApplicationId, array $candidateData): User
    {
        $jobApplicantion = $this->candidateRepository->findJobApplicationById($jobApplicationId);
        $candidate = $jobApplicantion->candidate;

        // Data Jabatan
        $jobPosition = $jobApplicantion->jobVacancy->jobPosition;

        $userData = [
            'nip' => $candidateData['nip'],
            'company_id' => $candidateData['company_id'],
            'name' => $candidate->name,
            'email' => $candidate->email,
            'password' => $candidate->password,
            'type_nakes' => $candidateData['type_nakes'],
        ];

        $user = $this->userRepository->create($userData);

        $profileData = [
            'user_id' => $user->id,
            'nickname' => $candidate->name,
            'gender' => $candidate->gender,
            'place_of_birth' => $candidate->birth_place,
            'date_of_birth' => $candidate->birth_date,
            'no_kk' => $candidate->no_kk,
            'no_nik' => $candidate->nik,
            'no_bpjs_kes' => $candidate->no_bpjskes,
            'no_bpjs_ket' => $candidate->no_bpjstk,
            'address' => $candidate->ktp_address,
            'village' => $candidate->village,
            'district' => $candidate->district,
            'city' => $candidate->city,
            'province' => $candidate->province,
            'phone' => $candidate->phone_number,
            'profile_picture' => $candidate->profile_picture,
            'joining_date' => $candidateData['joined_at'],

            // Data Jabatan
            'org_structure_id' => $jobPosition->org_structure_id,
            'job_position_id' => $jobPosition->id,
            'pangkat_id' => $jobPosition->pangkat_id,
            'kelas_id' => $jobPosition->kelas_id,
            'employee_type_id' => $candidateData['employee_type_id'],
        ];
        
        $user->profile()->create($profileData);

        $candidateDocuments = $candidate->documents()->get();
        foreach ($candidateDocuments as $document) {
            $user->userPersonalDocuments()->create([
                'document_type' => $document->document_label,
                'user_name' => $candidate->name,
                'file_path' => $document->file_path,
                'uploaded_by_user' => true
            ]);
        }

        $user->jobPositions()->create([
            'job_position_id' => $jobPosition->id,
            'assigned_by' => Auth::user()->id,
            'start_date' => now(),
            'status' => 'active',
        ]);

        $role = Role::find($candidateData['role_id']);
        if ($role) {
            $user->assignRole($role->name);
        }

        return $user;
    }
}