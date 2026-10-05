<?php

namespace App\Models\LMS;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\LMS\LmsSettingEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Klaim pelatihan yang sudah dilakukan: user unggah sertifikat + jam per hari, HC memeriksa
 * konversi ke JP (setting LMS `menit_1_jp`). Hanya klaim approved yang menambah jam pelatihan
 * di Laporan Pelatihan (lihat approvedHoursByUser()).
 */
class TrainingClaim extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'user_id', 'title', 'organizer', 'certificate_path',
        'status', 'jp', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(ApprovalSnapshots::class, 'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::TRAINING_CLAIM->value);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistories::class, 'requestable_id', 'id')
            ->where('requestable_type', ApprovalModuleEnum::TRAINING_CLAIM->value);
    }

    public function sessions()
    {
        return $this->hasMany(TrainingClaimSession::class, 'training_claim_id')->orderBy('date')->orderBy('start_time');
    }

    /** Total menit semua sesi. $sessions boleh array form atau model (data_get dua-duanya). */
    public static function minutesOf(iterable $sessions): int
    {
        $minutes = 0;

        foreach ($sessions as $session) {
            $start = data_get($session, 'start_time');
            $end = data_get($session, 'end_time');

            if ($start && $end && ($s = Carbon::parse($start))->lessThan($e = Carbon::parse($end))) {
                $minutes += $s->diffInMinutes($e);
            }
        }

        return $minutes;
    }

    public static function minutesPerJp(): int
    {
        return (int) Setting::resolve(LmsSettingEnum::MENIT_1_JP->value, null, (int) LmsSettingEnum::MENIT_1_JP->default());
    }

    /** Total menit dibagi menit_1_jp, dibulatkan sekali (bukan per sesi). */
    public static function jpFor(int $minutes): int
    {
        $perJp = static::minutesPerJp();

        return $perJp > 0 ? (int) round($minutes / $perJp) : 0;
    }

    /** Klaim approved satu karyawan yang punya sesi di jendela baseline (isi modal Detail laporan). */
    public static function approvedFor($baseline, string $userId): Collection
    {
        return static::with('sessions')
            ->where('status', 'approved')
            ->where('company_id', $baseline->company_id)
            ->where('user_id', $userId)
            ->whereHas('sessions', fn ($q) => $q->whereBetween('date', [$baseline->start_date, $baseline->end_date]))
            ->latest()
            ->get();
    }

    /**
     * user_id => ['count' => jumlah pelatihan (ikut Total Pelatihan), 'jp' => total JP final, 'hours' => jam (JP x menit_1_jp / 60)] dari klaim
     * approved yang punya sesi di jendela baseline. Jadi kolom "JP/Jam Eksternal (Terlaksana)"
     * di Laporan Pelatihan (portal & admin) dan ikut menambah Total Jam Pelatihan.
     */
    public static function approvedByUser($baseline): Collection
    {
        $perJp = static::minutesPerJp();

        return static::where('status', 'approved')
            ->where('company_id', $baseline->company_id)
            ->whereHas('sessions', fn ($q) => $q->whereBetween('date', [$baseline->start_date, $baseline->end_date]))
            ->get(['user_id', 'jp'])
            ->groupBy('user_id')
            ->map(fn ($claims) => [
                'count' => $claims->count(),
                'jp' => (int) $claims->sum('jp'),
                'hours' => round($claims->sum('jp') * $perJp / 60, 2),
            ]);
    }
}
