<?php

namespace App\Filament\Candidate\Resources\JobVacancies\Schemas;

use App\Filament\Candidate\Resources\JobApplicants\JobApplicantResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobVacancyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(function ($record) {
                    if ($record) {
                        $hasApply = $record->applicants()->where('candidate_id', auth()->id())->exists();
                        if ($hasApply) {
                            return 'Maaf, Anda sudah melamar pekerjaan ini sebelumnya';
                        }
                    }
                })
                ->description(function ($record) {
                    if ($record) {
                        $hasApply = $record->applicants()->where('candidate_id', auth()->id())->exists();
                        if ($hasApply) {
                            return 'Anda tidak dapat melamar pekerjaan ini lagi. Silakan lihat status lamaran Anda di halaman "Lamaran Saya".';
                        }
                    }
                })
                ->headerActions([
                    Action::make('Lamaran Saya')
                        ->url(JobApplicantResource::getUrl('index'))
                        ->visible(function ($record) {
                            if ($record) {
                                return $record->applicants()->where('candidate_id', auth()->id())->exists();
                            }
                            return false;
                        })
                        ->icon('heroicon-o-archive-box'),
                ])
                ->hidden(function ($record) {
                    if ($record) {
                        return !$record->applicants()->where('candidate_id', auth()->id())->exists();
                    }
                    return true;
                })
                ->columnSpanFull(),

                Section::make(fn ($record) => $record ? $record->jobPosition->name . ' - ' . $record->jobPosition->orgStructure->name : 'Detail Lowongan Kerja')
                    ->headerActions([
                        Action::make('apply')
                            ->label('Lamar Pekerjaan')
                            ->icon('heroicon-o-briefcase')
                            ->visible(fn ($record) => $record && !$record->applicants()->where('candidate_id', auth()->id())->exists())
                            ->url(fn ($record) => JobApplicantResource::getUrl('create', ['job_vacancy_id' => $record->id]))
                    ])
                    ->description($record->company->name ?? 'Syifa Global Group')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('primary')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->label('Deskripsi Lowongan')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull(),

                        TextEntry::make('requirements')
                            ->label('Persyaratan:')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
