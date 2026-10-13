<?php

namespace App\Services\Document;

use App\Enum\CustomPermissionEnum;
use App\Models\Document\Document;
use App\Models\Document\SigningLock;
use App\Models\Document\SigningLockEvent;
use App\Models\User;
use App\Notifications\SigningLockChanged;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Pembatasan percobaan password saat menandatangani (TTE). Jenjang:
 *  1. MAX_ATTEMPTS password salah beruntun dalam ATTEMPT_WINDOW menit -> blokir sementara
 *     TEMP_LOCK_MINUTES menit, terbuka otomatis;
 *  2. MAX_TEMP_LOCKS blokir sementara dalam TEMP_LOCK_WINDOW_HOURS jam -> blokir keras, hanya
 *     bisa dibuka pemegang permission buka_blokir_tanda_tangan (unlock()), bukan pemilik akun.
 * Blokir hanya untuk fungsi tanda tangan; login tetap bisa. Semua kejadian masuk
 * signing_lock_events (append-only), dan kegagalan juga dicatat di jejak audit dokumennya.
 */
class SigningLockService
{
    public const MAX_ATTEMPTS = 5;

    public const ATTEMPT_WINDOW_MINUTES = 15;

    public const TEMP_LOCK_MINUTES = 15;

    public const MAX_TEMP_LOCKS = 3;

    public const TEMP_LOCK_WINDOW_HOURS = 24;

    /** Pesan kalau $user sedang diblokir, null kalau boleh mencoba. */
    public function blockedMessage(User $user): ?string
    {
        $lock = SigningLock::where('user_id', $user->id)->first();

        if ($lock?->isHardLocked()) {
            return 'Tanda tangan akun Anda diblokir karena password salah berulang kali. Hubungi HC/SDM untuk membuka blokir.';
        }

        if ($lock?->isTempLocked()) {
            return 'Terlalu banyak password salah. Coba lagi pukul ' . $lock->locked_until->format('H:i') . '.';
        }

        return null;
    }

    /**
     * Catat password salah. Mengembalikan pesan untuk ditampilkan ke pengguna (sisa percobaan
     * atau status blokir).
     */
    public function recordFailure(User $user, ?Document $document = null): string
    {
        [$message, $level] = DB::transaction(function () use ($user, $document) {
            $lock = SigningLock::where('user_id', $user->id)->lockForUpdate()->first()
                ?? SigningLock::create(['user_id' => $user->id]);

            $now = now();

            // Hitungan percobaan hanya berlaku dalam jendela waktu; yang lama dianggap kedaluwarsa.
            if ($lock->last_failed_at && $lock->last_failed_at->lt($now->copy()->subMinutes(self::ATTEMPT_WINDOW_MINUTES))) {
                $lock->failed_attempts = 0;
            }

            $lock->failed_attempts++;
            $lock->last_failed_at = $now;
            $this->event($user, 'failed', null, $document, null, ['attempt' => $lock->failed_attempts]);

            if ($lock->failed_attempts < self::MAX_ATTEMPTS) {
                $lock->save();
                $sisa = self::MAX_ATTEMPTS - $lock->failed_attempts;

                return ["Password salah. Sisa {$sisa} percobaan sebelum tanda tangan diblokir sementara.", null];
            }

            // Ambang terlampaui: blokir sementara, atau keras kalau sudah berulang dalam 24 jam.
            $windowStart = $lock->temp_lock_window_started_at;
            if (! $windowStart || $windowStart->lt($now->copy()->subHours(self::TEMP_LOCK_WINDOW_HOURS))) {
                $lock->temp_lock_count = 0;
                $lock->temp_lock_window_started_at = $now;
            }

            $lock->temp_lock_count++;
            $lock->failed_attempts = 0;

            if ($lock->temp_lock_count >= self::MAX_TEMP_LOCKS) {
                $lock->hard_locked_at = $now;
                $lock->locked_until = null;
                $lock->save();
                $this->event($user, 'locked_hard', null, $document, null, ['temp_lock_count' => $lock->temp_lock_count]);

                return ['Tanda tangan akun Anda diblokir. Hubungi HC/SDM untuk membuka blokir.', 'keras'];
            }

            $lock->locked_until = $now->copy()->addMinutes(self::TEMP_LOCK_MINUTES);
            $lock->save();
            $this->event($user, 'locked_temp', null, $document, null, ['until' => $lock->locked_until->toISOString(), 'temp_lock_count' => $lock->temp_lock_count]);

            return ['Terlalu banyak password salah. Tanda tangan diblokir sampai pukul ' . $lock->locked_until->format('H:i') . '.', 'sementara'];
        });

        $this->afterFailure($user, $document, $level);

        return $message;
    }

