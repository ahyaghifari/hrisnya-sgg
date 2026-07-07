<?php

namespace App\Filament\Candidate\Resources\CandidateProfiles\Pages;

use App\Filament\Candidate\Resources\CandidateProfiles\CandidateProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCandidateProfile extends EditRecord
{
    protected static string $resource = CandidateProfileResource::class;
    protected static ?string $title = 'Edit Profil Saya';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $candidate = $this->record;
        $documents = $candidate->documents()->orderBy('id', 'asc')->get();

        foreach ($documents as $document) {
            if (in_array($document->doc_type, ['ktp', 'kk', 'bpjstk', 'ijazah'])) {
                $data[$document->doc_type] = $document->file_path;
            }
        }

        $additionalDocs = $documents->where('doc_type', 'additional');
        if ($additionalDocs->isNotEmpty()) {
            $data['additional_documents'] = $additionalDocs->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'document_label' => $doc->document_label,
                    'document_file' => $doc->file_path,
                ];
            })->values()->toArray();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $candidate = $this->record;

        $documentTypes = [
            'ktp' => 'KTP',
            'kk' => 'KK',
            'bpjstk' => 'BPJS Ketenagakerjaan',
            'ijazah' => 'Ijazah',
        ];

        foreach ($documentTypes as $field => $type) {
            if (!empty($this->data[$field])) {
                $filePath = is_array($this->data[$field]) 
                    ? reset($this->data[$field]) 
                    : $this->data[$field];
                
                $candidate->documents()->updateOrCreate(
                    ['doc_type' => $field],
                    [
                        'document_label' => $type,
                        'file_path' => $filePath,
                    ]
                );
            }
        }

        $submittedIds = collect($this->data['additional_documents'] ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();
        
        $candidate->documents()
            ->where('doc_type', 'additional')
            ->when(!empty($submittedIds), function ($query) use ($submittedIds) {
                $query->whereNotIn('id', $submittedIds);
            })
            ->delete();

        if (!empty($this->data['additional_documents'])) {
            foreach ($this->data['additional_documents'] as $doc) {
                if (!empty($doc['document_file'])) {
                    $filePath = is_array($doc['document_file']) 
                        ? reset($doc['document_file']) 
                        : $doc['document_file'];
                    
                    $documentData = [
                        'doc_type' => 'additional',
                        'document_label' => $doc['document_label_name'] ?? 'Lainnya',
                        'file_path' => $filePath,
                    ];

                    if (!empty($doc['id'])) {
                        $candidate->documents()->where('id', $doc['id'])->update($documentData);
                    } else {
                        $candidate->documents()->create($documentData);
                    }
                }
            }
        }

        $requiredFields = [
            'name', 'email', 'phone_number', 'nik', 'no_kk', 'birth_place', 'birth_date'
        ];

        $isComplete = true;
        foreach ($requiredFields as $field) {
            if (empty($this->data[$field])) {
                $isComplete = false;
                break;
            }
        }

        if ($isComplete && !$candidate->is_onboarded) {
            $candidate->is_onboarded = true;
            $candidate->save();
        }
    }
}
