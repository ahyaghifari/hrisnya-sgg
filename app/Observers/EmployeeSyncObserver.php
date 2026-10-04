<?php

namespace App\Observers;

use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use App\Services\Identity\EmployeeSyncService;
use Illuminate\Database\Eloquent\Model;

/**
 * Menyalin ke employees (+ identitas, alamat, lampiran, riwayat karir) tiap User, UserProfiles,
 * UserProfileDocument, atau UserJobPositions berubah. Kegagalan hanya dilaporkan ke log - menyimpan
 * data asli tidak boleh ikut gagal gara-gara tabel salinan (identity:verify yang menangkap selisihnya).
 *
 * Cuma menangkap save/delete lewat model. Update/hapus massal TIDAK memicu event - mis.
 * `$user->profile()->update()` lewat relasi (UserRepository::updateProfileUser memanggil sinkron
 * sendiri), `$user->jobPositions()->where(...)->delete()` di EditKaryawan/CreateKaryawan, dan
 * `->update()` di UserTransitionService; selisihnya dirapikan `identity:backfill` dan dilaporkan
 * `identity:verify`.
 */
class EmployeeSyncObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof UserProfiles || $model instanceof UserProfileDocument || $model instanceof UserJobPositions) {
            $this->sync($model->user_id);

            return;
        }

        // User: hanya kolom yang disalin ke employees.
        if ($model instanceof User && ($model->wasRecentlyCreated || $model->wasChanged(['name', 'nip', 'company_id', 'is_active']))) {
            $this->sync($model->id);
        }
    }

    /** Soft delete User = nonaktif; hapus baris ledger jabatan/lampiran = salinannya ikut hilang. */
    public function deleted(Model $model): void
    {
        $this->sync($model instanceof User ? $model->id : $model->user_id);
    }

    public function restored(Model $model): void
    {
        if ($model instanceof User) {
            $this->sync($model->id);
        }
    }

    private function sync(?string $userId): void
    {
        if ($userId) {
            app(EmployeeSyncService::class)->syncUserIdQuietly($userId);
        }
    }
}
