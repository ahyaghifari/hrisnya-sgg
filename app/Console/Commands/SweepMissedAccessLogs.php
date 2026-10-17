<?php

namespace App\Console\Commands;

use App\Models\Attendance\AttendanceRecords;
use App\Services\Attendance\AccessLogResweepService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Jaring pengaman untuk SyncAccessLogsAttendance (yang incremental, tiap 20 detik).
 *
 * Sync incremental menyaring dengan access_datetime > watermark, dan watermark selalu maju ke scan
 * terbaru yang IA lihat. Baris yang masuk ke access_logs BELAKANGAN tapi membawa jam yang lebih tua
 * dari garis itu tidak akan pernah ikut batch manapun lagi - hilang diam-diam, tanpa log. Penyebab
 * yang ketemu di produksi: jam device antar lokasi tidak sinkron (sejak RS ikut, watermark holding
 * didorong maju oleh device yang jamnya lebih depan), dan device yang sempat offline lalu mengirim
 * susulan. Baris yang terbaca tapi dibuang (jadwal belum siap, roster masih libur) ikut tersembuhkan
 * di sini, karena watermark juga terlanjur maju melewatinya.
 *
 * Command ini TIDAK memakai watermark sama sekali. Dia membaca ulang SEHARI PENUH lewat
 * readAndGroup() - jalur batch yang sama dengan tombol "Sync Presensi" manual, yang aman diulang
 * berapa kali pun karena tidak punya konsep "scan baru" (bandingkan readAndGroupIncremental yang
 * memutuskan slot/split/overnight dari scan mana yang baru di batch - lihat peringatan di docblock
 * SyncAccessLogsAttendance::handle()).
 *
 * Yang disapu cuma orang yang JANGGAL (lihat findSuspects()), bukan semua orang - di hari normal
 * daftarnya kosong dan command ini berhenti setelah 2 query agregat.
 */
class SweepMissedAccessLogs extends Command
{
    protected $signature = 'attendance:sweep-missed-scans {--date= : Default hari ini} {--limit=50 : Batas orang yang disapu per run}';

    protected $description = 'Baca ulang sehari penuh untuk karyawan yang scan-nya tidak nyambung dengan attendance_records';

    /** Beda sedetik dua detik = tap ganda, sudah dibuang reader. Bukan tanda jam pulang hilang. */
    private const REPEATED_TAP_SECONDS = 60;

    public function handle(AccessLogResweepService $resweepService): int
    {
        // Carbon::parse biar scheduler bisa mengirim kata relatif ("yesterday") - kalau tanggalnya
        // dihitung di bootstrap/app.php, nilainya membeku selama proses schedule:work hidup.
        $date = Carbon::parse($this->option('date') ?: 'today')->toDateString();
        $limit = (int) $this->option('limit');
        $log = Log::channel('attendance_sync');
        $runId = Str::random(6);

        $suspects = $this->findSuspects($date);

        if ($suspects->isEmpty()) {
            $this->info("Sapuan {$date}: tidak ada yang janggal.");

            return self::SUCCESS;
        }

        // ponytail: dibatasi supaya satu run tidak meledak kalau ada gangguan massal (mis. roster
        // sekantor belum diisi). Sisanya kebagian run berikutnya, 10 menit lagi.
        $overflow = max(0, $suspects->count() - $limit);
        $suspects = $suspects->take($limit);

        $log->warning("[{$runId}] sapuan {$date}: " . $suspects->count() . ' nip janggal'
            . ($overflow > 0 ? " (+{$overflow} ditunda ke run berikutnya)" : '')
            . ' -> ' . $suspects->implode(', '));

        $saved = 0;
        $skippedManual = 0;

        foreach ($suspects as $nip) {
            try {
                [$s, $m] = $resweepService->resweepUserDate($nip, $date);
                $saved += $s;
                $skippedManual += $m;
            } catch (\Throwable $e) {
                // Satu orang bermasalah tidak boleh menyandera sisanya.
                $log->error("[{$runId}] sapuan gagal: nip={$nip} tanggal={$date} error={$e->getMessage()}");
            }
        }

        $log->info("[{$runId}] sapuan selesai: tersimpan={$saved} dilewati_koreksi_manual={$skippedManual}");
        $this->info("Sapuan {$date}: {$saved} tersimpan, {$skippedManual} dilewati (koreksi manual).");

        return self::SUCCESS;
    }

    /**
     * Orang yang scan mentahnya tidak nyambung dengan attendance_records hari itu. Sengaja pakai
     * aturan kasar dan murah (2 query agregat, tanpa menyentuh resolusi jadwal) - salah tuduh cuma
     * berakibat satu baca ulang percuma, sedangkan gagal menuduh berarti presensinya tetap hilang.
     *
     * Tidak dianggap janggal: orang yang sudah check-in dan belum scan lagi (mayoritas karyawan
     * sepanjang jam kerja) - jam pulang kosong itu wajar selama belum ada scan berikutnya.
     *
     * ponytail: orang yang jam pulangnya DITOLAK kalkulator (di bawah min_work_minutes) akan terus
     * kelihatan janggal dan tersapu ulang tiap run sampai hari berganti. Jumlahnya sedikit dan
     * hasilnya tetap benar (ditolak lagi), jadi dibiarkan - kalau ternyata ramai, simpan alasan
     * penolakan di attendance_records lalu kecualikan di sini.
     *
     * @return Collection<int,string>
     */
    private function findSuspects(string $date): Collection
    {
        $scans = DB::connection(config('database.access_log_connection'))->table('access_logs')
            ->where('access_date', $date)
            ->groupBy('employee_id')
            ->select('employee_id', DB::raw('MIN(access_time) as first_scan'), DB::raw('MAX(access_time) as last_scan'))
            ->get();

        if ($scans->isEmpty()) {
            return collect();
        }

        $records = AttendanceRecords::query()
            ->where('date', $date)
            ->whereIn('nip', $scans->pluck('employee_id'))
            ->get(['nip', 'check_in_time', 'check_out_time'])
            ->keyBy('nip');

        return $scans->filter(function ($scan) use ($records) {
            $record = $records->get($scan->employee_id);

            // Punya scan tapi tidak ada barisnya sama sekali - inilah kasus utamanya.
            if (! $record) {
                return true;
            }

            // Jam masuk tersimpan tidak sama dengan scan pertama hari itu: ada scan yang tidak terbaca.
            if ($record->check_in_time !== $scan->first_scan) {
                return true;
            }

            // Ada scan lain yang jelas bukan tap ganda, tapi jam pulangnya masih kosong.
            return $record->check_out_time === null
                && $this->secondsBetween($scan->first_scan, $scan->last_scan) > self::REPEATED_TAP_SECONDS;
        })->pluck('employee_id')->values();
    }

    private function secondsBetween(string $from, string $to): int
    {
        return Carbon::parse($from)->diffInSeconds(Carbon::parse($to));
    }
}
