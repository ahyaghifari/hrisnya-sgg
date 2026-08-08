<?php

namespace App\Imports\Kepegawaian;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import akun DASAR karyawan (nama/email/nip saja, TIDAK bikin profil/role) - kunci
 * pencocokan NIP:
 * - NIP di file tapi tidak ada di sistem (company ini) -> status 'baru', dibuatkan akun baru
 *   pas confirm.
 * - NIP ketemu di sistem, tapi nama/email beda dari file -> status 'berubah', nama/email
 *   ditimpa pas confirm.
 * - NIP ketemu & nama/email sudah sama persis -> status 'tidak_berubah', dilewati (tidak ada
 *   yang ditulis).
 *
 * Kategori ke-4 ("ada di sistem, tidak ada di file") BUKAN tanggung jawab class ini - class ini
 * cuma tahu isi file yang di-upload (lihat getSeenNips(), dipakai KaryawanImportPage buat query
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
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $nip = trim((string) ($row['nip'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($nip === '' && $nama === '' && $email === '') {
                continue; // baris kosong, bukan error
            }

            if ($nip === '' || $nama === '' || $email === '') {
                $this->errors[] = "Baris {$rowNum}: nip, nama, dan email wajib diisi semua.";

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Baris {$rowNum}: email \"{$email}\" tidak valid.";

                continue;
            }

            $this->seenNips[] = $nip;

            $existing = User::where('company_id', $this->companyId)->where('nip', $nip)->first();

            // Email dipakai user LAIN (NIP beda) di company ini - unique constraint bakal
            // gagal kalau dipaksakan, tolak dari sini biar errornya jelas.
            $emailTaken = User::where('company_id', $this->companyId)
                ->where('email', $email)
                ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
                ->exists();

            if ($emailTaken) {
                $this->errors[] = "Baris {$rowNum}: email \"{$email}\" sudah dipakai NIP lain.";

                continue;
            }

            if (! $existing) {
                $this->handleNew($rowNum, $nip, $nama, $email);

                continue;
            }

            $changed = $existing->name !== $nama || strtolower($existing->email) !== $email;

            if (! $changed) {
                $this->previewRows[] = [
                    'row' => $rowNum,
                    'status' => 'tidak_berubah',
                    'nip' => $nip,
                    'nama' => $nama,
                    'email' => $email,
                ];

                continue;
            }

            $this->handleChanged($rowNum, $existing, $nip, $nama, $email);
        }
    }

    private function handleNew(int $rowNum, string $nip, string $nama, string $email): void
    {
        $password = self::generatePassword($nama, $nip);

        if ($this->preview) {
            $this->previewRows[] = [
                'row' => $rowNum,
                'status' => 'baru',
                'nip' => $nip,
                'nama' => $nama,
                'email' => $email,
                'password' => $password,
            ];

            return;
        }

        User::create([
            'name' => $nama,
            'email' => $email,
            'nip' => $nip,
            'company_id' => $this->companyId,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->successCount++;
    }

    private function handleChanged(int $rowNum, User $existing, string $nip, string $nama, string $email): void
    {
        if ($this->preview) {
            $this->previewRows[] = [
                'row' => $rowNum,
                'status' => 'berubah',
                'nip' => $nip,
                'nama_lama' => $existing->name,
                'nama_baru' => $nama,
                'email_lama' => $existing->email,
                'email_baru' => $email,
            ];

            return;
        }

        $existing->update(['name' => $nama, 'email' => $email]);

        $this->successCount++;
    }

    /** !{2 huruf inisial}{nip}# - inisial sama persis pola yang dipakai avatar di seluruh app
     * (2 huruf pertama tiap kata nama, diambil dari 2 kata pertama). */
    public static function generatePassword(string $name, string $nip): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->map(fn ($word) => mb_substr($word, 0, 1))
            ->take(2)
            ->implode('');

        return '!' . strtoupper($initials) . $nip . '#';
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
