<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan keamanan terkait blokir tanda tangan (terkunci / dibuka / perlu dibuka petugas).
 * Hanya kanal database, dan tidak bisa dimatikan lewat preferensi notifikasi: pemilik akun harus
 * tahu kalau akunnya terkunci atau ada yang membuka blokirnya.
 */
class SigningLockChanged extends Notification
{
    public function __construct(private string $title, private string $body) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'created_at' => now()->toISOString(),
        ];
    }
}
