<?php

namespace App\Filament\Resources\LMS\LmsCourses\Tables;

use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use App\Helpers\PermissionHelper;
use App\Services\LMS\CourseService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LmsCoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('title')->label('Judul')->searchable()->sortable()->wrap(),
                TextColumn::make('category.name')->label('Kategori')->searchable()->sortable()->wrap(),
                TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->locale('id')->translatedFormat('d F Y') : '-'),
                TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->locale('id')->translatedFormat('d F Y') :'-'),
                TextColumn::make('source_type')->label('Tipe')->searchable()->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('status')->label('Status')->searchable()->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->preload()
                    ->multiple()
                    ->relationship('category', 'name'),

                SelectFilter::make('source_type')
                    ->label('Tipe')
                    ->options([
                        'internal' => 'Internal',
                        'external' => 'Eksternal',
                    ])
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('participants')
                        ->label('Peserta')
                        ->icon('heroicon-o-users')
                        ->color('primary')
                        ->url(fn ($record) => LmsCoursesResource::getUrl('participants', ['record' => $record])),
                    EditAction::make()->visible(fn ($record) => LmsCoursesResource::canEdit($record))->label('Detail')->icon(Heroicon::OutlinedInformationCircle),

                    Action::make('duplicate')
                        ->label('Duplikat')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('Meeting, materi, dan quiz yang terpasang ikut disalin. Peserta & riwayat TIDAK ikut disalin — sesuaikan tanggal meeting di course baru setelah ini.')
                        ->visible(fn ($record) => LmsCoursesResource::canCreate())
                        ->action(function ($record) {
                            $newCourse = app(CourseService::class)->duplicateCourse($record);

                            Notification::make()
                                ->title('Course berhasil diduplikat')
                                ->body('Jangan lupa sesuaikan tanggal meeting di course baru.')
                                ->success()
                                ->send();

                            return redirect(LmsCoursesResource::getUrl('edit', ['record' => $newCourse]));
                        }),
                ])
            ])
            ->recordUrl(false)
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make()->visible(fn ($record) => LmsCoursesResource::canDelete($record)),
                // ]),
            ]);
    }
}
