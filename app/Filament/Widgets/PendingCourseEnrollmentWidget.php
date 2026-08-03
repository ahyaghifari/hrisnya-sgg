<?php

namespace App\Filament\Widgets;

use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\LMS\LmsCourses\LmsCoursesResource;
use App\Models\LMS\LmsCourseUsers;
use App\Services\LMS\CourseUserService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Daftar pengajuan ikut pelatihan yang masih REQUESTED - approve/tolak langsung dari sini,
 * biar approver (pemegang permission approve_pengajuan_pelatihan) tidak perlu buka satu-satu
 * halaman Peserta tiap course. Approve/reject-nya sendiri lewat CourseUserService supaya
 * konsisten (notifikasi ke peserta, dsb) dengan tombol approve di halaman Peserta/Detail Course. */
class PendingCourseEnrollmentWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Pengajuan Ikut Pelatihan';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can(CustomPermissionEnum::APPROVE_PENGAJUAN_PELATIHAN->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LmsCourseUsers::query()
                    ->where('status', CourseUserStatus::REQUESTED->value)
                    ->with(['user', 'course'])
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('Peserta')
                    ->description(fn (LmsCourseUsers $record): ?string => $record->user?->nip)
                    ->searchable(),

                TextColumn::make('course.title')
                    ->label('Pelatihan')
                    ->searchable(),

                TextColumn::make('enrolled_at')
                    ->label('Tanggal Daftar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('enrolled_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (LmsCourseUsers $record): void {
                        app(CourseUserService::class)->approveEnrollment($record, Auth::user());

                        Notification::make()->title('Pendaftaran disetujui')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (LmsCourseUsers $record): void {
                        app(CourseUserService::class)->rejectEnrollment($record, Auth::user());

                        Notification::make()->title('Pendaftaran ditolak')->danger()->send();
                    }),

                Action::make('view_course')
                    ->label('Lihat Peserta')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (LmsCourseUsers $record): string => LmsCoursesResource::getUrl('participants', ['record' => $record->course_id])),
            ]);
    }
}
