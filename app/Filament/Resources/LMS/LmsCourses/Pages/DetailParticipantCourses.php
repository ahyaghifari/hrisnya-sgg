<?php

namespace App\Filament\Resources\LMS\LmsCourses\Pages;

use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use App\Models\LMS\LmsCourseUsers;
use Filament\Forms\Components\Repeater;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class DetailParticipantCourses extends Page
{
    use InteractsWithRecord;
    
    protected static string $resource = LmsCoursesResource::class;
    protected static ?string $title = 'Detail Peserta Pelatihan';

    protected string $view = 'filament.resources.l-m-s.lms-courses.pages.detail-participant-courses';
    protected LmsCourseUsers $courseUser;

    public function mount(int | string $record, int | string $user): void
    {
        $this->record = $this->resolveRecord($record);
        $this->courseUser = LmsCourseUsers::with('user')
            ->where('course_id', $this->record->getKey())
            ->where('user_id', $user)
            ->firstOrFail();
    }

    public function configure(Schema $schema): Schema
    {
        return $schema
            ->record($this->courseUser)
            ->components([
                Section::make('Informasi Pelatihan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('course.title')
                            ->columnSpanFull()
                            ->label('Judul Pelatihan'),

                        TextEntry::make('enrolled_at')
                            ->label('Tanggal Mulai')
                            ->date('d F Y'),

                        TextEntry::make('completed_at')
                            ->label('Tanggal Selesai')
                            ->date('d F Y'),

                        TextEntry::make('user.name')
                            ->belowContent($this->courseUser->user->nip)
                            ->label('Nama Peserta'),

                        TextEntry::make('user.email')
                            ->label('Email Peserta'),

                        TextEntry::make('course_status')
                            ->label('Status Pelatihan')
                            ->default(fn ($record) => $record->completed_at === null ? 'In Progress' : 'Completed')
                            ->badge()
                            ->color(fn ($record) => $record->completed_at === null ? 'warning' : 'success'),

                        TextEntry::make('score')
                            ->label('Nilai Pelatihan'),

                        TextEntry::make('progress')
                            ->label('Progress Pelatihan')
                            ->suffix('%'),
                    ]),

                Section::make('Pertemuan Pelatihan')
                    ->schema([
                        RepeatableEntry::make('meetingUsers')
                            ->hiddenLabel()
                            ->columns(1)
                            ->schema([
                                Tabs::make()
                                    ->tabs([
                                        Tab::make('Detail Pertemuan')
                                            ->schema([
                                                TextEntry::make('meeting.title')
                                                    ->label('Judul Pertemuan'),
                                            ]),

                                        Tab::make('Absensi')
                                            ->columnSpanFull()
                                            ->columns(2)
                                            ->schema([
                                                TextEntry::make('meeting.attendance.status')
                                                    ->label('Status Absensi')
                                                    ->badge()
                                                    ->color(fn (string $state): string => match ($state) {
                                                        'present' => 'success',
                                                        'absent' => 'danger',
                                                        'late' => 'warning',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                                        'present' => 'Hadir',
                                                        'absent' => 'Tidak Hadir',
                                                        'late' => 'Terlambat',
                                                        default => 'Belum Absen',
                                                    }),

                                                TextEntry::make('meeting.attendance.checked_in_at')
                                                    ->label('Waktu Check-in')
                                                    ->formatStateUsing(fn (?string $state): string => $state ? \Carbon\Carbon::parse($state)->format('H:i') : 'Belum Check-in')
                                            ]),
                                        
                                        Tab::make('Quiz')
                                            ->schema([
                                                RepeatableEntry::make('meeting.quizzes')
                                                    ->hiddenLabel()
                                                    ->columnSpanFull()
                                                    ->columns(2)
                                                    ->schema([
                                                        TextEntry::make('quiz.title')
                                                            ->label('Judul Quiz'),

                                                        TextEntry::make('quiz.results.score')
                                                            ->default(fn ($record) => $record->results?->first()?->total_score ?? 'N/A')
                                                            ->label('Nilai Quiz'),
                                                    ])
                                            ]),
                                    ]),
                            ]),
                    ])
            ]);
    }
}
