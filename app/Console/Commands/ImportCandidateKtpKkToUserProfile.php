<?php

namespace App\Console\Commands;

use App\Models\Candidate\Candidates;
use App\Models\User;
use App\Models\User\UserProfileDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off: sebelum ada halaman "Lampiran" self-service di Data Diri, KTP/KK kandidat cuma
 * tersimpan di candidate_documents (doc_type ktp/kk). Begitu kandidat itu diterima jadi
 * karyawan, datanya TIDAK otomatis pindah — akun user barunya mulai dari user_profile_documents
 * kosong. Command ini MENYALIN (bukan memindah — baris candidate_documents & file aslinya tetap
 * ada) KTP/KK yang sudah ada ke user_profile_documents, untuk kandidat yang emailnya cocok
 * dengan user aktif. Aman dijalankan ulang: dilewati kalau user itu sudah punya lampiran
 * berlabel sama (KTP/KK).
 */
class ImportCandidateKtpKkToUserProfile extends Command
{
    protected $signature = 'candidates:import-ktp-kk {--dry-run : Cuma tampilkan yang AKAN disalin, tidak menulis apa pun}';

    protected $description = 'Salin lampiran KTP/KK dari candidate_documents ke user_profile_documents untuk kandidat yang sudah jadi user (dicocokkan lewat email)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Candidates::query()
            ->whereHas('documents', fn ($q) => $q->whereIn('doc_type', ['ktp', 'kk']))
            ->with(['documents' => fn ($q) => $q->whereIn('doc_type', ['ktp', 'kk'])])
            ->get();

        $copied = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $user = User::where('email', $candidate->email)->first();

            if (! $user) {
                continue;
            }

            foreach ($candidate->documents as $doc) {
                $label = $doc->doc_type === 'ktp' ? 'KTP' : 'KK';

                $alreadyExists = UserProfileDocument::query()
                    ->where('user_id', $user->id)
                    ->where('label', $label)
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("[dry-run] {$candidate->email} -> {$user->name}: {$label} ({$doc->file_path})");
                    $copied++;

                    continue;
                }

                if (! Storage::disk('public')->exists($doc->file_path)) {
                    $this->warn("File tidak ditemukan, dilewati: {$doc->file_path}");

                    continue;
                }

                $newPath = 'profile-documents/' . $user->id . '/' . basename($doc->file_path);
                Storage::disk('public')->copy($doc->file_path, $newPath);

                UserProfileDocument::create([
                    'user_id' => $user->id,
                    'label' => $label,
                    'file_path' => $newPath,
                ]);

                $copied++;
            }
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Selesai: {$copied} lampiran disalin, {$skipped} dilewati (sudah ada).");

        return self::SUCCESS;
    }
}
