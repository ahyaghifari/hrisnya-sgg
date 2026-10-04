<?php

namespace App\Services\LMS;

use App\Events\LmsProgresUpdated;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\User;
use App\Notifications\CourseParticipantEnrolled;
use App\Notifications\CoursePublished;
use App\Repositories\LMS\CourseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CourseService
{
    public function __construct(
        protected CourseRepository $courseRepositories,
    ) {}

    public function getAllCategories()
    {
        return $this->courseRepositories->getAllCategories();
    }

    public function getAllCourse(?string $keyword = null, ?string $category = null, int $limit = 10)
    {
        return $this->courseRepositories->getAllCourse($keyword, $category, $limit);
    }

    public function getCourseById(string $id)
    {
        $course = $this->courseRepositories->getCourseById($id);

        if (!$course) {
            return null;
        }

        // event(new LmsProgresUpdated(auth('sanctum')->user(), $course));
        return $course;
    }

    public function getFeedbackQuestions()
    {
        return $this->courseRepositories->getActiveFeedbackQuestions();
    }

    /**
     * 1 respondent cuma boleh submit umpan balik 1x per course — dicek di sini (bukan
     * dibiarkan gagal di constraint database, karena lms_course_feedbacks memang tidak
     * punya unique constraint) supaya pesannya jelas alih-alih exception generik.
     */
    public function storeFeedback(array $data)
    {
        $alreadySubmitted = LmsCourseFeedbacks::where('course_id', $data['course_id'])
            ->where('respondent_id', $data['respondent_id'])
            ->exists();

        if ($alreadySubmitted) {
            throw new \RuntimeException('Umpan balik untuk pelatihan ini sudah pernah diisi.');
        }

        return DB::transaction(fn () => $this->courseRepositories->storeFeedback($data));
    }

    public function getSuggestionsKeywords(string $partialKeyword, int $limit = 5)
    {
        return $this->courseRepositories->getSuggestionsKeywords($partialKeyword, $limit);
    }

    /**
     * Duplikat course jadi batch baru — menggantikan rencana "scheduler" otomatis (kolom
     * repeat_cycle/next_run_date tidak pernah punya job consumer, lihat
     * planning/2026-07-14-perbaikan-lms-dan-akses-portal.md Tahap 3 #4). Admin duplikat manual
     * course lama sebagai template, lalu sesuaikan tanggal meeting-nya di course baru.
     * Ikut disalin: meeting, materi, dan quiz yang terpasang ke tiap meeting (quiz bank-nya
     * sendiri tetap dipakai bersama, bukan digandakan — sama seperti desain aslinya).
     * TIDAK ikut disalin: peserta, kehadiran, hasil quiz, riwayat, feedback — course baru mulai
     * kosong dari sisi peserta.
     */
    public function duplicateCourse(LmsCourses $course): LmsCourses
    {
        return DB::transaction(function () use ($course) {
            $newCourse = $course->replicate();
            $newCourse->code = $course->code . '-' . now()->format('mdHis');
            $newCourse->title = $course->title . ' (Salinan)';
            $newCourse->created_by = Auth::id();
            $newCourse->save();

            foreach ($course->competencies as $competency) {
                $newCourse->competencies()->attach($competency->id);
            }

            foreach ($course->meetings()->with('materials', 'quizzes')->get() as $meeting) {
                $newMeeting = $meeting->replicate();
                $newMeeting->course_id = $newCourse->id;
                $newMeeting->save();

                foreach ($meeting->materials as $material) {
                    $newMaterial = $material->replicate();
                    $newMaterial->meeting_id = $newMeeting->id;
                    $newMaterial->save();
                }

                foreach ($meeting->quizzes as $meetingQuiz) {
                    $newMeetingQuiz = $meetingQuiz->replicate();
                    $newMeetingQuiz->meeting_id = $newMeeting->id;
                    $newMeetingQuiz->save();
                }
            }

            return $newCourse->fresh();
        });
    }

    /**
     * Course belum ada peserta terdaftar saat publish (peserta self-enroll lewat API), jadi
     * yang dikabari adalah semua karyawan aktif — dibatasi ke perusahaan course kalau
     * company_id-nya diisi.
     */
    public function notifyCoursePublished(LmsCourses $course): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->when($course->company_id, fn ($query) => $query->whereCompany($course->company_id))
            ->get();

        Notification::send($recipients, new CoursePublished($course));
    }

    /**
     * Ke peserta yang sudah DITAMBAHKAN admin (LmsCourseUsers, jalur portal
     * LmsCourseDetailPage::addParticipants() - status approved langsung, bukan self-enroll) saat
     * pelatihannya dipublish - beda dari notifyCoursePublished() yang broadcast ke semua karyawan
     * aktif tanpa peduli status keanggotaan (dipakai jalur admin Filament yang belum ada peserta
     * terdaftar saat publish di sana).
     */
    public function notifyParticipantsEnrolled(LmsCourses $course): void
    {
        $recipients = User::query()
            ->whereIn('id', $course->courseUsers()->pluck('user_id'))
            ->get();

        Notification::send($recipients, new CourseParticipantEnrolled($course));
    }
}
