-- Hapus 3 course "hantu" (start_date NULL, tapi published sehingga tetap tampil di halaman Daftar Pelatihan)
-- Scope: HANYA 3 course ini beserta data anaknya. Tabel master (kategori, bank soal, dsb) tidak disentuh.
--
-- lms_course_users & lms_course_meeting_users punya FK ON DELETE SET NULL ke lms_courses,
-- jadi baris pesertanya TIDAK ikut terhapus otomatis kalau cuma DELETE FROM lms_courses.
-- Makanya dihapus manual duluan di bawah. Sisanya (lms_meetings, lms_course_competencies,
-- lms_course_feedbacks(+answers), lms_respondents(+quiz_answers/results), trainer_assessment_results,
-- lms_materials, lms_meeting_attendances, lms_meeting_honorarium_assessments, lms_meeting_quizzes)
-- semuanya ON DELETE CASCADE dari lms_courses/lms_meetings, jadi otomatis ikut hilang.

SET @course_ids = '01keds6p89mearqkdsps7cxtjd,01kedse28nhe2a2zx45yfp9z0v,01kegasr5f8d8axgc71wt8hg8j';

START TRANSACTION;

DELETE FROM lms_course_meeting_users
WHERE course_id IN ('01keds6p89mearqkdsps7cxtjd','01kedse28nhe2a2zx45yfp9z0v','01kegasr5f8d8axgc71wt8hg8j');

DELETE FROM lms_course_users
WHERE course_id IN ('01keds6p89mearqkdsps7cxtjd','01kedse28nhe2a2zx45yfp9z0v','01kegasr5f8d8axgc71wt8hg8j');

DELETE FROM lms_courses
WHERE id IN ('01keds6p89mearqkdsps7cxtjd','01kedse28nhe2a2zx45yfp9z0v','01kegasr5f8d8axgc71wt8hg8j');

-- Cek dulu hasilnya sebelum COMMIT. Kalau sudah sesuai:
COMMIT;
-- Kalau ada yang aneh, ganti COMMIT di atas jadi ROLLBACK;
