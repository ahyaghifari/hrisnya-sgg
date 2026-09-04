<?php

namespace Tests\Unit\Rekrutmen;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\CompanyMessageTemplate;
use Tests\TestCase;

/**
 * cta() murni logika (config + match) — tak sentuh DB. Menjaga tombol email menunjuk app
 * pelamar & hilang kalau REKRUTMEN_URL kosong.
 */
class ApplicationNotificationCtaTest extends TestCase
{
    private function notif(string $event): ApplicationNotification
    {
        $n = new ApplicationNotification();
        $n->event = $event;
        $n->job_application_id = 'APP123';

        return $n;
    }

    public function test_no_cta_when_url_blank(): void
    {
        config(['services.rekrutmen.url' => '']);

        $this->assertNull($this->notif(CompanyMessageTemplate::EVENT_QUIZ_SENT)->cta());
    }

    public function test_quiz_and_interview_cta_point_to_applicant_app(): void
    {
        config(['services.rekrutmen.url' => 'https://rekrutmen.test/']);

        $this->assertSame(
            ['url' => 'https://rekrutmen.test/tes-online', 'label' => 'Kerjakan Tes'],
            $this->notif(CompanyMessageTemplate::EVENT_QUIZ_SENT)->cta(),
        );
        $this->assertSame(
            'https://rekrutmen.test/lamaran/APP123',
            $this->notif(CompanyMessageTemplate::EVENT_INTERVIEW_SCHEDULED)->cta()['url'],
        );
    }

    public function test_rejection_event_has_no_cta(): void
    {
        config(['services.rekrutmen.url' => 'https://rekrutmen.test']);

        $this->assertNull($this->notif(CompanyMessageTemplate::EVENT_SCREENING_REJECTED)->cta());
    }
}
