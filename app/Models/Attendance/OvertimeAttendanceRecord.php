<?php

namespace App\Models\Attendance;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scan lembur di hari libur jadwal (bucket 'overtime' AccessLogScheduleReader). Tidak mengenal
 * telat/pulang cepat - cuma jam masuk/pulang & durasi sebagai bukti pengajuan lembur.
 */
class OvertimeAttendanceRecord extends Model
{
    use HasUlids;

    protected $table = 'overtime_attendance_records';

    protected $fillable = ['user_id', 'date', 'check_in_time', 'check_out_time', 'working_minutes', 'source'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Simpan entry bucket 'overtime'. Baris attendance_records otomatis (mesin) tanggal yang sama
     * dihapus - mis. scan yang keburu terhitung masuk/telat sebelum pengajuan lembur dibuat.
     * Koreksi manual HR (is_auto_calculated=false) dibiarkan.
     *
     * @param  array<int, array>  $entries
     */
    public static function saveEntries(array $entries, string $source): int
    {
        foreach ($entries as $e) {
            $in = $e['check_in'] ?? null;
            $out = $e['check_out'] ?? null;
            $minutes = null;
            if ($in && $out) {
                $co = Carbon::createFromFormat('H:i:s', $out);
                $ci = Carbon::createFromFormat('H:i:s', $in);
                $minutes = (int) ($co->lt($ci) ? $co->addDay() : $co)->diffInMinutes($ci, true);
            }

            self::updateOrCreate(
                ['user_id' => $e['user']->id, 'date' => $e['date']],
                ['check_in_time' => $in, 'check_out_time' => $out, 'working_minutes' => $minutes, 'source' => $source]
            );

            AttendanceRecords::query()
                ->where('user_id', $e['user']->id)
                ->where('date', $e['date'])
                ->where('is_auto_calculated', true)
                ->delete();
        }

        return count($entries);
    }
}
