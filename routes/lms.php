<?php

use App\Livewire\LMS\BankSoalPage;
use App\Livewire\LMS\CreateBankSoal;
use App\Livewire\LMS\CreateLmsCourse;
use App\Livewire\LMS\CreateQuiz;
use App\Livewire\LMS\ExternalTrainingDetailPage;
use App\Livewire\LMS\ExternalTrainingsPage;
use App\Livewire\LMS\LmsCategoriesPage;
use App\Livewire\LMS\LmsCourseBaselinesPage;
use App\Livewire\LMS\LmsCourseDetailPage;
use App\Livewire\LMS\LmsCoursesPage;
use App\Livewire\LMS\LmsFeedbackQuestionsPage;
use App\Livewire\LMS\LmsSettingsPage;
use App\Livewire\LMS\MasterHonorariumPage;
use App\Livewire\LMS\QuizzesPage;
use App\Livewire\LMS\TrainerDetailPage;
use App\Livewire\LMS\TrainersPage;
use App\Livewire\LMS\TrainingReportPage;
use App\Livewire\Pelatihan\CourseFeedback;
use App\Livewire\Pelatihan\MyTrainingRequests;
use App\Livewire\Pelatihan\QuizAttempt;
use App\Livewire\Pelatihan\SubmitTrainingRequest;
use App\Livewire\Pelatihan\TrainingDetail;
use App\Livewire\Pelatihan\TrainingList;
use App\Livewire\Pelatihan\TrainingRequestDetail;
use Illuminate\Support\Facades\Route;

/**
 * Route modul LMS (Learning Management System) & Pelatihan. Dipisah dari routes/web.php
 * (2026-09-04) - lihat komentar di routes/sikartap.php untuk konvensi split per-modul.
 * Route name masih prefix "pelatihan.*" untuk sebagian besar (nama lama, belum di-rename).
 */
Route::get('/pelatihan', TrainingList::class)
    ->name('pelatihan');

// Didaftarkan sebelum wildcard /pelatihan/{course} di bawah — kalau kebalik, "quiz"/"umpan-balik"
// akan ditangkap sebagai {course} dan gagal resolve (404) sebelum sempat sampai ke route ini.
Route::get('/pelatihan/quiz/{meetingQuiz}', QuizAttempt::class)
    ->name('pelatihan.quiz');

Route::get('/pelatihan/umpan-balik/{course}', CourseFeedback::class)
    ->name('pelatihan.feedback');

Route::get('/pelatihan/pengajuan-saya', MyTrainingRequests::class)
    ->name('pelatihan.pengajuan-saya');

Route::get('/pelatihan/pengajuan-saya/{record}', TrainingRequestDetail::class)
    ->name('pelatihan.pengajuan-saya.show');

Route::get('/pelatihan/pengajuan', SubmitTrainingRequest::class)
    ->name('pelatihan.pengajuan');

Route::get('/lms/honorarium', MasterHonorariumPage::class)
    ->name('pelatihan.honorarium');

Route::get('/lms/pelatihan-eksternal', ExternalTrainingsPage::class)
    ->name('pelatihan.eksternal');

Route::get('/lms/pelatihan-eksternal/{record}', ExternalTrainingDetailPage::class)
    ->name('pelatihan.eksternal.show');

Route::get('/lms/evaluasi', LmsFeedbackQuestionsPage::class)
    ->name('pelatihan.evaluasi');

Route::get('/lms/pengaturan', LmsSettingsPage::class)
    ->name('pelatihan.pengaturan');

Route::get('/lms/kategori', LmsCategoriesPage::class)
    ->name('pelatihan.kategori');

Route::get('/lms/baseline', LmsCourseBaselinesPage::class)
    ->name('pelatihan.baseline');

Route::get('/lms/laporan', TrainingReportPage::class)
    ->name('pelatihan.laporan');

// Bank Soal — padanan portal dari Filament QuestionBanksResource (/admin/lms/question-banks).
// "tambah" harus didaftarkan SEBELUM wildcard /lms/bank-soal/{soal} kalau nanti ditambah.
Route::get('/lms/bank-soal', BankSoalPage::class)
    ->name('pelatihan.bank-soal');

Route::get('/lms/bank-soal/tambah', CreateBankSoal::class)
    ->name('pelatihan.bank-soal.create');

// Kuis — padanan portal dari Filament QuizzesResource (/admin/lms/quizzes).
// "tambah" harus didaftarkan SEBELUM wildcard /lms/kuis/{kuis} kalau nanti ditambah.
Route::get('/lms/kuis', QuizzesPage::class)
    ->name('pelatihan.kuis');

Route::get('/lms/kuis/tambah', CreateQuiz::class)
    ->name('pelatihan.kuis.create');

Route::get('/lms/kuis/{quiz}/ubah', CreateQuiz::class)
    ->name('pelatihan.kuis.edit');

Route::get('/lms/pemateri', TrainersPage::class)
    ->name('pelatihan.pemateri');

Route::get('/lms/pemateri/{trainer}', TrainerDetailPage::class)
    ->name('pelatihan.pemateri.show');

Route::get('/lms/pelatihan', LmsCoursesPage::class)
    ->name('pelatihan.kelola');

Route::get('/lms/pelatihan/tambah', CreateLmsCourse::class)
    ->name('pelatihan.kelola.create');

Route::get('/lms/pelatihan/{course}', LmsCourseDetailPage::class)
    ->name('pelatihan.kelola.show');

Route::get('/pelatihan/{course}', TrainingDetail::class)
    ->name('pelatihan.show');
