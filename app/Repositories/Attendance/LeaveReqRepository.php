<?php

namespace App\Repositories\Attendance;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Attendance\AttendanceRequestAttachments;
use App\Models\Attendance\AttendanceRequestLeaveDetails;
use App\Models\Attendance\AttendanceRequests;
use App\Models\Master\Attendance\Holidays;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveReqRepository
{
    public function getLeaveTypeById(string $id)
    {
        return LeaveTypes::find($id);
    }

    public function findAttendanceReqLeaveDetail(string $attendanceReqId)
    {
        return AttendanceRequests::with('user.profile.orgStructure','leaveDetails')->find($attendanceReqId);
    }

    /**
     * Kuota cuti dihitung LIVE (tabel `leave_user_balances` sudah dihapus 2026-08-29):
     * - `quota`  = m_leave_types.quota_days
     * - `used`   = Σ total_days pengajuan `leave` `approved` user ini dalam periode berjalan
     * - periode:
     *   - Cuti Haid (usageWindow 'monthly')  = bulan kalender yg memuat $referenceDate
     *   - **Cuti Tahunan**                    = jendela 1 tahun DARI ULANG TAHUN BERGABUNG
     *     (`user_profiles.joining_date`) yang memuat $referenceDate. Mis. bergabung 5 Mei →
     *     periode 5 Mei 2026 – 4 Mei 2027. 29 Feb di tahun non-kabisat = 28 Feb. `joining_date`
     *     kosong → fallback tahun kalender Jan–Des.
     *   - Melahirkan/keguguran ('per_request') = tahun kalender (cuma buat tampilan, enforcement
     *     pakai assertWithinSpecialLeaveQuota per pengajuan)
     * - `$referenceDate` = tanggal acuan (saat approval: `start_date` pengajuan; default now()).
     * Approve/reject/cancel otomatis konsisten karena tidak ada saldo yang perlu "dikembalikan".
     */
    public function getLeaveUserBalance(string $userId, string $typeId, ?string $referenceDate = null): array
    {
        $leaveType = $this->getLeaveTypeById($typeId);

        $quota = $leaveType ? $leaveType->quota_days : 0;

        $ref = $referenceDate ? Carbon::parse($referenceDate) : Carbon::now();
        $category = $leaveType ? LeaveTypeCategoryEnum::tryFrom($leaveType->category ?? '') : null;

        if ($category?->usageWindow() === 'monthly') {
            $periodStart = $ref->copy()->startOfMonth();
            $periodEnd = $ref->copy()->endOfMonth();
        } elseif ($category === LeaveTypeCategoryEnum::TAHUNAN) {
            [$periodStart, $periodEnd] = $this->anniversaryWindow($userId, $ref);
        } else {
            $periodStart = $ref->copy()->startOfYear();
            $periodEnd = $ref->copy()->endOfYear();
        }

        // Bandingkan sebagai TANGGAL (bukan datetime) — kolom start_date/date bertipe date,
        // batas datetime `... 00:00:00` bikin pengajuan tepat di hari-pertama-periode luput.
        $periodStartDate = $periodStart->toDateString();
        $periodEndDate = $periodEnd->toDateString();

        $used = (int) AttendanceRequestLeaveDetails::query()
            ->where('leave_type_id', $typeId)
            ->whereBetween('start_date', [$periodStartDate, $periodEndDate])
            ->whereIn('request_id', AttendanceRequests::query()
                ->select('id')
                ->where('user_id', $userId)
                ->where('type', 'leave')
                ->where('status', 'approved'))
            ->sum('total_days');

        // Cuti bersama cuma mengurangi kuota Cuti Tahunan, bukan tipe cuti lain (izin, dll).
        $collectiveLeaveDays = 0;
        if ($leaveType && str_contains(mb_strtolower($leaveType->name), 'tahunan')) {
            $collectiveLeaveDays = Holidays::where('is_collective_leave', true)
                ->whereBetween('date', [$periodStartDate, $periodEndDate])
                ->count();
        }

        $available = $quota - $used - $collectiveLeaveDays;
        if ($available < 0) {
            $available = 0;
        }
        return [
            'quota' => $quota,
            'used' => $used,
            'collective_leave_days' => $collectiveLeaveDays,
            'available' => $available,
        ];
    }

    /**
     * Jendela 1 tahun cuti tahunan yang memuat $ref, dihitung dari ULANG TAHUN BERGABUNG
     * (`user_profiles.joining_date`). anchor = joining_date dgn tahun diganti tahun $ref;
     * kalau anchor jatuh SESUDAH $ref → mundur 1 tahun. 29 Feb di tahun non-kabisat → 28 Feb
     * (clamp ke jumlah hari bulan itu). joining_date kosong → fallback tahun kalender Jan–Des.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function anniversaryWindow(string $userId, Carbon $ref): array
    {
        $joiningRaw = User::find($userId)?->profile?->joining_date;

        if (! $joiningRaw) {
            return [$ref->copy()->startOfYear(), $ref->copy()->endOfYear()];
        }

        $joining = Carbon::parse($joiningRaw);
        $anchor = $this->anchorForYear($joining->month, $joining->day, $ref->year);

        if ($anchor->gt($ref->copy()->startOfDay())) {
            $anchor = $this->anchorForYear($joining->month, $joining->day, $ref->year - 1);
        }

        return [$anchor->copy()->startOfDay(), $anchor->copy()->addYear()->subDay()->endOfDay()];
    }

    /** Tanggal ulang tahun bergabung di $year, dgn hari di-clamp (29 Feb → 28 di non-kabisat). */
    private function anchorForYear(int $month, int $day, int $year): Carbon
    {
        $maxDay = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($day, $maxDay))->startOfDay();
    }

    public function storeLeaveRequest(array $data): array
    {
        DB::beginTransaction();
        try {
            // total_days dihitung di AttendaceReqService (hari kerja jadwal user). Fallback ke
            // hari kalender di sini cuma buat pemanggil lama yang belum mengirim nilainya.
            if (! isset($data['total_days'])) {
                if (!empty($data['start_date']) && empty($data['end_date'])) {
                    $data['total_days'] = 1;
                } elseif (!empty($data['start_date']) && !empty($data['end_date'])) {
                    $start = \Carbon\Carbon::parse($data['start_date']);
                    $end = \Carbon\Carbon::parse($data['end_date']);

                    if ($end->lt($start)) {
                        [$start, $end] = [$end, $start];
                    }

                    $data['total_days'] = $start->diffInDays($end) + 1;
                } else {
                    $data['total_days'] = 0;
                }
            }

            // Saldo TIDAK lagi dipotong di sini saat submit — dihitung live dari request
            // approved lewat getLeaveUserBalance(), lihat komentar di method itu.
            $detail = AttendanceRequestLeaveDetails::create([
                'request_id' => $data['request_id'],
                'leave_type_id' => $data['type_id'],
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $data['total_days'],
            ]);

            if (!empty($data['attachments_files']) && is_array($data['attachments_files'])) {
                foreach ($data['attachments_files'] as $file) {
                    if ($file) {
                        $path = Storage::disk('public')->putFile("leave/{$data['request_id']}", $file);

                        AttendanceRequestAttachments::create([
                            'request_id' => $data['request_id'],
                            'file_path' => $path,
                        ]);
                    }
                }
            }

            DB::commit();

            return [$detail];

        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
