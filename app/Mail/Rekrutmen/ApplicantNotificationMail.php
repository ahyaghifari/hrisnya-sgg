<?php

namespace App\Mail\Rekrutmen;

use App\Models\Rekrutmen\ApplicationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email pemberitahuan ke pelamar. Port dari App\Mail\ApplicantNotificationMail project
 * `rekrutmen`. Dikirim sinkron dari aksi admin panel /rekrutmen (bukan ShouldQueue) —
 * volumenya kecil & dipicu manual; kalau bulk penolakan jadi berat, tinggal implement
 * ShouldQueue + koneksi queue khusus (contoh: SendPayrollSlipJob).
 */
class ApplicantNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ApplicationNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.rekrutmen.applicant-notification');
    }
}
