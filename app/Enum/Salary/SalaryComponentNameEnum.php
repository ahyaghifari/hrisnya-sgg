<?php

namespace App\Enum\Salary;

enum SalaryComponentNameEnum: string
{
    // by Job Position
    case GAJI_POKOK = 'Gaji Pokok';
    case TUNJANGAN_JABATAN = 'Tunjangan Jabatan';
    case TUNJANGAN_TRANSPORTASI = 'Tunjangan Transportasi';

    case TUNJANGAN_MASA_KERJA = 'Tunjangan Masa Kerja';
    case BPJS_KESEHATAN = 'BPJS Kesehatan';
    case BPJS_KETENAGA_KERJAAN = 'BPJS Ketenaga Kerjaan';

    // by company
    case JASA_PELAYANAN = 'Jasa Pelayanan';

    // general
    // case LEMBUR = 'Lembur'; // tidak dipakai, lembur dihitung langsung dari OvertimeRateRules
    // case TUNJANGAN_KELEBIHAN_JAM = 'Tunjangan Kelebihan Jam Kerja';
    case CUTI = 'Leave';
    case IZIN = 'Izin';
    case LATE_ARRIVAL = 'Keterlambatan';

    // tax doctor
    case DOCTOR_TAX = 'Pajak Jasa Dokter';

    // archived
    // case INSENTIF = 'Insentif';


    public function label(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function jobPositionComponents(): array
    {
        return [
            self::TUNJANGAN_JABATAN,
            self::TUNJANGAN_TRANSPORTASI,
            self::TUNJANGAN_MASA_KERJA,
            self::BPJS_KESEHATAN,
            self::BPJS_KETENAGA_KERJAAN,
        ];
    }

    public static function pangkatComponents(): array
    {
        return [
            self::GAJI_POKOK,
        ];
    }

    public static function companyComponents(): array
    {
        return [
            self::JASA_PELAYANAN,
        ];
    }

    public static function generalComponents(): array
    {
        return [
            // self::TUNJANGAN_KELEBIHAN_JAM,
            self::CUTI,
            self::IZIN,
            self::LATE_ARRIVAL,
            self::DOCTOR_TAX,
        ];
    }
}
