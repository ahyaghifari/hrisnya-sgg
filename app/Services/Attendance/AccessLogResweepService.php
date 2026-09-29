<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRecords;
use App\Repositories\Attendance\AttendanceRecordRepository;
use App\Services\Attendance\Calculators\FixedScheduleCalculator;
use App\Services\Attendance\Calculators\FlexibleScheduleCalculator;
use App\Services\Attendance\Calculators\ShiftScheduleCalculator;

/**
 * Baca ulang access_logs 1 (nip, tanggal) lewat jadwal TERBARU, lalu simpan hasilnya ke
 * attendance_records - dipakai bareng oleh SweepMissedAccessLogs (jaring pengaman berkala) dan
 * ScheduleRosterChangeService::executeApproved() (resweep TARGETED begitu roster disetujui,
 * supaya scan yang tadinya jatuh ke bucket 'off'/unresolved karena jadwalnya masih draft langsung
 * kebaca ulang dengan jadwal yang baru disetujui, tidak nunggu jadwal sweep berkala berikutnya -
 * instruksi user 2026-09-29). Logic aslinya cuma ada di command itu, diekstrak ke sini biar tidak
 * ada 2 salinan.
 */
class AccessLogResweepService
{
    public function __construct(
        private AccessLogScheduleReader $reader,
        private FixedScheduleCalculator $fixedCalculator,
        private FlexibleScheduleCalculator $flexibleCalculator,
        private ShiftScheduleCalculator $shiftCalculator,
        private AttendanceRecordRepository $repo,
    ) {}

    /** @return array{0:int,1:int} [tersimpan, dilewati karena koreksi manual] */
    public function resweepUserDate(string $nip, string $date): array
    {
        $result = $this->reader->readAndGroup($date, $date, $nip);

        $calculators = [
            'fixed' => $this->fixedCalculator,
            'flexible' => $this->flexibleCalculator,
            'shift' => $this->shiftCalculator,
        ];

        $saved = 0;
        $skippedManual = 0;

        foreach ($calculators as $type => $calculator) {
            foreach ($calculator->calculate($result['buckets'][$type]) as $r) {
                // Koreksi manual HR (is_auto_calculated=false) tidak boleh ditimpa mesin.
                if ($this->isManuallyCorrected($r['user']->nip, $r['date'])) {
                    $skippedManual++;

                    continue;
                }

                $this->repo->saveCalculatedResult($r, 'access_logs_' . $type);
                $saved++;
            }
        }

        return [$saved, $skippedManual];
    }

    private function isManuallyCorrected(string $nip, string $date): bool
    {
        return AttendanceRecords::query()
            ->where('nip', $nip)
            ->where('date', $date)
            ->where('is_auto_calculated', false)
            ->exists();
    }
}
