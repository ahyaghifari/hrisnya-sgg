<?php

namespace Tests\Feature\Rekrutmen;

use App\Models\Rekrutmen\InterviewInvitee;
use App\Models\Rekrutmen\InterviewSchedule;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use PHPUnit\Framework\TestCase;

/**
 * Wawancara berlapis (keputusan user 2026-09-28): 1 pelamar bisa diwawancara 2 kali, jumlah tahapnya
 * diatur per lowongan, tiap tahap punya nama bebas dan hasilnya sendiri.
 *
 * ponytail: tanpa database. Tabel-tabelnya ada di koneksi `recruitment` (MySQL), sementara test suite
 * project ini pakai sqlite in-memory - jadi yang diuji logika penentuan tahap & label di model, dengan
 * relasi dipasang manual. Percabangan status (lulus tahap 1 -> tetap `interview`, lulus tahap akhir ->
 * `offering`) ada di WawancaraPage::tandaiHasilWawancaraAction().
 */
class InterviewRoundsTest extends TestCase
{
    private function invitee(int $round, ?string $result, ?string $name = null): InterviewInvitee
    {
        $schedule = new InterviewSchedule(['round' => $round, 'name' => $name]);
        $invitee = new InterviewInvitee(['result' => $result]);
        $invitee->setRelation('schedule', $schedule);

        return $invitee;
    }

    private function application(array $invitees): JobApplication
    {
        $app = new JobApplication();
        $app->setRelation('interviewInvitees', collect($invitees));

        return $app;
    }

    public function test_the_stage_label_falls_back_to_the_round_number(): void
    {
        $this->assertSame('Wawancara Tahap 2', (new InterviewSchedule(['round' => 2]))->label());
        $this->assertSame('Wawancara User', (new InterviewSchedule(['round' => 2, 'name' => 'Wawancara User']))->label());
    }

    public function test_an_invitee_knows_which_round_it_belongs_to(): void
    {
        $this->assertSame(2, $this->invitee(2, null)->round());

        $tanpaJadwal = new InterviewInvitee();
        $tanpaJadwal->setRelation('schedule', null);
        $this->assertSame(1, $tanpaJadwal->round(), 'Tanpa jadwal dianggap tahap 1.');
    }

    public function test_the_pending_round_is_the_earliest_one_without_a_result(): void
    {
        $app = $this->application([
            $this->invitee(1, 'lulus'),
            $this->invitee(2, null),
        ]);

        $this->assertSame(2, $app->pendingInterviewRound());
    }

    public function test_there_is_no_pending_round_once_every_stage_is_decided(): void
    {
        $app = $this->application([
            $this->invitee(1, 'lulus'),
            $this->invitee(2, 'tidak_lulus'),
        ]);

        $this->assertNull($app->pendingInterviewRound());
    }

    /** Urutan baris tidak boleh memengaruhi tahap mana yang dianggap menunggu. */
    public function test_the_pending_round_ignores_row_order(): void
    {
        $app = $this->application([
            $this->invitee(2, null),
            $this->invitee(1, null),
        ]);

        $this->assertSame(1, $app->pendingInterviewRound());
    }

    public function test_an_invitee_can_be_looked_up_per_round(): void
    {
        $app = $this->application([
            $this->invitee(1, 'lulus'),
            $this->invitee(2, null),
        ]);

        $this->assertSame('lulus', $app->interviewInviteeForRound(1)?->result);
        $this->assertNull($app->interviewInviteeForRound(2)?->result);
        $this->assertNull($app->interviewInviteeForRound(3), 'Tahap yang belum pernah dijadwalkan.');
    }

    /** Lowongan lama tanpa setelan tahap tetap dianggap 1 tahap. */
    public function test_an_opening_defaults_to_a_single_round(): void
    {
        $this->assertSame(1, (new JobOpening())->interview_rounds ?: 1);
        $this->assertSame(2, (new JobOpening(['interview_rounds' => 2]))->interview_rounds);
    }
}
