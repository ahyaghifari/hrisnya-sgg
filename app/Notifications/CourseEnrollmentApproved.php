<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\LMS\LmsCourses;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CourseEnrollmentApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected LmsCourses $course) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::COURSE_ENROLLMENT_APPROVED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Pendaftaran Pelatihan Disetujui';
    }

    protected function body(): string
    {
        return "Pendaftaran Anda untuk pelatihan \"{$this->course->title}\" sudah disetujui.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'course_id' => $this->course->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['course_id' => $this->course->id])
            ->action('Lihat', 'view');
    }
}
