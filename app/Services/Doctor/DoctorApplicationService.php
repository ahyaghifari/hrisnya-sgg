<?php

namespace App\Services\Doctor;

use App\Enum\Doctor\DoctorDocumentType;
use App\Enum\Doctor\DoctorLicenseType;
use App\Models\Doctor\Doctor;
use App\Models\Doctor\Specialty;
use App\Models\Master\Companies;
use App\Models\Rekrutmen\DoctorApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Terima lamaran dokter: salin isian & berkas dari DB recruitment ke master DB `doctor`.
 * Akun login HRIS TIDAK dibuat di sini (butuh password) - tautkan lewat Edit dokter.
 */
class DoctorApplicationService
{
    /** key berkas form lamaran => jenis dokumen master. */
    private const DOC_TYPES = [
        'pas_foto' => 'PAS_FOTO', 'd_cv' => 'CV', 'd_ktp' => 'KTP', 'd_npwp' => 'NPWP',
        'd_ijazah_dokter' => 'IJAZAH_DOKTER', 'd_ijazah_spesialis' => 'IJAZAH_SPESIALIS', 'd_ijazah_subspesialis' => 'IJAZAH_SUBSPESIALIS',
        'd_transkrip' => 'TRANSKRIP', 'd_kompetensi' => 'SERTIFIKAT_KOMPETENSI', 'd_rekening' => 'BUKU_REKENING',
    ];

    public function __construct(private DoctorDocumentService $documents, private DoctorLicenseService $licenses) {}

    public function accept(DoctorApplication $app): Doctor
    {
        $d = $app->data ?? [];

        if (! empty($d['nik']) && Doctor::where('nik', $d['nik'])->exists()) {
            throw new \RuntimeException('NIK sudah terdaftar di master dokter.');
        }

        $doctor = DB::connection('doctor')->transaction(function () use ($app, $d) {
            $doctor = Doctor::create([
                'nama' => $d['nama'] ?? $app->user?->name,
                'gelar_depan' => $d['gelar_depan'] ?? null,
                'gelar_belakang' => $d['gelar_belakang'] ?? null,
                'nik' => $d['nik'] ?? null,
                'tempat_lahir' => $d['tempat_lahir'] ?? null,
                'tanggal_lahir' => $d['tanggal_lahir'] ?? null,
                'jenis_kelamin' => ['Laki-laki' => 'L', 'Perempuan' => 'P'][$d['jenis_kelamin'] ?? ''] ?? null,
                'agama' => $d['agama'] ?? null,
                'status' => 'aktif',
            ]);

            foreach (['spesialisasi' => true, 'subspesialisasi' => false] as $key => $primary) {
                if (filled($d[$key] ?? null)) {
                    $doctor->specialties()->syncWithoutDetaching([
                        Specialty::firstOrCreate(['nama' => trim($d[$key])])->id => ['is_primary' => $primary],
                    ]);
                }
            }

            // Perusahaan rekrutmen -> m_companies HRIS lewat hris_code == code (jembatan yang sama dgn Rekrutmen\Company).
            $rsId = $app->company?->hris_code ? Companies::where('code', $app->company->hris_code)->value('id') : null;
            if ($rsId) {
                $doctor->hospitals()->create(['rumah_sakit_id' => $rsId, 'tanggal_mulai' => now()->toDateString(), 'status' => 'aktif']);
            }

            return $doctor;
        });

        foreach (['str' => DoctorLicenseType::STR, 'strkt' => DoctorLicenseType::STR_KT] as $p => $type) {
            if (filled($d["{$p}_nomor"] ?? null)) {
                $this->licenses->store($doctor, [
                    'jenis' => $type->value,
                    'nomor' => $d["{$p}_nomor"],
                    'tanggal_terbit' => $d["{$p}_terbit"] ?? null,
                    'tanggal_berakhir' => $d["{$p}_berlaku"] ?? null,
                    'status' => 'aktif',
                    'notes' => $d["{$p}_kolegium"] ?? null,
                ], $this->upload($app->files[$p.'_file'] ?? null));
            }
        }

        foreach (self::DOC_TYPES as $key => $type) {
            if ($file = $this->upload($app->files[$key] ?? null)) {
                $this->documents->store($doctor, ['document_type' => $type], $file);
            }
        }

        foreach ($d['pelatihan'] ?? [] as $row) {
            if ($file = $this->upload($row['berkas'] ?? null)) {
                $this->documents->store($doctor, [
                    'document_type' => DoctorDocumentType::SERTIFIKAT_PELATIHAN->value,
                    'document_number' => $row['nomor'] ?? null,
                    'expired_at' => $row['berlaku'] ?? null,
                    'notes' => $row['nama'] ?? null,
                ], $file);
            }
        }

        $app->update(['status' => 'diterima']);

        return $doctor;
    }

    /** @param array{path: string, name: string}|null $f */
    private function upload(?array $f): ?UploadedFile
    {
        $disk = Storage::disk('rekrutmen');

        return $f && $disk->exists($f['path']) ? new UploadedFile($disk->path($f['path']), $f['name'], null, null, true) : null;
    }
}
