<?php

namespace App\Console\Commands;

use App\Enum\CustomPermissionEnum;
use App\Models\Employee\EmployeeNakesDocument;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use Illuminate\Console\Command;

/**
 * Pengingat masa berlaku STR/SIP (dokumen AKTIF saja; riwayat diabaikan). Dijalankan harian:
 * karyawan dikabari tepat H-90, H-30, H-7, dan hari habis; pemegang permission
 * lihat_lampiran_identitas_karyawan (HC) di company yang sama menerima satu rekap per hari.
 * ponytail: cocokkan hari persis - kalau scheduler mati sehari, pengingat hari itu terlewat.
 */
class NotifyNakesDocumentExpiry extends Command
{
    protected $signature = 'nakes:notify-expiry';

    protected $description = 'Kabari karyawan & HC soal STR/SIP yang mendekati/habis masa berlakunya';

    private const DAYS = [90, 30, 7, 0];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $docs = EmployeeNakesDocument::with('employee.user')
            ->where('is_active', true)
            ->whereIn('berlaku_sampai', collect(self::DAYS)->map(fn ($d) => $today->copy()->addDays($d)->toDateString()))
            ->get();

        $rekap = []; // company_id => [baris teks]
        foreach ($docs as $doc) {
            $user = $doc->employee?->user;
            if (! $user) {
                continue;
            }

            $sisa = $doc->sisaHari();
            $tanggal = $doc->berlaku_sampai->translatedFormat('d M Y');
            $kapan = $sisa === 0 ? 'habis hari ini' : "habis {$sisa} hari lagi ({$tanggal})";

            $user->notify(new SpReportUpdated(
                "{$doc->type} Segera Habis",
                "{$doc->type} Anda {$kapan}. Unggah yang terbaru lewat tombol Perpanjang di Data Diri.",
                url: route('profil-saya'),
                always: true,
            ));

            $rekap[$doc->employee->company_id][] = "{$user->name} - {$doc->type} {$kapan}";
        }

        foreach ($rekap as $companyId => $baris) {
            $penerima = User::permission(CustomPermissionEnum::LIHAT_LAMPIRAN_IDENTITAS_KARYAWAN->value)
                ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
                ->get();

            $body = implode('; ', array_slice($baris, 0, 5)) . (count($baris) > 5 ? '; dan ' . (count($baris) - 5) . ' lainnya.' : '.');

            foreach ($penerima as $hc) {
                $hc->notify(new SpReportUpdated('STR/SIP Karyawan Segera Habis', $body, always: true));
            }
        }

        $this->info("Dokumen diingatkan: {$docs->count()}.");

        return self::SUCCESS;
    }
}
