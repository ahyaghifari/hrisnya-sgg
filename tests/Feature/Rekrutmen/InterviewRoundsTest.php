<?php

namespace Tests\Feature\Rekrutmen;

use App\Models\Rekrutmen\InterviewInvitee;
use App\Models\Rekrutmen\InterviewSchedule;
use App\Models\Rekrutmen\JobApplication;
use PHPUnit\Framework\TestCase;

/**
 * Wawancara boleh dijadwalkan lebih dari sekali untuk 1 pelamar, tanpa batas jumlah sesi. TAPI
 * keputusan lanjut/tidak BUKAN per sesi (koreksi arah, keputusan user 2026-09-30): dia menempel di
 * job_applications.interview_result + status. Selama hasilnya belum dicatat, pelamarnya tetap di
 * tahap wawancara dan boleh dijadwalkan lagi.
 *
 * Sesi cuma penanda & nama ("Wawancara HR", "Wawancara User").
 *
 * ponytail: tanpa database. Tabelnya di koneksi `recruitment` (MySQL) sementara suite ini sqlite
 * in-memory. Penyaringan siapa yang boleh diundang & percabangan keputusannya ada di WawancaraPage.
 */
class InterviewRoundsTest extends TestCase
{
    private function invitee(int $round, ?string $name = null): InterviewInvitee
    {
        $invitee = new InterviewInvitee();
        $invitee->setRelation('schedule', new InterviewSchedule(['round' => $round, 'name' => $name]));

        return $invitee;
    }

    private function application(array $invitees, ?string $result = null): JobApplication
    {
        $app = new JobApplication(['interview_result' => $result]);
        $app->setRelation('interviewInvitees', collect($invitees));

        return $app;
    }

    public function test_the_session_label_falls_back_to_the_round_number(): void
    {
        $this->assertSame('Wawancara Tahap 2', (new InterviewSchedule(['round' => 2]))->label());
        $this->assertSame('Wawancara User', (new InterviewSchedule(['round' => 2, 'name' => 'Wawancara User']))->label());
    }

    public function test_an_invitee_knows_which_session_it_belongs_to(): void
    {
        $this->assertSame(3, $this->invitee(3)->round());

        $tanpaJadwal = new InterviewInvitee();
        $tanpaJadwal->setRelation('schedule', null);
        $this->assertSame(1, $tanpaJadwal->round(), 'Tanpa jadwal dianggap sesi 1.');
    }

    /** Satu pelamar boleh punya banyak sesi - tidak dibatasi dua. */
    public function test_an_applicant_can_hold_any_number_of_sessions(): void
    {
        $app = $this->application([$this->invitee(1), $this->invitee(2), $this->invitee(3)]);

        $this->assertCount(3, $app->interviewInvitees);
        $this->assertSame('Wawancara Tahap 3', $app->interviewInviteeForRound(3)->schedule->label());
        $this->assertNull($app->interviewInviteeForRound(4), 'Sesi yang belum pernah dijadwalkan.');
    }

    /**
     * Inti koreksinya: banyaknya sesi tidak memutuskan apa pun. Pelamar dengan tiga sesi tetap
     * "belum diputuskan" selama interview_result-nya kosong.
     */
    public function test_sessions_do_not_decide_anything(): void
    {
        $belum = $this->application([$this->invitee(1), $this->invitee(2), $this->invitee(3)]);
        $this->assertNull($belum->interview_result, 'Berapa pun sesinya, keputusan tetap dari statusnya.');

        $sudah = $this->application([$this->invitee(1)], 'lulus');
        $this->assertSame('lulus', $sudah->interview_result, 'Satu sesi pun cukup kalau sudah diputuskan.');
    }

    /** Hasil tidak lagi disimpan di undangan sesi - kolomnya sudah dilepas dari tabel. */
    public function test_the_result_no_longer_lives_on_the_session_invitee(): void
    {
        $this->assertNotContains('result', (new InterviewInvitee())->getFillable());
    }
}
