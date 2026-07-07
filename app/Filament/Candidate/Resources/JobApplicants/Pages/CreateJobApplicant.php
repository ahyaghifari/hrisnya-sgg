<?php

namespace App\Filament\Candidate\Resources\JobApplicants\Pages;

use App\Filament\Candidate\Resources\JobApplicants\JobApplicantResource;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Candidate\Candidates;
use App\Models\Job\JobOpenings;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateJobApplicant extends CreateRecord
{
    public $job_vacancy_id;

    protected static string $resource = JobApplicantResource::class;

    public function getTitle(): string|Htmlable
    {
        $jobVacancy = JobOpenings::with('jobPosition.orgStructure')->find($this->job_vacancy_id);
        $jobTitle = $jobVacancy ? $jobVacancy->jobPosition->name : 'Lowongan Pekerjaan Baru';
        $jobUnit = $jobVacancy ? $jobVacancy->jobPosition->orgStructure->name : '';
        return "Lamar Pekerjaan: " . $jobTitle . " - " . $jobUnit;
    }

    public function mount(): void
    {
        parent::mount();

        $this->job_vacancy_id = request()->query('job_vacancy_id');
    }

    public function getRecord(): ?Model
    {
        return JobOpenings::with('jobPosition.orgStructure', 'company')->find($this->job_vacancy_id);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Create the job application record
            $application = CandidateJobApplications::create([
                'candidate_id'   => $data['candidate_id'],
                'job_vacancy_id' => $data['job_vacancy_id'],
                'current_stage'  => $data['current_stage'],
                'status'         => $data['status'],
                'applied_at'     => $data['applied_at'],
            ]);

            $candidate = Candidates::find($data['candidate_id']);

            if (!$candidate) {
                return $application;
            }

            $candidateData = $data['candidate'] ?? [];

            // Update candidate direct fields
            $directFields = array_filter([
                'name'                  => $candidateData['name'] ?? null,
                'nickname'              => $candidateData['nickname'] ?? null,
                'gender'                => $data['gender'] ?? null,
                'blood_type'            => $candidateData['blood_type'] ?? null,
                'birth_place'           => $candidateData['birth_place'] ?? null,
                'birth_date'            => $candidateData['birth_date'] ?? null,
                'body_height_weight'    => $candidateData['body_height_weight'] ?? null,
                'status_marital'        => $data['status_marital'] ?? null,
                'marriage_date'         => $candidateData['marriage_date'] ?? null,
                'email'                 => $candidateData['email'] ?? null,
                'phone_number'          => $candidateData['phone_number'] ?? null,
                'nik'                   => $candidateData['nik'] ?? null,
                'social_media_accounts' => $candidateData['social_media_accounts'] ?? null,
                'last_education'        => $candidateData['last_education'] ?? null,
                'domicile_address'      => $candidateData['domicile_address'] ?? null,
                'nationality'           => $candidateData['nationality'] ?? null,
                'religion'              => $candidateData['religion'] ?? null,
                'profile_picture'       => $candidateData['profile_picture'] ?? null,
            ], fn ($value) => $value !== null);

            if (!empty($directFields)) {
                $candidate->fill($directFields)->save();
            }

            // Update existing item by id, or create new one if no id present
            $syncItem = function (string $relation, array $item) use ($candidate) {
                $id = $item['id'] ?? null;
                unset($item['id'], $item['candidate_id'], $item['created_at'], $item['updated_at']);
                $item = array_filter($item, fn ($v) => $v !== null);
                if (empty($item)) {
                    return;
                }
                if ($id) {
                    $candidate->{$relation}()->where('id', $id)->update($item);
                } else {
                    $candidate->{$relation}()->create($item);
                }
            };

            // Family Members
            foreach ($candidateData['family_members'] ?? [] as $item) {
                $syncItem('familyMembers', $item ?? []);
            }

            // Educations
            foreach ($candidateData['educations'] ?? [] as $item) {
                $syncItem('educations', $item ?? []);
            }

            // Trainings
            foreach ($candidateData['trainings'] ?? [] as $item) {
                $syncItem('trainings', $item ?? []);
            }

            // Organizations
            foreach ($candidateData['organizations'] ?? [] as $item) {
                $syncItem('organizations', $item ?? []);
            }

            // Work Experiences
            foreach ($candidateData['work_experiences'] ?? [] as $item) {
                $syncItem('workExperiences', $item ?? []);
            }

            // References
            foreach ($candidateData['references'] ?? [] as $item) {
                $syncItem('references', $item ?? []);
            }

            // Additional Infos (hasOne – always updateOrCreate)
            $additionalInfosData = array_filter(
                $candidateData['additionalInfos'] ?? [],
                fn ($v) => $v !== null
            );
            if (!empty($additionalInfosData)) {
                $candidate->additionalInfos()->updateOrCreate(
                    ['candidate_id' => $candidate->id],
                    $additionalInfosData
                );
            }

            // Self Assessments (hasOne – always updateOrCreate)
            $selfAssessmentsData = array_filter(
                $candidateData['selfAssessments'] ?? [],
                fn ($v) => $v !== null
            );
            if (!empty($selfAssessmentsData)) {
                $candidate->selfAssessments()->updateOrCreate(
                    ['candidate_id' => $candidate->id],
                    $selfAssessmentsData
                );
            }

            return $application;
        });
    }

    public function getFormActions(): array
    {
        return [
            Action::make('apply')
                ->label('Lamar Sekarang')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pengajuan Lamaran')
                ->modalDescription('Apakah Anda yakin ingin mengajukan lamaran untuk posisi ini? Pastikan semua informasi yang Anda berikan sudah benar sebelum melanjutkan.')
                ->action(function () {
                    $this->handleRecordCreation($this->data);

                    $this->redirect($this->getRedirectUrl());
                })
        ];
    }
}