    /** Password benar: hitungan salah beruntun di-reset (hitungan blokir sementara 24 jam tetap). */
    public function recordSuccess(User $user): void
    {
        SigningLock::where('user_id', $user->id)->update(['failed_attempts' => 0, 'last_failed_at' => null]);
    }

    /**
     * Buka blokir (sementara maupun keras) milik $target oleh $actor. Syarat: $actor punya
     * permission buka_blokir_tanda_tangan, bukan akun yang sama dengan $target, dan satu
     * perusahaan dengan $target (kecuali boleh melihat perusahaan lain). Alasan wajib diisi.
     */
    public function unlock(User $target, User $actor, string $reason): void
    {
        abort_unless($actor->can(CustomPermissionEnum::BUKA_BLOKIR_TANDA_TANGAN->value), 403);
        abort_if($actor->id === $target->id, 403, 'Blokir akun sendiri tidak bisa dibuka sendiri.');
        abort_unless($actor->company_id === $target->company_id || $actor->canFilterByCompany(), 403);

        $reason = trim($reason);
        abort_if($reason === '', 422, 'Alasan wajib diisi.');

        DB::transaction(function () use ($target, $actor, $reason) {
            SigningLock::where('user_id', $target->id)->update([
                'failed_attempts' => 0,
                'last_failed_at' => null,
                'locked_until' => null,
                'temp_lock_count' => 0,
                'temp_lock_window_started_at' => null,
                'hard_locked_at' => null,
            ]);

            $this->event($target, 'unlocked', $actor, null, $reason);
        });

        $target->notify(new SigningLockChanged(
            'Blokir Tanda Tangan Dibuka',
            "Blokir tanda tangan akun Anda dibuka oleh {$actor->name}. Alasan: {$reason}. Jika bukan Anda yang meminta, segera ganti password dan hubungi HC/SDM.",
        ));
    }

    /** Akun yang sedang diblokir (sementara aktif atau keras) di satu perusahaan. */
    public function lockedUsers(?string $companyId)
    {
        return SigningLock::query()
            ->with('user')
            ->where(fn ($q) => $q->whereNotNull('hard_locked_at')->orWhere('locked_until', '>', now()))
            ->whereHas('user', fn ($q) => $companyId ? $q->whereCompany($companyId) : $q)
            ->orderByDesc('hard_locked_at')
            ->get();
    }

    private function event(User $user, string $event, ?User $actor, ?Document $document, ?string $reason, array $meta = []): void
    {
        SigningLockEvent::create([
            'user_id' => $user->id,
            'event' => $event,
            'actor_id' => $actor?->id,
            'document_id' => $document?->id,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'meta' => $meta ?: null,
        ]);
    }

    /** Catat di jejak audit dokumen dan kirim notifikasi; dipanggil sesudah transaksi selesai. */
    private function afterFailure(User $user, ?Document $document, ?string $level): void
    {
        $document?->logEvent('signature_failed', $user, ['reason' => 'password_salah']);

        if ($level === null) {
            return;
        }

        $document?->logEvent('signature_locked', $user, ['level' => $level]);

        if ($level !== 'keras') {
            return;
        }

        $user->notify(new SigningLockChanged(
            'Tanda Tangan Diblokir',
            'Tanda tangan akun Anda diblokir karena password salah berulang kali. Hubungi HC/SDM untuk membuka blokir.',
        ));

        // Petugas yang berwenang membuka blokir (satu perusahaan) diberi tahu.
        if (! Permission::where('name', CustomPermissionEnum::BUKA_BLOKIR_TANDA_TANGAN->value)->exists()) {
            return;
        }

        User::permission(CustomPermissionEnum::BUKA_BLOKIR_TANDA_TANGAN->value)
            ->where('company_id', $user->company_id)
            ->where('id', '!=', $user->id)
            ->get()
            ->each(fn (User $officer) => $officer->notify(new SigningLockChanged(
                'Akun Terblokir Tanda Tangan',
                "Tanda tangan akun {$user->name} diblokir karena password salah berulang. Buka blokir di Pengaturan Kepegawaian setelah memastikan identitasnya.",
            )));
    }
}
