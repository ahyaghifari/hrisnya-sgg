<?php

namespace App\Mail;

use App\Models\Payroll\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        /** From khusus akun email perusahaan (payroll_mail_settings). Null = pakai default config('mail.from'). */
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress ? new Address($this->fromAddress, $this->fromName ?? $this->fromAddress) : null,
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
