<?php

namespace App\Imports\Kepegawaian;

use App\Models\Master\EmployeeTypes;
use App\Models\Master\Religions;
use App\Models\User;
use App\Models\User\UserProfiles;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import akun DASAR karyawan (nama/nip wajib, sisanya opsional) - kunci pencocokan NIP:
 * - NIP di file tapi tidak ada di sistem (company ini) -> status 'baru', dibuatkan akun baru
 *   pas confirm. email & tanggal_lahir WAJIB diisi untuk baris baru (email buat akun, tanggal
 *   lahir buat password awal).
 * - NIP ketemu di sistem, tapi ada field (nama/email/email_pribadi/status_karyawan/
 *   tanggal_mulai_kerja/jenis_kelamin/status_perkawinan/tempat_lahir/tanggal_lahir/agama/
 *   alamat/nik) beda dari file -> status 'berubah', ditimpa pas confirm. Field yang KOSONG di
 *   file (termasuk email) TIDAK
 *   menimpa data existing - dibiarkan ikut apa yang sudah ada di sistem.
 * - NIP ketemu & semua field sudah sama persis -> status 'tidak_berubah', dilewati (tidak ada
 *   yang ditulis).
 *
 * Kategori ke-4 ("ada di sistem, tidak ada di file") BUKAN tanggung jawab class ini - class ini
 * cuma tahu isi file yang di-upload (lihat getSeenNips(), dipakai ImportAccounts (Filament) buat query
 * balik "company ini dikurangi NIP yang kelihatan di file").
 *
 * Mode `preview: true` cuma menghitung status tiap baris tanpa menulis apa pun ke database -
 * sama pola BonImport (lihat komentarnya).
 */
class KaryawanAccountImport implements ToCollection, WithHeadingRow
{
    private array $previewRows = [];
    private array $errors = [];
    private int $successCount = 0;

    /** @var array<int, string> NIP (as string) yang benar-benar kebaca dari file - dipakai
     * caller buat cari NIP company yang TIDAK disebut di file sama sekali. */
    private array $seenNips = [];

    public function __construct(
        protected string $companyId,
        protected bool $preview = false,
    ) {}

