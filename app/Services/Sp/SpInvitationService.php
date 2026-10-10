<?php

namespace App\Services\Sp;

use App\Enum\CustomPermissionEnum;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationUpdated;
use App\Services\SikartapReviewerResolver;
use Illuminate\Validation\ValidationException;

/**
 * Undangan klarifikasi (tahap 1 alur SP): karyawan + atasan langsungnya diundang pada waktu &
 * tempat tertentu. Tanpa dokumen TTD - cukup record dan notifikasi. Berita Acara baru boleh dibuat
 * dari undangan yang masih terbuka (lihat SpReportService::create).
 */
class SpInvitationService
{
    /** @param  array{user_id:string, scheduled_at:string, location:string, note?:?string}  $data */
    public function create(User $creator, array $data): Invitation
    {
        $this->assertCanManage($creator);
        $this->requireFilled($data['user_id'] ?? null, 'user_id');

        $employee = User::whereCompany($creator->company_id)->findOrFail($data['user_id']);
        $supervisor = app(SikartapReviewerResolver::class)->directSupervisorOf($employee);

        $invitation = Invitation::create([
            'type' => \App\Enum\InvitationType::SP_KLARIFIKASI,
            'company_id' => $creator->company_id,
            'user_id' => $employee->id,
            'supervisor_id' => $supervisor && $supervisor->id !== $employee->id ? $supervisor->id : null,
            'created_by' => $creator->id,
        ] + $this->schedule($data));

        $this->notify($invitation, 'Undangan Klarifikasi', 'diundang klarifikasi');

        return $invitation;
    }

    /** Ubah jadwal/tempat/catatan; karyawan & atasan dikabari. */
    public function update(Invitation $invitation, User $actor, array $data): Invitation
    {
        $this->assertOpen($invitation, $actor);

        $invitation->update($this->schedule($data));
        $this->notify($invitation, 'Undangan Klarifikasi Diubah', 'jadwal undangan klarifikasi diubah');

        return $invitation;
    }

    public function cancel(Invitation $invitation, User $actor, string $reason): Invitation
    {
        $this->assertOpen($invitation, $actor);
        $this->requireFilled($reason, 'alasan');

        $invitation->update(['cancelled_at' => now(), 'cancelled_reason' => $reason]);
        $this->notify($invitation, 'Undangan Klarifikasi Dibatalkan', "undangan klarifikasi dibatalkan: {$reason}");

        return $invitation;
    }

    private function schedule(array $data): array
    {
        foreach (['scheduled_at', 'location'] as $field) {
            $this->requireFilled($data[$field] ?? null, $field);
        }

        return [
            'scheduled_at' => $data['scheduled_at'],
            'location' => $data['location'],
            'note' => $data['note'] ?? null,
        ];
    }

    private function notify(Invitation $invitation, string $title, string $what): void
    {
        $invitation->loadMissing('user');
        $when = $invitation->scheduled_at->translatedFormat('d M Y H:i');
        $topic = $invitation->note ? " Topik: {$invitation->note}." : '';

        // always: undangan resmi, tidak boleh hilang karena pengaturan notifikasi penerima.
        $url = route('undangan.show', $invitation);

        $invitation->user?->notify(new InvitationUpdated($title, "Anda {$what}: {$when}, di {$invitation->location}.{$topic}", $url, $invitation->type));
        $invitation->supervisor?->notify(new InvitationUpdated($title, "{$invitation->user?->name} dan Anda {$what}: {$when}, di {$invitation->location}.{$topic}", $url, $invitation->type));
    }

    private function assertCanManage(User $actor, ?string $companyId = null): void
    {
        abort_unless(
            $actor->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $actor->can('Create:EmployeeSp')
                && $actor->company_id === ($companyId ?? $actor->company_id),
            403,
            'Anda tidak berwenang mengelola undangan klarifikasi.'
        );
    }

    private function assertOpen(Invitation $invitation, User $actor): void
    {
        $this->assertCanManage($actor, $invitation->company_id);

        if (! $invitation->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Undangan sudah dibatalkan atau sudah dibuatkan Berita Acara.']);
        }
    }

    private function requireFilled(mixed $value, string $field): void
    {
        if (! filled($value)) {
            throw ValidationException::withMessages([$field => "Kolom {$field} wajib diisi."]);
        }
    }
}
