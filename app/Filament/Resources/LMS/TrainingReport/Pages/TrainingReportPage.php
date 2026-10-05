<?php

namespace App\Filament\Resources\LMS\TrainingReport\Pages;

use App\Exports\LMS\TrainingReportExport;
use App\Filament\Resources\LMS\TrainingReport\TrainingReportResource;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourseUsers;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrainingReportPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = TrainingReportResource::class;

    protected string $view = 'filament.resources.l-m-s.training-report.pages.training-report-page';

    protected static ?string $title = 'Laporan Pelatihan';

    public ?string $baselineId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->disabled(fn () => ! $this->baselineId)
                ->tooltip(fn () => ! $this->baselineId ? 'Pilih baseline terlebih dahulu' : null)
                ->action(function (): BinaryFileResponse|null {
                    if (! $this->baselineId) {
                        Notification::make()
                            ->title('Pilih periode baseline terlebih dahulu')
                            ->warning()
                            ->send();

                        return null;
                    }

                    $data     = $this->getReportData();
                    $baseline = $data['baseline'];

                    if (! $baseline || empty($data['matrix'])) {
                        Notification::make()
                            ->title('Tidak ada data untuk diekspor')
                            ->warning()
                            ->send();

                        return null;
                    }

                    $courseHeaders = $data['courses']
                        ->mapWithKeys(fn ($c) => [$c->id => $c->title])
                        ->toArray();

                    $period = Carbon::parse($baseline->start_date)->format('d/m/Y')
                        . ' – '
                        . Carbon::parse($baseline->end_date)->format('d/m/Y');

                    $fileName = 'laporan-pelatihan-'
                        . str($baseline->company->name)->slug()
                        . '-'
                        . Carbon::parse($baseline->start_date)->format('Ymd')
                        . '.xlsx';

                    return Excel::download(
                        new TrainingReportExport(
                            matrix: $data['matrix'],
                            courseHeaders: $courseHeaders,
                            baselinePeriod: $period,
                            companyName: $baseline->company->name,
                        ),
                        $fileName
                    );
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $authUser = Auth::user();

        $query = LmsCourseBaselines::with('company')->where('is_active', true);

        if ($authUser && $authUser->company_id && ! PermissionHelper::hasGlobalRole($authUser)) {
            $query->where('company_id', $authUser->company_id);
        }

        $options = $query->get()->mapWithKeys(function ($baseline) {
            $label = $baseline->company->name
                . ' | '
                . Carbon::parse($baseline->start_date)->format('d/m/Y')
                . ' – '
                . Carbon::parse($baseline->end_date)->format('d/m/Y')
                . ' (Min: ' . $baseline->min_hours . ' jam)';

            return [$baseline->id => $label];
        });

        return $schema->components([
            Section::make('Filter Periode')
                ->schema([
                    Select::make('baselineId')
                        ->label('Periode Baseline')
                        ->options($options)
                        ->placeholder('Pilih baseline pelatihan...')
                        ->searchable()
                        ->live()
                        ->default(function () use ($options) {
                            $authUser = Auth::user();

                            if ($authUser && $authUser->company_id) {
                                // Prioritaskan baseline dari perusahaan pengguna yang sedang login
                                $companyBaseline = LmsCourseBaselines::where('company_id', $authUser->company_id)
                                    ->where('is_active', true)
                                    ->latest('start_date')
                                    ->first();

                                if ($companyBaseline) {
                                    return $companyBaseline->id;
                                }
                            }

                            // Jika tidak ada baseline khusus perusahaan, gunakan yang paling terbaru
                            return LmsCourseBaselines::where('is_active', true)
                                ->latest('start_date')
                                ->value('id');
                        })
                        ->afterStateUpdated(fn () => $this->resetTable()),
                ]),

            Section::make('Informasi Baseline')
                ->hidden(fn ($get) => blank($get('baselineId')))
                ->columns(4)
                ->schema([
                    Placeholder::make('info_company')
                        ->label('Perusahaan')
                        ->content(fn ($get) => LmsCourseBaselines::with('company')->find($get('baselineId'))?->company?->name ?? '-'),

                    Placeholder::make('info_period')
                        ->label('Periode')
                        ->content(function ($get) {
                            $b = LmsCourseBaselines::find($get('baselineId'));
                            if (! $b) return '-';
                            return Carbon::parse($b->start_date)->format('d/m/Y')
                                . ' – '
                                . Carbon::parse($b->end_date)->format('d/m/Y');
                        }),

                    Placeholder::make('info_min_hours')
                        ->label('Minimum Jam')
                        ->content(fn ($get) => (LmsCourseBaselines::find($get('baselineId'))?->min_hours ?? 0) . ' jam'),

                    Placeholder::make('info_min_courses')
                        ->label('Minimum Pelatihan')
                        ->content(fn ($get) => (LmsCourseBaselines::find($get('baselineId'))?->min_courses ?? 0) . ' pelatihan'),
                ]),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildTableQuery())
            ->columns($this->buildTableColumns())
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderByName($direction))
            ->striped()
            ->emptyStateHeading('Belum ada data pelatihan')
            ->emptyStateDescription('Pilih periode baseline di atas untuk menampilkan laporan')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }

    protected function getBaselineModel(): ?LmsCourseBaselines
    {
        if (! $this->baselineId) {
            return null;
        }

        return LmsCourseBaselines::with('company')->find($this->baselineId);
    }

    protected function getBaselineCourses(): Collection
    {
        $baseline = $this->getBaselineModel();

        if (! $baseline) {
            return new Collection();
        }

        $courseIds = LmsCourseUsers::whereHas('user', fn ($q) => $q->whereCompany($baseline->company_id))
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->distinct()
            ->pluck('course_id');

        return LmsCourses::whereIn('id', $courseIds)
            ->orderBy('start_date')
            ->get();
    }

    protected function buildTableQuery(): Builder
    {
        $baseline = $this->getBaselineModel();

        if (! $baseline) {
            return User::query()->whereRaw('0 = 1');
        }

        $courses = $this->getBaselineCourses();

        // name/nip dibaca lewat accessor User (employees), jadi cukup select users.id.
        $query = User::whereCompany($baseline->company_id)
            ->select(['users.id']);

        $startDate = $baseline->start_date;
        $endDate   = $baseline->end_date;

        // Per-course enrollment flag and enrolled_at date via correlated subquery
        foreach ($courses as $course) {
            $cid = $course->id; // ULID — alphanumeric, safe to embed
            $query->selectRaw(
                "EXISTS(SELECT 1 FROM lms_course_users WHERE user_id = users.id AND course_id = '{$cid}' AND DATE(enrolled_at) BETWEEN '{$startDate}' AND '{$endDate}') as enrolled_{$cid}"
            );
            $query->selectRaw(
                "(SELECT DATE(enrolled_at) FROM lms_course_users WHERE user_id = users.id AND course_id = '{$cid}' AND DATE(enrolled_at) BETWEEN '{$startDate}' AND '{$endDate}' LIMIT 1) as enrolled_at_{$cid}"
            );
        }

        // JP pelatihan eksternal terlaksana yang sudah disetujui (sesi di jendela baseline).
        // company_id & baseline dates = nilai internal, bukan input user.
        $perJp = \App\Models\LMS\TrainingClaim::minutesPerJp();
        $externalJpSql = "COALESCE((
                SELECT SUM(tc.jp) FROM training_claims tc
                WHERE tc.user_id = users.id AND tc.status = 'approved' AND tc.deleted_at IS NULL
                AND tc.company_id = '{$baseline->company_id}'
                AND EXISTS(SELECT 1 FROM training_claim_sessions tcs WHERE tcs.training_claim_id = tc.id AND tcs.date BETWEEN '{$startDate}' AND '{$endDate}')
            ), 0)";
        $externalCountSql = str_replace('SUM(tc.jp)', 'COUNT(*)', $externalJpSql);
        $query->selectRaw("{$externalJpSql} as external_jp");

        if ($courses->isNotEmpty()) {
            $courseIdList = $courses->pluck('id')
                ->map(fn ($id) => "'{$id}'")->implode(',');

            // Total training hours = jam pertemuan yang sudah diabsen manual masuk & pulang
            // + pelatihan eksternal terlaksana (JP final x menit_1_jp / 60).
            $query->selectRaw("
                COALESCE((
                    SELECT ROUND(SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE, lm.start_time, lm.end_time))) / 60.0, 2)
                    FROM lms_course_meeting_users lcmu
                    INNER JOIN lms_meetings lm ON lcmu.meeting_id = lm.id
                    WHERE lcmu.user_id = users.id
                    AND lcmu.course_id IN ({$courseIdList})
                    AND lcmu.absen_masuk = 1
                    AND lcmu.absen_pulang = 1
                    AND lm.start_time IS NOT NULL
                    AND lm.end_time IS NOT NULL
                ), 0) + ROUND({$externalJpSql} * {$perJp} / 60.0, 2) as total_training_hours
            ");

            // Total pelatihan = kursus LMS distinct yang diikuti + pelatihan eksternal terlaksana (disetujui)
            $query->selectRaw("
                COALESCE((
                    SELECT COUNT(DISTINCT course_id)
                    FROM lms_course_users
                    WHERE user_id = users.id
                    AND course_id IN ({$courseIdList})
                    AND DATE(enrolled_at) BETWEEN '{$startDate}' AND '{$endDate}'
                ), 0) + {$externalCountSql} as total_courses_count
            ");
        } else {
            $query->selectRaw("ROUND({$externalJpSql} * {$perJp} / 60.0, 2) as total_training_hours, {$externalCountSql} as total_courses_count");
        }

        return $query->orderByName();
    }

    protected function buildTableColumns(): array
    {
        $baseline = $this->getBaselineModel();
        $minHours = (float) ($baseline?->min_hours ?? 0);
        $courses  = $this->getBaselineCourses();
        $perJp    = \App\Models\LMS\TrainingClaim::minutesPerJp();

        $columns = [
            TextColumn::make('nip')
                ->label('NIP')
                ->searchable(query: fn (Builder $query, string $search) => $query->whereNipLike($search)),

            TextColumn::make('name')
                ->label('Nama Karyawan')
                ->searchable(query: fn (Builder $query, string $search) => $query->searchName($search))
                ->weight(FontWeight::Bold),
        ];

        foreach ($courses as $course) {
            $cid = $course->id;
            $columns[] = TextColumn::make("enrolled_{$cid}")
                ->label($course->title)
                ->description(fn ($record) => $record->{"enrolled_at_{$cid}"}
                    ? Carbon::parse($record->{"enrolled_at_{$cid}"})->format('d/m/Y')
                    : null)
                ->alignCenter()
                ->badge()
                ->icon(fn ($state) => (bool) $state ? Heroicon::CheckCircle : Heroicon::XCircle)
                ->formatStateUsing(fn ($state) => (bool) $state ? '' : '')
                ->color(fn ($state) => (bool) $state ? 'success' : 'danger');
        }

        // Klik angka JP -> modal daftar pelatihan eksternal terlaksana yang disetujui.
        $columns[] = TextColumn::make('external_jp')
            ->label('JP Eksternal (Terlaksana)')
            ->alignCenter()
            ->formatStateUsing(fn ($state) => (int) $state . ' JP')
            ->icon(fn ($state) => (int) $state > 0 ? Heroicon::ListBullet : null)
            ->iconPosition('after')
            ->tooltip(fn ($state) => (int) $state > 0 ? 'Lihat detail' : null)
            ->action(
                Action::make('detail_eksternal')
                    ->modalHeading('Pelatihan Eksternal Terlaksana')
                    ->modalContent(fn ($record) => view('livewire.lms.partials.external-claims-list', [
                        'claims' => $baseline
                            ? \App\Models\LMS\TrainingClaim::approvedFor($baseline, $record->id)
                            : collect(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
            )
            ->sortable();

        $columns[] = TextColumn::make('external_hours_virtual')
            ->label('Jam Eksternal (Terlaksana)')
            ->alignCenter()
            ->getStateUsing(fn ($record) => round((int) ($record->external_jp ?? 0) * $perJp / 60, 2))
            ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.') . ' jam');

        $columns[] = TextColumn::make('total_training_hours')
            ->label('Total Jam Pelatihan')
            ->alignCenter()
            ->formatStateUsing(fn ($state) => number_format((float) $state, 0) . ' jam')
            ->color(fn ($state) => (float) $state >= $minHours ? 'success' : 'warning')
            ->badge()
            ->sortable();

        $columns[] = TextColumn::make('unfulfilled_hours_virtual')
            ->label('Jam Belum Terpenuhi')
            ->alignCenter()
            ->getStateUsing(fn ($record) => max(0.0, round($minHours - (float) ($record->total_training_hours ?? 0), 2)))
            ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 0) . ' jam' : 'Terpenuhi')
            ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
            ->badge();

        $columns[] = TextColumn::make('total_courses_count')
            ->label('Total Pelatihan')
            ->alignCenter()
            ->suffix(' pelatihan')
            ->badge()
            ->color('primary')
            ->sortable();

        return $columns;
    }

    // ─── Export data (used by export action) ─────────────────────────────────

    public function getReportData(): array
    {
        if (! $this->baselineId) {
            return [
                'baseline' => null,
                'courses'  => collect(),
                'matrix'   => [],
            ];
        }

        $baseline = LmsCourseBaselines::with('company')->find($this->baselineId);

        if (! $baseline) {
            return [
                'baseline' => null,
                'courses'  => collect(),
                'matrix'   => [],
            ];
        }

        // Courses ditentukan dari siapa yang enroll (karyawan company baseline), bukan company_id course itu sendiri
        $courseIds = LmsCourseUsers::whereHas('user', fn ($q) => $q->whereCompany($baseline->company_id))
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->distinct()
            ->pluck('course_id');

        $courses = LmsCourses::whereIn('id', $courseIds)
            ->orderBy('start_date')
            ->get();

        $courseIds = $courses->pluck('id')->toArray();

        // All employees of the same company
        $users = User::whereCompany($baseline->company_id)
            ->orderByName()
            ->get();

        // Enrollment lookup: [user_id][course_id] => true
        $enrollments = LmsCourseUsers::whereIn('course_id', $courseIds)
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->get(['user_id', 'course_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('course_id')->flip()->map(fn () => true));

        // Completed meeting users with their meeting data: [user_id] => Collection
        $completedMeetingUsers = LmsCourseMeetingUsers::whereIn('course_id', $courseIds)
            ->where('is_completed', true)
            ->with('meeting:id,start_time,end_time')
            ->get()
            ->groupBy('user_id');

        // Total courses count per user: [user_id] => count
        $totalCoursesByUser = LmsCourseUsers::whereIn('course_id', $courseIds)
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->selectRaw('user_id, COUNT(DISTINCT course_id) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $external = \App\Models\LMS\TrainingClaim::approvedByUser($baseline);

        $matrix = [];

        foreach ($users as $user) {
            $row = [
                'nip'     => $user->nip,
                'name'    => $user->name,
                'company' => $baseline->company->name,
                'courses' => [],
            ];

            // Enrollment flags per course
            foreach ($courses as $course) {
                $row['courses'][$course->id] = isset($enrollments[$user->id])
                    && $enrollments[$user->id]->has($course->id);
            }

            // Total completed training hours
            $totalMinutes = 0;
            if (isset($completedMeetingUsers[$user->id])) {
                foreach ($completedMeetingUsers[$user->id] as $mu) {
                    if ($mu->meeting && $mu->meeting->start_time && $mu->meeting->end_time) {
                        $start = Carbon::parse($mu->meeting->start_time);
                        $end   = Carbon::parse($mu->meeting->end_time);
                        if ($end->greaterThan($start)) {
                            $totalMinutes += $start->diffInMinutes($end);
                        }
                    }
                }
            }

            $externalHours = $external[$user->id]['hours'] ?? 0;
            $totalHours = round($totalMinutes / 60, 2) + $externalHours;

            $row['external_jp']        = $external[$user->id]['jp'] ?? 0;
            $row['external_hours']     = $externalHours;
            $row['total_hours']        = $totalHours;
            $row['total_courses']      = ($totalCoursesByUser[$user->id] ?? 0) + ($external[$user->id]['count'] ?? 0);
            $row['unfulfilled_hours']  = max(0, round($baseline->min_hours - $totalHours, 2));

            $matrix[] = $row;
        }

        return [
            'baseline' => $baseline,
            'courses'  => $courses,
            'matrix'   => $matrix,
        ];
    }
}
