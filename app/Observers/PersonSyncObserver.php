<?php

namespace App\Observers;

use App\Models\User;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use App\Services\Identity\PersonSyncService;
use Illuminate\Database\Eloquent\Model;

/**
 * Menyalin ke persons tiap UserProfiles/User disimpan, jadi tidak perlu diubah di tiap halaman
 * yang menulis profil. Kegagalan salin HANYA dilaporkan ke log - menyimpan profil tidak boleh
 * ikut gagal gara-gara tabel salinan (identity:verify yang menangkap selisihnya).
 *
 * Catatan: cuma menangkap save lewat model. Update massal (`$user->profile()->update()` lewat
 * relasi, `Model::where()->update()`) TIDAK memicu event - UserRepository::updateProfileUser
 * memanggil PersonSyncService::syncUserIdQuietly() sendiri untuk jalur itu.
 */
class PersonSyncObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof UserProfiles || $model instanceof UserProfileDocument) {
            $this->sync($model->user_id);

            return;
        }

        // User: salin cuma saat baru dibuat / nama berubah (nama_lengkap diambil dari users.name).
        if ($model instanceof User && ($model->wasRecentlyCreated || $model->wasChanged('name'))) {
            $this->sync($model->id);
        }
    }

    /** Lampiran dihapus -> salinannya ikut dibuang (UserProfiles dihapus tidak menghapus person). */
    public function deleted(Model $model): void
    {
        if ($model instanceof UserProfileDocument) {
            $this->sync($model->user_id);
        }
    }

    private function sync(string $userId): void
    {
        app(PersonSyncService::class)->syncUserIdQuietly($userId);
    }
}
