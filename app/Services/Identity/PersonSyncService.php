<?php

namespace App\Services\Identity;

use App\Models\Person\Person;
use App\Models\Person\PersonDocument;
use App\Models\User;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin users + user_profiles ke persons / person_identities / person_addresses
 * (roadmap_migrasi_bertahap_identity_hris.md). SATU ARAH: tabel lama tetap sumber & diedit
 * seperti biasa, tabel baru cuma salinan. Dipakai bersama oleh observer model (tiap edit) dan
 * command identity:backfill supaya tidak ada dua logika salin. Idempotent - dipanggil berkali-kali
 * dengan data sama tidak menghasilkan baris ganda dan tidak mengubah updated_at.
 */
class PersonSyncService
{
    /** Seperti syncUserId() tapi kegagalan cuma dilaporkan ke log - dipakai jalur simpan profil
     * supaya tabel salinan tidak pernah bisa menggagalkan penyimpanan data asli. */
    public function syncUserIdQuietly(string $userId): void
    {
        try {
            $this->syncUserId($userId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function syncUserId(string $userId): ?Person
    {
        // Tanpa global scope: akun non-karyawan (NON_KARYAWAN_IDS) & soft-deleted tetap orang.
        $user = User::withoutGlobalScopes()->withTrashed()->find($userId);

        return $user ? $this->syncUser($user) : null;
    }

    public function syncUser(User $user): Person
    {
        return DB::transaction(function () use ($user) {
            $profile = UserProfiles::where('user_id', $user->id)->first();

            $person = Person::updateOrCreate(['user_id' => $user->id], [
                'nama_lengkap' => $user->name,
                'nickname' => $profile?->nickname,
                'gender' => $profile?->gender,
                'marital_status' => $profile?->marital_status,
                'place_of_birth' => $profile?->place_of_birth,
                'date_of_birth' => $profile?->date_of_birth,
                'religion_id' => $profile?->religion_id,
                'profile_picture' => $profile?->profile_picture,
                'phone' => $profile?->phone,
                'whatsapp' => $profile?->whatsapp,
                'email_pribadi' => $profile?->email_pribadi,
            ]);

            // Tautan akun -> orang. Query langsung (bukan $user->save()) supaya tidak memicu
            // observer lagi dan tidak membuat putaran; hanya menulis kalau memang berbeda.
            DB::table('users')->where('id', $user->id)
                ->where(fn ($q) => $q->whereNull('person_id')->orWhere('person_id', '!=', $person->id))
                ->update(['person_id' => $person->id]);

            // Lampiran tidak bergantung pada profil (upload tidak mensyaratkannya).
            $this->syncDocuments($person);

            // Orang tanpa profil: cukup baris persons (nama), identitas & alamat tidak dibuat.
            if (! $profile) {
                return $person;
            }

            $person->identity()->updateOrCreate([], $profile->only([
                'no_nik', 'no_kk', 'npwp', 'no_bpjs_kes', 'no_bpjs_ket',
                'bank_name', 'bank_account_number', 'bank_account_name',
            ]));

            // type 'utama': form HRIS belum membedakan alamat KTP dan domisili.
            $person->addresses()->updateOrCreate(['type' => 'utama'], $profile->only([
                'country', 'province', 'city', 'district', 'village', 'address', 'postal_code', 'rt', 'rw',
            ]) + ['is_primary' => true]);

            return $person;
        });
    }

    /** Lampiran KTP/KK/STR/SIP/RKK: salin baris (file_path sama, file TIDAK dipindah) dan buang
     * baris salinan yang asalnya sudah dihapus. STR/SIP ikut disalin karena karyawan nakes juga
     * memilikinya, bukan cuma dokter. */
    private function syncDocuments(Person $person): void
    {
        $source = UserProfileDocument::where('user_id', $person->user_id)->get();

        foreach ($source as $doc) {
            PersonDocument::updateOrCreate(
                ['person_id' => $person->id, 'type' => $doc->label],
                ['file_path' => $doc->file_path],
            );
        }

        PersonDocument::where('person_id', $person->id)
            ->whereNotIn('type', $source->pluck('label'))
            ->delete();
    }
}
