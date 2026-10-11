<?php

namespace App\Services\SuratTugas;

use App\Models\SuratTugas\PenandatanganSuratTugas;
use App\Models\SuratTugas\SuratTugas;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus surat: draft (bebas diubah/dihapus pembuatnya) -> menunggu_ttd (isi terkunci, masuk
 * daftar tanda tangan pemberi tugas) -> terbit (setelah TTD, efek ke presensi).
 */
class SuratTugasService
{
    /**
     * Buat draft baru, atau perbarui $draft yang masih berstatus draft.
     *
     * @param  array{nomor?:?string, tugas:string, tempat:string, transportasi?:?string, lain_lain?:?string, waktu?:?string, peserta:array<int,string>, periode:array<int,array{start_date:string,end_date:?string}>}  $data
     */
    public function saveDraft(array $data, User $creator, ?SuratTugas $draft = null): SuratTugas
    {
        if ($draft) {
            $this->assertEditableDraft($draft, $creator);
        }

        $penandatangan = $this->penandatangan($creator);

        $peserta = array_values(array_unique($data['peserta'] ?? []));
        $periode = array_values($data['periode'] ?? []);

        if ($peserta === [] || $periode === []) {
            throw ValidationException::withMessages(['peserta' => 'Pilih minimal satu karyawan dan satu tanggal.']);
        }

        // Hanya bawahan sendiri (langsung/tidak langsung) yang boleh ditugaskan.
        $allowed = app(OrgHierarchyService::class)->subordinates($creator)->whereIn('users.id', $peserta)->pluck('users.id')->all();
        if (array_diff($peserta, $allowed)) {
            throw ValidationException::withMessages(['peserta' => 'Ada karyawan yang bukan bawahan Anda.']);
        }

        foreach ($periode as $row) {
            if (filled($row['end_date'] ?? null) && $row['end_date'] < $row['start_date']) {
                throw ValidationException::withMessages(['periode' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
            }
        }

        return DB::transaction(function () use ($data, $creator, $penandatangan, $peserta, $periode, $draft) {
            $attributes = [
                'nomor' => $data['nomor'] ?? null,
                'ttd_user_id' => $penandatangan->user_id,
                'nama_ttd' => $penandatangan->nama,
                'jabatan_ttd' => $penandatangan->jabatan,
                'nip_ttd' => $penandatangan->user?->nip,
                'payload_version' => 2,
                'tugas' => $data['tugas'],
                'tempat' => $data['tempat'],
                'transportasi' => $data['transportasi'] ?? null,
                'lain_lain' => $data['lain_lain'] ?? null,
                'waktu' => $data['waktu'] ?? null,
            ];

            if ($draft) {
                $draft->update($attributes);
                $draft->peserta()->delete();
                $draft->periode()->delete();
                $surat = $draft;
            } else {
                $surat = SuratTugas::create($attributes + [
                    'company_id' => $creator->company_id,
                    'status' => SuratTugas::STATUS_DRAFT,
                    'created_by' => $creator->id,
                ]);
            }

            foreach ($peserta as $userId) {
                $surat->peserta()->create(['user_id' => $userId]);
            }
            foreach ($periode as $row) {
                $surat->periode()->create(['start_date' => $row['start_date'], 'end_date' => $row['end_date'] ?: null]);
            }

            return $surat;
        });
    }

    /**
     * Draft diajukan: penanda tangan di-snapshot ulang dari pengaturan terkini (draft bisa
     * lama mengendap), isi terkunci, dan surat masuk daftar "menunggu tanda tangan".
     */
    public function requestSignature(SuratTugas $draft, User $creator): SuratTugas
    {
        $this->assertEditableDraft($draft, $creator);

        $penandatangan = $this->penandatangan($creator);

        return DB::transaction(function () use ($draft, $creator, $penandatangan) {
            $draft->update([
                'ttd_user_id' => $penandatangan->user_id,
                'nama_ttd' => $penandatangan->nama,
                'jabatan_ttd' => $penandatangan->jabatan,
                'nip_ttd' => $penandatangan->user?->nip,
                'payload_version' => 2,
                'status' => SuratTugas::STATUS_MENUNGGU_TTD,
            ]);

            // Snapshot identitas peserta (nama/NIP/jabatan) dibekukan saat diajukan: yang tercetak di surat
            // dan masuk hash TTD, tidak ikut berubah kalau profil karyawan diganti sesudahnya.
            $draft->peserta()->with('user.activeJobPosition.jobPosition')->get()->each->snapshotFromUser();
            $draft->unsetRelation('peserta');

            // Masuk daftar "menunggu tanda tangan" penanda tangan (registry documents).
            $draft->issueDocument('Surat Perintah Tugas', $creator);

            return $draft;
        });
    }

    public function deleteDraft(SuratTugas $draft, User $creator): void
    {
        $this->assertEditableDraft($draft, $creator);

        $draft->delete();
    }

    /**
     * TTD pemberi tugas masuk: surat terbit, lalu tiap peserta x periode dicatat sebagai izin
     * surat_tugas approved (efek ke presensi/roster sama seperti izin biasa). Idempotent.
     */
    public function afterSigned(SuratTugas $surat): void
    {
        if ($surat->isTerbit()) {
            return;
        }

        DB::transaction(function () use ($surat) {
            $surat->update(['status' => SuratTugas::STATUS_TERBIT]);
            $surat->loadMissing(['peserta.user', 'periode', 'createdBy']);

            $service = app(AttendaceReqService::class);

            foreach ($surat->peserta as $peserta) {
                foreach ($surat->periode as $periode) {
                    $service->storePermissionRequest([
                        'type_id' => 'surat_tugas',
                        'date' => $periode->start_date->toDateString(),
                        // Tanpa tanggal selesai: presensi hanya dicatat untuk tanggal mulai.
                        'end_date' => $periode->end_date?->toDateString(),
                        'purpose' => $surat->tugas,
                        'location' => $surat->tempat,
                    ], $peserta->user, true, 'Surat Perintah Tugas No. ' . $surat->documentNumber(), $surat->createdBy);
                }
            }
        });
    }

    /** Pemberi tugas diatur HC di Pengaturan Kepegawaian, bukan dipilih per surat. */
    private function penandatangan(User $creator): PenandatanganSuratTugas
    {
        return PenandatanganSuratTugas::where('company_id', $creator->company_id)->first()
            ?? throw ValidationException::withMessages(['penandatangan' => 'Penanda tangan surat tugas belum diatur di Pengaturan Kepegawaian.']);
    }

    private function assertEditableDraft(SuratTugas $surat, User $creator): void
    {
        abort_unless($surat->created_by === $creator->id && $surat->isDraft(), 403, 'Hanya draft milik Anda yang bisa diubah.');
    }
}
