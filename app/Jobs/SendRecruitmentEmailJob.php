<?php

namespace App\Jobs;

use App\Mail\RecruitmentMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRecruitmentEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected readonly string          $recipientEmail,
        protected readonly RecruitmentMail $mail,
    ) {}

    public function handle(): void
    {
        Mail::to($this->recipientEmail)->send($this->mail);
    }
}