    public function collection(Collection $rows): void
    {
        $employeeTypes = EmployeeTypes::where('company_id', $this->companyId)->pluck('id', 'name');
        $religions = Religions::pluck('id', 'name'); // agama: master data global, tidak per company
        $genderMap = ['laki-laki' => 'laki_laki', 'perempuan' => 'perempuan'];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $nip = trim((string) ($row['nip'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $emailPribadi = strtolower(trim((string) ($row['email_pribadi'] ?? '')));
            $statusKaryawan = trim((string) ($row['status_karyawan'] ?? ''));
            $tanggalMulaiKerja = trim((string) ($row['tanggal_mulai_kerja'] ?? ''));
            $jenisKelamin = trim((string) ($row['jenis_kelamin'] ?? ''));
            $statusPerkawinan = trim((string) ($row['status_perkawinan'] ?? ''));
            $tanggalLahir = trim((string) ($row['tanggal_lahir'] ?? ''));
            $tempatLahir = trim((string) ($row['tempat_lahir'] ?? ''));
            $agama = trim((string) ($row['agama'] ?? ''));
            $alamat = trim((string) ($row['alamat'] ?? ''));
            $nik = trim((string) ($row['nik'] ?? ''));

            if ($nip === '' && $nama === '') {
                continue; // baris kosong, bukan error
            }

            if ($nip === '' || $nama === '') {
                $this->errors[] = "Baris {$rowNum}: nip dan nama wajib diisi.";

                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Baris {$rowNum}: email \"{$email}\" tidak valid.";

                continue;
            }

            if ($emailPribadi !== '' && ! filter_var($emailPribadi, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Baris {$rowNum}: email pribadi \"{$emailPribadi}\" tidak valid.";

                continue;
            }

            $employeeTypeId = null;
            if ($statusKaryawan !== '') {
                if (! isset($employeeTypes[$statusKaryawan])) {
                    $this->errors[] = "Baris {$rowNum}: status karyawan \"{$statusKaryawan}\" tidak ditemukan di master tipe karyawan.";

                    continue;
                }
                $employeeTypeId = $employeeTypes[$statusKaryawan];
            }

            $religionId = null;
            if ($agama !== '') {
                if (! isset($religions[$agama])) {
                    $this->errors[] = "Baris {$rowNum}: agama \"{$agama}\" tidak ditemukan di master agama.";

                    continue;
                }
                $religionId = $religions[$agama];
            }

            $gender = null;
            if ($jenisKelamin !== '') {
                $genderKey = strtolower($jenisKelamin);
                if (! isset($genderMap[$genderKey])) {
                    $this->errors[] = "Baris {$rowNum}: jenis kelamin \"{$jenisKelamin}\" harus \"Laki-laki\" atau \"Perempuan\".";

                    continue;
                }
                $gender = $genderMap[$genderKey];
            }

            $joiningDate = $tanggalMulaiKerja !== '' ? $this->parseDate($tanggalMulaiKerja) : null;
            if ($tanggalMulaiKerja !== '' && $joiningDate === null) {
                $this->errors[] = "Baris {$rowNum}: tanggal mulai kerja \"{$tanggalMulaiKerja}\" tidak valid.";

                continue;
            }

            $dateOfBirth = $tanggalLahir !== '' ? $this->parseDate($tanggalLahir) : null;
            if ($tanggalLahir !== '' && $dateOfBirth === null) {
                $this->errors[] = "Baris {$rowNum}: tanggal lahir \"{$tanggalLahir}\" tidak valid.";

                continue;
            }

            $this->seenNips[] = $nip;

            $existing = User::whereCompany($this->companyId)->whereNip($nip)->first();

            if (! $existing && $email === '') {
                $this->errors[] = "Baris {$rowNum}: email wajib diisi untuk akun baru.";

                continue;
            }

            // Baris update dengan email kosong -> pakai email lama, jangan sampai email
            // existing ketimpa kosong / null.
            $effectiveEmail = $email !== '' ? $email : strtolower((string) $existing?->email);

            // Email dipakai user LAIN (NIP beda) di company ini - unique constraint bakal
            // gagal kalau dipaksakan, tolak dari sini biar errornya jelas. Cuma dicek kalau
            // email memang disebut di file (bukan hasil fallback ke email lama).
            if ($email !== '') {
                $emailTaken = User::whereCompany($this->companyId)
                    ->where('email', $email)
                    ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
                    ->exists();

                if ($emailTaken) {
                    $this->errors[] = "Baris {$rowNum}: email \"{$email}\" sudah dipakai NIP lain.";

                    continue;
                }
            }

            $profileFields = [
                'email_pribadi' => $emailPribadi !== '' ? $emailPribadi : null,
                'employee_type_id' => $employeeTypeId,
                'joining_date' => $joiningDate,
                'gender' => $gender,
                'marital_status' => $statusPerkawinan !== '' ? $statusPerkawinan : null,
                'date_of_birth' => $dateOfBirth,
                'place_of_birth' => $tempatLahir !== '' ? $tempatLahir : null,
                'religion_id' => $religionId,
                'address' => $alamat !== '' ? $alamat : null,
                'no_nik' => $nik !== '' ? $nik : null,
            ];

            // Sama keynya dengan $profileFields, tapi employee_type_id/religion_id diganti nama
            // manusiawinya (bukan ULID) - dipakai buat ditampilkan di tabel pratinjau.
            $profileLabels = [
                'email_pribadi' => $emailPribadi !== '' ? $emailPribadi : null,
                'employee_type_id' => $statusKaryawan !== '' ? $statusKaryawan : null,
                'joining_date' => $joiningDate,
                'gender' => $jenisKelamin !== '' ? $jenisKelamin : null,
                'marital_status' => $statusPerkawinan !== '' ? $statusPerkawinan : null,
                'date_of_birth' => $dateOfBirth,
                'place_of_birth' => $tempatLahir !== '' ? $tempatLahir : null,
                'religion_id' => $agama !== '' ? $agama : null,
                'address' => $alamat !== '' ? $alamat : null,
                'no_nik' => $nik !== '' ? $nik : null,
            ];

            if (! $existing) {
                if ($dateOfBirth === null) {
                    $this->errors[] = "Baris {$rowNum}: tanggal_lahir wajib diisi untuk akun baru (dipakai untuk password awal).";

                    continue;
                }

                $this->handleNew($rowNum, $nip, $nama, $effectiveEmail, $dateOfBirth, $profileFields, $profileLabels);

                continue;
            }

            $this->handleChanged($rowNum, $existing, $nip, $nama, $effectiveEmail, $profileFields, $profileLabels);
        }
    }

    private function handleNew(int $rowNum, string $nip, string $nama, string $email, string $dateOfBirth, array $profileFields, array $profileLabels): void
    {
        $password = self::generatePassword($nip, $dateOfBirth);

        if ($this->preview) {
            $this->previewRows[] = [
                'row' => $rowNum,
                'status' => 'baru',
                'nip' => $nip,
                'nama' => $nama,
                'email' => $email,
                'password' => $password,
                'profile' => $profileLabels,
            ];

            return;
        }

        $user = User::create([
            'name' => $nama,
            'email' => $email,
            'nip' => $nip,
            'company_id' => $this->companyId,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        if (array_filter($profileFields, fn ($v) => $v !== null) !== []) {
            UserProfiles::create(['user_id' => $user->id, ...$profileFields]);
        }

        $this->successCount++;
    }

    private function handleChanged(int $rowNum, User $existing, string $nip, string $nama, string $email, array $profileFields, array $profileLabels): void
    {
        $profile = $existing->profile;

        // Field kosong di file = tidak diubah, jadi bandingkan hanya field yang terisi di file.
        $profileChanges = array_filter(
            $profileFields,
            fn ($newValue, $key) => $newValue !== null && $newValue !== ($profile?->{$key}),
            ARRAY_FILTER_USE_BOTH
        );

        $accountChanged = $existing->name !== $nama || strtolower($existing->email) !== $email;

        if (! $accountChanged && $profileChanges === []) {
            $this->previewRows[] = [
                'row' => $rowNum,
                'status' => 'tidak_berubah',
                'nip' => $nip,
                'nama' => $nama,
                'email' => $email,
            ];

            return;
        }

        if ($this->preview) {
            $labelForOld = [
                'email_pribadi' => $profile?->email_pribadi,
                'employee_type_id' => $profile?->employeeType?->name,
                'joining_date' => $profile?->joining_date,
                'gender' => $profile?->gender,
                'marital_status' => $profile?->marital_status,
                'date_of_birth' => $profile?->date_of_birth,
                'place_of_birth' => $profile?->place_of_birth,
                'religion_id' => $profile?->religion?->name,
                'address' => $profile?->address,
                'no_nik' => $profile?->no_nik,
            ];

            $this->previewRows[] = [
                'row' => $rowNum,
                'status' => 'berubah',
                'nip' => $nip,
                'nama_lama' => $existing->name,
                'nama_baru' => $nama,
                'email_lama' => $existing->email,
                'email_baru' => $email,
                'profile_lama' => array_intersect_key($labelForOld, $profileChanges),
                'profile_baru' => array_intersect_key($profileLabels, $profileChanges),
            ];

            return;
        }

        $existing->update(['name' => $nama, 'email' => $email]);

        if ($profileChanges !== []) {
            UserProfiles::updateOrCreate(['user_id' => $existing->id], $profileChanges);
        }

        $this->successCount++;
    }

    /** !{tanggal lahir 2 digit}{nip}{tahun lahir 4 digit}# */
    public static function generatePassword(string $nip, string $dateOfBirth): string
    {
        $dob = Carbon::parse($dateOfBirth);

        return '!' . $dob->format('d') . $nip . $dob->format('Y') . '#';
    }

    private function parseDate(string $value): ?string
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function getPreview(): array
    {
        return $this->previewRows;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /** @return array<int, string> */
    public function getSeenNips(): array
    {
        return $this->seenNips;
    }
}
