<?php

namespace App\Services\Notification;

use App\Enum\NotificationTypeEnum;
use App\Models\User;
use App\Models\UserNotificationPreference;

class NotificationPreferenceService
{
    /**
     * Tidak ada baris = default enabled (opt-out) - supaya user yang belum pernah buka
     * halaman Pengaturan Notifikasi tetap dapat notifikasi seperti biasa.
     */
    public function isEnabled(User $user, NotificationTypeEnum $type): bool
    {
        $preference = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('notification_type', $type->value)
            ->first();

        return $preference?->enabled ?? true;
    }

    public function setEnabled(User $user, NotificationTypeEnum $type, bool $enabled): void
    {
        UserNotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'notification_type' => $type->value],
            ['enabled' => $enabled]
        );
    }
}
