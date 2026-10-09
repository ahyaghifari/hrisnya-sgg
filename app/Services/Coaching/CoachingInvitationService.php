<?php

namespace App\Services\Coaching;

use App\Enum\CustomPermissionEnum;
use App\Enum\InvitationType;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use App\Services\OrgHierarchyService;
use App\Services\SikartapReviewerResolver;
use Illuminate\Validation\ValidationException;

/**
 * Undangan coaching: coach mengundang coachee pada waktu & tempat tertentu (tabel `invitations` generik,
 * type COACHING). Coachee diundang; atasan langsungnya ikut diundang hanya kalau dipilih (with_supervisor). Cakupan karyawan sama dengan
 * pembuatan Coaching: akses penuh (Create:Coaching / Coaching Karyawan Lainnya) = semua karyawan company;
 * selain itu hanya bawahannya.
 */
class CoachingInvitationService
{
    public function canCreate(User $user): bool
    {
        return $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value)
            && ($this->hasFullAccess($user) || $user->canAccessKaryawanMenu());
    }

    public function hasFullAccess(User $user): bool
    {
        return $user->can('Create:Coaching') || $user->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value);
    }

    /** @return \Illuminate\Support\Collection<string, string> id => nama, coachee yang boleh diundang user ini */
    public function coacheeOptions(User $user)
    {
        $query = User::whereCompany($user->company_id)->whereKeyNot($user->id);

        if (! $this->hasFullAccess($user)) {
            $query->whereIn('id', app(OrgHierarchyService::class)->subordinates($user)->pluck('id'));
        }

        return $query->orderByName()->get()->pluck('name', 'id');
    }

    /** Atasan langsung coachee kalau diminta; null kalau tidak ada, atau dia sama dengan coach/coachee. */
    private function supervisorIdFor(User $coachee, User $coach, bool $with): ?string
    {
        if (! $with) {
            return null;
        }

        $supervisor = app(SikartapReviewerResolver::class)->directSupervisorOf($coachee);

        return $supervisor && ! in_array($supervisor->id, [$coachee->id, $coach->id], true) ? $supervisor->id : null;
    }

    /** @param  array{user_id:string, scheduled_at:string, location:string, note?:?string, with_supervisor?:bool}  $data */
    public function create(User $creator, array $data): Invitation
    {
        abort_unless($this->canCreate($creator), 403, 'Anda tidak berwenang membuat undangan coaching.');
        $this->requireFilled($data['user_id'] ?? null, 'user_id');
        abort_unless($this->coacheeOptions($creator)->has($data['user_id']), 403, 'Karyawan di luar cakupan Anda.');

        $invitation = Invitation::create([
            'type' => InvitationType::COACHING,
            'company_id' => $creator->company_id,
            'user_id' => $data['user_id'],
            'supervisor_id' => $this->supervisorIdFor(User::findOrFail($data['user_id']), $creator, (bool) ($data['with_supervisor'] ?? false)),
            'created_by' => $creator->id,
        ] + $this->schedule($data));

        $this->notify($invitation, 'Undangan Coaching', 'diundang coaching');

        return $invitation;
    }

    public function update(Invitation $invitation, User $actor, array $data): Invitation
    {
        $this->assertManage($invitation, $actor);

        $update = $this->schedule($data);
        if (array_key_exists('with_supervisor', $data)) {
            $update['supervisor_id'] = $this->supervisorIdFor($invitation->user, $actor, (bool) $data['with_supervisor']);
        }

        $invitation->update($update);
        $this->notify($invitation->refresh(), 'Undangan Coaching Diubah', 'jadwal undangan coaching diubah');

        return $invitation;
    }

    public function cancel(Invitation $invitation, User $actor, string $reason): Invitation
    {
        $this->assertManage($invitation, $actor);
        $this->requireFilled($reason, 'alasan');

        $invitation->update(['cancelled_at' => now(), 'cancelled_reason' => $reason]);
        $this->notify($invitation, 'Undangan Coaching Dibatalkan', "undangan coaching dibatalkan: {$reason}");

        return $invitation;
    }

    /** Pembuat undangan (atau pemegang akses penuh di company yang sama) boleh mengubah/membatalkan selama belum dibatalkan. */
    public function canManage(Invitation $invitation, User $actor): bool
    {
        return $invitation->type === InvitationType::COACHING
            && $invitation->isOpen()
            && ($invitation->created_by === $actor->id
                || ($this->hasFullAccess($actor) && $actor->company_id === $invitation->company_id));
    }

    private function assertManage(Invitation $invitation, User $actor): void
    {
        abort_unless($this->canManage($invitation, $actor), 403, 'Anda tidak berwenang mengelola undangan ini.');
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
        $invitation->loadMissing(['user', 'createdBy', 'supervisor']);
        $when = $invitation->scheduled_at->translatedFormat('d M Y H:i');
        $topic = $invitation->note ? " Topik: {$invitation->note}." : '';

        // always: undangan resmi, tidak boleh hilang karena pengaturan notifikasi penerima.
        $url = route('undangan.show', $invitation);

        $invitation->user?->notify(new SpReportUpdated(
            $title,
            "Anda {$what} oleh {$invitation->createdBy?->name}: {$when}, di {$invitation->location}.{$topic}",
            $url,
            always: true,
        ));

        $invitation->supervisor?->notify(new SpReportUpdated(
            $title,
            "{$invitation->user?->name} dan Anda {$what} oleh {$invitation->createdBy?->name}: {$when}, di {$invitation->location}.{$topic}",
            $url,
            always: true,
        ));
    }

    private function requireFilled(mixed $value, string $field): void
    {
        if (! filled($value)) {
            throw ValidationException::withMessages([$field => "Kolom {$field} wajib diisi."]);
        }
    }
}