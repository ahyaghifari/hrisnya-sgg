<?php

namespace App\Filament\Resources\LMS\LmsCourses\Pages;

use App\Enum\CourseUserStatus;
use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Services\LMS\CourseUserService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipantCourses extends Page implements HasTable
{
    use InteractsWithTable;
    
    protected static string $resource = LmsCoursesResource::class;

    protected string $view = 'filament.resources.l-m-s.lms-courses.pages.participant-courses';
    
    public $record;
    
    public function mount($record = null): void
    {
        $this->record = $this->resolveRecord($record);
    }
    
    protected function resolveRecord($key): LmsCourses
    {
        return LmsCourses::findOrFail($key);
    }
    
    public function getTitle(): string
    {
        return 'Peserta - ' . $this->record->title;
    }
    
    public function getBreadcrumbs(): array
    {
        return [
            LmsCoursesResource::getUrl('index') => 'Pelatihan',
            '#' => $this->getTitle(),
        ];
    }
    
    protected function getHeaderActions(): array
    {
        return [
            
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('user.name')
                    ->description(fn (LmsCourseUsers $record): string => $record->user->nip)
                    ->label('Nama Peserta')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('user.profile.jobPosition.name')
                    ->label('Jabatan')
                    ->searchable(),
                    
                TextColumn::make('user.profile.jobPosition.orgStructure.name')
                    ->label('Unit Kerja')
                    ->searchable(),
                    
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CourseUserStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => match (CourseUserStatus::from($state)) {
                        CourseUserStatus::REQUESTED => 'gray',
                        CourseUserStatus::APPROVED => 'primary',
                        CourseUserStatus::IN_PROGRESS => 'warning',
                        CourseUserStatus::COMPLETED => 'success',
                        CourseUserStatus::CANCELED => 'danger',
                    }),

                TextColumn::make('is_mandatory')
                    ->label('Kewajiban')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Wajib' : 'Opsional')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),

                TextColumn::make('overdue')
                    ->label('Batas Waktu')
                    ->state(function (LmsCourseUsers $record): ?string {
                        if (!$this->record->isOverdue() || $record->status === CourseUserStatus::COMPLETED->value) {
                            return null;
                        }

                        return 'Terlambat';
                    })
                    ->placeholder('-')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable(),
                    
                TextColumn::make('score')
                    ->label('Skor')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('is_passed')
                    ->label('Lulus')
                    ->badge()
                    ->color(fn ($state, $record) => is_null($record->passed_at) ? 'warning' : (is_null($state) ? 'danger' : ($state ? 'success' : 'danger')))
                    ->formatStateUsing(fn ($state, $record) => is_null($record->passed_at) ? 'In Progress' : (is_null($state) ? 'Tidak' : ($state ? 'Ya' : 'Tidak'))),
                    
                TextColumn::make('enrolled_at')
                    ->label('Tanggal Daftar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                TextColumn::make('completed_at')
                    ->label('Tanggal Selesai')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->label('Setujui')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (LmsCourseUsers $record): bool => $record->status === CourseUserStatus::REQUESTED->value)
                        ->action(function (LmsCourseUsers $record): void {
                            app(CourseUserService::class)->approveEnrollment($record, auth()->user());

                            Notification::make()->title('Pendaftaran disetujui')->success()->send();
                        }),

                    Action::make('reject')
                        ->label('Tolak')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (LmsCourseUsers $record): bool => $record->status === CourseUserStatus::REQUESTED->value)
                        ->action(function (LmsCourseUsers $record): void {
                            app(CourseUserService::class)->rejectEnrollment($record, auth()->user());

                            Notification::make()->title('Pendaftaran ditolak')->danger()->send();
                        }),

                    Action::make('mark_completed')
                        ->label('Tandai Selesai')
                        ->icon('heroicon-o-flag')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Absensi masih diisi manual, jadi status selesai perlu ditandai manual oleh L&D. Tandai pelatihan ini selesai untuk peserta?')
                        ->visible(fn (LmsCourseUsers $record): bool => in_array($record->status, [CourseUserStatus::APPROVED->value, CourseUserStatus::IN_PROGRESS->value], true))
                        ->action(function (LmsCourseUsers $record): void {
                            app(CourseUserService::class)->markCompleted($record, auth()->user());

                            Notification::make()->title('Pelatihan ditandai selesai')->success()->send();
                        }),

                    Action::make('unmark_completed')
                        ->label('Batalkan Selesai')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (LmsCourseUsers $record): bool => $record->status === CourseUserStatus::COMPLETED->value)
                        ->action(function (LmsCourseUsers $record): void {
                            app(CourseUserService::class)->unmarkCompleted($record, auth()->user());

                            Notification::make()->title('Status selesai dibatalkan')->success()->send();
                        }),

                    Action::make('upload_certificate')
                        ->label('Unggah Sertifikat')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('primary')
                        ->disabled(fn (LmsCourseUsers $record): bool => filled($record->certificate_url))
                        ->modalHeading('Unggah Sertifikat Peserta')
                        ->visible(fn (LmsCourseUsers $record): bool => $record->status === CourseUserStatus::COMPLETED->value)
                        ->schema([
                            FileUpload::make('certificate_file')
                                ->label('File Sertifikat')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(10240)
                                ->disk('public')
                                ->directory(fn (LmsCourseUsers $record) => 'certificates/' . $this->record->id . '/' . $record->user_id)
                                ->required(),
                        ])
                        ->action(function (array $data, LmsCourseUsers $record): void {
                            $record->update(['certificate_url' => $data['certificate_file']]);

                            Notification::make()
                                ->title('Sertifikat berhasil diunggah')
                                ->success()
                                ->send();
                        }),

                    Action::make('view_detail')
                        ->label('Detail')
                        ->icon('heroicon-o-information-circle')
                        ->color('secondary')
                        ->url(fn (LmsCourseUsers $record) => LmsCoursesResource::getUrl('detail-participant', ['record' => $this->record, 'user' => $record->user_id])),
                ])
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'requested' => 'Requested',
                        'approved' => 'Approved',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'canceled' => 'Canceled',
                    ]),

                SelectFilter::make('is_mandatory')
                    ->label('Kewajiban')
                    ->options([
                        '1' => 'Wajib',
                        '0' => 'Opsional',
                    ]),
            ])
            ->defaultSort('enrolled_at', 'desc');
    }
    
    protected function getTableQuery(): Builder
    {
        $courseId = $this->record->id;
        
        return LmsCourseUsers::query()
            ->with(['user.profile.jobPosition.orgStructure'])
            ->where('course_id', $courseId);
    }
}
