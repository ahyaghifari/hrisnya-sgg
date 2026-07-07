<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems\Pages;

use App\Filament\Resources\LMS\LmsCoursePlanItems\LmsCoursePlanItemResource;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsCoursePlanItems;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class ListLmsCoursePlanItems extends ListRecords
{
    public $type;
    protected static string $resource = LmsCoursePlanItemResource::class;

    protected static ?string $title = 'Daftar Pengajuan Pelatihan';

    public ?string $activeTab = 'course_request';

    public function  mount(): void
    {
        $this->type = request()->query('type', 'request');
    }

    protected function getHeaderActions(): array
    {
        $type = $this->type;
        return [
            CreateAction::make()->label('Ajukan Pelatihan')->visible($type === 'request'),
        ];
    }

    public function getTabs(): array
    {
        $type = $this->type;

        if ($type === 'request') {
            return [
                'course_request' => Tab::make('Pengajuan Pelatihan')
                    ->badge($this->getCourseRequestCount())
                    ->visible($type === 'request'),
                'course_invitation' => Tab::make('Undangan Pelatihan')
                    ->badge($this->getCourseInvitationCount())
                    ->visible($type === 'request'),
            ];
        }

        return [];
    }

    protected function getCourseRequestCount(): int
    {
        return LmsCoursePlanItems::where('requested_by', Auth::id())->count();
    }

    protected function getCourseInvitationCount(): int
    {
        return LmsCourseUsers::where('user_id', Auth::id())->count();
    }

    public function table(Table $table): Table
    {
        if ($this->activeTab === 'course_invitation') {
            return $table
                ->query(
                    LmsCourseUsers::query()->where('user_id', Auth::id())
                )
                ->columns([
                    TextColumn::make('course.title')
                        ->label('Judul Pelatihan')
                        ->searchable()
                        ->sortable(),

                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'pending' => 'warning',
                            'enrolled' => 'success',
                            'completed' => 'info',
                            'cancelled' => 'danger',
                            default => 'gray',
                        }),

                    TextColumn::make('progress')
                        ->label('Progress')
                        ->suffix('%')
                        ->numeric()
                        ->placeholder('0'),

                    TextColumn::make('enrolled_at')
                        ->label('Tanggal Mendaftar')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-')
                        ->sortable(),

                    TextColumn::make('completed_at')
                        ->label('Tanggal Selesai')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                ])
                ->recordUrl(null)
                ->recordActions([]);
        }

        return $table
            ->query($this->type === 'request' ? LmsCoursePlanItems::query()->where('requested_by', Auth::id()) : LmsCoursePlanItems::query()->where('request_status', '!=', 'draft'))
            ->columns([
                TextColumn::make('proposed_title')
                    ->label('Nama Pelatihan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('request_status')
                    ->label('Status Pengajuan')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->wrap()
                    ->placeholder('-'),
                    
                TextColumn::make('requested_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('j F Y')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordActions([
                EditAction::make()->hiddenLabel()
            ])
            ->recordUrl(function (LmsCoursePlanItems $record) {
                return LmsCoursePlanItemResource::getUrl('edit', ['record' => $record->id, 'type' => $this->type]);
            });
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }
}
