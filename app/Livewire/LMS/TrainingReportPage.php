<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Exports\LMS\TrainingReportExport;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourseBaselines;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * "Laporan Pelatihan" — padanan portal dari Filament TrainingReportResource (halaman filter
 * tunggal, bukan CRUD). Pilih periode Baseline Pelatihan aktif → tabel matriks karyawan × kursus
 * (ikut jendela start_date baseline), total jam pelatihan (dari pertemuan yang is_completed),
 * jam belum terpenuhi vs min_hours baseline, dan total pelatihan diikuti. Export Excel pakai
 * class export yang SAMA dengan admin (App\Exports\LMS\TrainingReportExport) — tidak
 * di-duplikasi, cuma dipanggil ulang dengan data dari komponen ini.
 *
 * Bentuk tabel & urutan kolom SENGAJA disamakan persis dengan TrainingReportPage admin: NIP,
 * Nama, [kolom per-kursus centang/silang + tanggal enroll], Total Jam Pelatihan, Jam Belum
 * Terpenuhi, Total Pelatihan.
 */
#[Layout('components.layout', ['title' => 'Laporan Pelatihan'])]
class TrainingReportPage extends Component
{
    public ?string $baselineId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);

        $this->baselineId = $this->defaultBaselineId();
    }

    private function defaultBaselineId(): ?string
    {
        $user = Auth::user();

        if ($user->company_id) {
            $companyBaseline = LmsCourseBaselines::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->latest('start_date')
                ->first();

            if ($companyBaseline) {
                return $companyBaseline->id;
            }
        }

        return LmsCourseBaselines::where('is_active', true)->latest('start_date')->value('id');
    }

    public function getBaselineOptionsProperty()
    {
        $user = Auth::user();

        $query = LmsCourseBaselines::with('company')->where('is_active', true);

        if ($user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query->get()->mapWithKeys(fn ($baseline) => [
            $baseline->id => $baseline->company->name . ' | '
                . Carbon::parse($baseline->start_date)->format('d/m/Y') . ' – '
                . Carbon::parse($baseline->end_date)->format('d/m/Y')
                . ' (Min: ' . $baseline->min_hours . ' jam)',
        ]);
    }

    public function getBaselineProperty(): ?LmsCourseBaselines
    {
        if (! $this->baselineId) {
            return null;
        }

        return LmsCourseBaselines::with('company')->find($this->baselineId);
    }

    public function getBaselineCoursesProperty()
    {
        $baseline = $this->baseline;

        if (! $baseline) {
            return collect();
        }

        $courseIds = LmsCourseUsers::whereHas('user', fn ($q) => $q->where('company_id', $baseline->company_id))
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->distinct()
            ->pluck('course_id');

        return LmsCourses::whereIn('id', $courseIds)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Sama persis logikanya dengan TrainingReportPage::getReportData() admin — dipertahankan
     * identik supaya angka yang ditampilkan portal & admin tidak pernah berbeda.
     */
    public function getReportMatrixProperty(): array
    {
        $baseline = $this->baseline;

        if (! $baseline) {
            return [];
        }

        $courses = $this->baselineCourses;
        $courseIds = $courses->pluck('id')->toArray();

        $users = User::where('company_id', $baseline->company_id)->orderBy('name')->get();

        $enrollments = LmsCourseUsers::whereIn('course_id', $courseIds)
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->get(['user_id', 'course_id', 'enrolled_at'])
            ->groupBy('user_id');

        $completedMeetingUsers = LmsCourseMeetingUsers::whereIn('course_id', $courseIds)
            ->where('is_completed', true)
            ->with('meeting:id,start_time,end_time')
            ->get()
            ->groupBy('user_id');

        $totalCoursesByUser = LmsCourseUsers::whereIn('course_id', $courseIds)
            ->whereDate('enrolled_at', '>=', $baseline->start_date)
            ->whereDate('enrolled_at', '<=', $baseline->end_date)
            ->selectRaw('user_id, COUNT(DISTINCT course_id) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $matrix = [];

        foreach ($users as $user) {
            $userEnrollments = $enrollments->get($user->id, collect())->keyBy('course_id');

            $row = [
                'nip' => $user->nip,
                'name' => $user->name,
                'company' => $baseline->company->name,
                'courses' => [],
            ];

            foreach ($courses as $course) {
                $enrollment = $userEnrollments->get($course->id);
                $row['courses'][$course->id] = [
                    'enrolled' => (bool) $enrollment,
                    'enrolled_at' => $enrollment?->enrolled_at,
                ];
            }

            $totalMinutes = 0;
            foreach ($completedMeetingUsers->get($user->id, collect()) as $mu) {
                if ($mu->meeting && $mu->meeting->start_time && $mu->meeting->end_time) {
                    $start = Carbon::parse($mu->meeting->start_time);
                    $end = Carbon::parse($mu->meeting->end_time);
                    if ($end->greaterThan($start)) {
                        $totalMinutes += $start->diffInMinutes($end);
                    }
                }
            }

            $totalHours = round($totalMinutes / 60, 2);

            $row['total_hours'] = $totalHours;
            $row['total_courses'] = $totalCoursesByUser[$user->id] ?? 0;
            $row['unfulfilled_hours'] = max(0, round($baseline->min_hours - $totalHours, 2));

            $matrix[] = $row;
        }

        return $matrix;
    }

    public function exportExcel()
    {
        if (! $this->baselineId) {
            Notification::make()->title('Pilih periode baseline terlebih dahulu')->warning()->send();

            return null;
        }

        $baseline = $this->baseline;
        $matrix = $this->reportMatrix;

        if (! $baseline || empty($matrix)) {
            Notification::make()->title('Tidak ada data untuk diekspor')->warning()->send();

            return null;
        }

        $courseHeaders = $this->baselineCourses->mapWithKeys(fn ($c) => [$c->id => $c->title])->toArray();

        $period = Carbon::parse($baseline->start_date)->format('d/m/Y') . ' – ' . Carbon::parse($baseline->end_date)->format('d/m/Y');

        $fileName = 'laporan-pelatihan-' . str($baseline->company->name)->slug() . '-' . Carbon::parse($baseline->start_date)->format('Ymd') . '.xlsx';

        // Export butuh struktur ['courses'=>[courseId=>bool]] per baris, bukan ['enrolled'=>..,'enrolled_at'=>..]
        // — flatten dulu supaya TrainingReportExport (dipakai bareng admin) tidak perlu diubah.
        $exportMatrix = array_map(function ($row) {
            $row['courses'] = array_map(fn ($c) => $c['enrolled'], $row['courses']);

            return $row;
        }, $matrix);

        return Excel::download(
            new TrainingReportExport(
                matrix: $exportMatrix,
                courseHeaders: $courseHeaders,
                baselinePeriod: $period,
                companyName: $baseline->company->name,
            ),
            $fileName
        );
    }

    public function render()
    {
        return view('livewire.lms.training-report-page');
    }
}
