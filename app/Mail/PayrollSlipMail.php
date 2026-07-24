<?php

namespace App\Mail;

use App\Models\Payroll\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollSlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Payroll $payroll,
        public readonly string $pdfBinary,
        public readonly string $pdfFilename,
        public readonly string $senderName,
        public readonly ?string $senderPosition,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Slip Gaji Bulan ' . $this->payroll->payrollPeriod->end_date->translatedFormat('F Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payroll-slip');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
