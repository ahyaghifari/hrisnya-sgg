<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Real SMTP send test – verifies that the email is actually delivered
 * using the SMTP credentials configured in .env.
 *
 * To check the received email, open:
 *   https://yopmail.com/en/?login=syifa-smtp-test
 *
 * Run only this test group:
 *   php artisan test --group=smtp
 *
 * NOTE: This test sends real emails. Do NOT run it in CI without
 * a dedicated test SMTP account.
 */
class SendEmailTest extends TestCase
{
    /**
     * Target yopmail inbox – readable at https://yopmail.com/en/?login=syifa-smtp-test
     */
    private const TEST_RECIPIENT = 'syifa-smtp-test@yopmail.com';

    /**
     * Verify that the SMTP mailer is configured before running.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (config('mail.mailers.smtp.host') === '127.0.0.1'
            || empty(config('mail.mailers.smtp.username'))) {
            $this->markTestSkipped(
                'SMTP credentials are not configured in .env. Skipping real-send test.'
            );
        }
    }

    /**
     * Sends a basic test email via SMTP and asserts no exception is thrown.
     * Check inbox: https://yopmail.com/en/?login=syifa-smtp-test
     */
    #[Test]
    #[Group('smtp')]
    public function it_sends_a_test_email_via_smtp(): void
    {
        // Use the real SMTP transport (NOT Mail::fake())
        $mailer = Mail::mailer('smtp');

        $exception = null;

        try {
            $mailer->to(self::TEST_RECIPIENT)
                   ->send(new TestMail(
                       recipientName: 'Yopmail Tester',
                       body: 'Ini adalah email pengujian yang dikirimkan oleh PHPUnit melalui SMTP. '
                           . 'Jika Anda menerima email ini, konfigurasi SMTP sudah benar.'
                   ));
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull(
            $exception,
            "Email gagal terkirim. Error: " . ($exception?->getMessage() ?? '')
        );
    }

    /**
     * Verifies mailable metadata (subject, class) and sends via SMTP.
     * Check inbox: https://yopmail.com/en/?login=syifa-smtp-test
     */
    #[Test]
    #[Group('smtp')]
    public function it_sends_email_with_correct_from_address(): void
    {
        $mailable = new TestMail(
            recipientName: 'Header Check',
            body: 'Verifikasi header From / Subject melalui SMTP.'
        );

        // Assert mailable properties before sending
        $this->assertInstanceOf(\Illuminate\Mail\Mailable::class, $mailable);
        $this->assertEquals('Test Email – SMTP Verification', $mailable->envelope()->subject);
        $this->assertEquals('Header Check', $mailable->recipientName);

        // Send via real SMTP and verify no exception
        $exception = null;
        try {
            Mail::mailer('smtp')
                ->to(self::TEST_RECIPIENT)
                ->send($mailable);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull(
            $exception,
            "Pengiriman SMTP gagal: " . ($exception?->getMessage() ?? '')
        );
    }

    /**
     * Sends one email to two separate yopmail inboxes simultaneously.
     * Check inbox 1: https://yopmail.com/en/?login=syifa-smtp-test
     * Check inbox 2: https://yopmail.com/en/?login=syifa-smtp-test2
     */
    #[Test]
    #[Group('smtp')]
    public function it_sends_email_to_multiple_recipients(): void
    {
        $recipients = [
            self::TEST_RECIPIENT,
            'syifa-smtp-test2@yopmail.com',
        ];

        $exception = null;

        try {
            Mail::mailer('smtp')
                ->to($recipients)
                ->send(new TestMail(
                    recipientName: 'Multiple Recipients',
                    body: 'Email ini dikirim ke beberapa penerima sekaligus.'
                ));
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull(
            $exception,
            "Pengiriman ke banyak penerima gagal: " . ($exception?->getMessage() ?? '')
        );
    }
}
