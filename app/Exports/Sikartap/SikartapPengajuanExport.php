<?php

namespace App\Exports\Sikartap;

use App\Models\Sikartap\Sikartap;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export data pengajuan Sikartap sesuai filter aktif di widget tabel pengajuan
 * DashboardSikartapPage (company/search/periode/batch) - rewrite tombol "Export Excel"
 * dashboard_hrd.php/dashboard_direktur.php legacy. */
class SikartapPengajuanExport implements FromCollection, WithHeadings, WithStyles
{
    /** @param Collection<int, Sikartap> $pengajuan */
    public function __construct(protected Collection $pengajuan) {}

    public function headings(): array
    {
        return ['No', 'NIP', 'Nama', 'Perusahaan', 'Diajukan', 'Berkas', 'Tes', 'Wawancara', 'Ide Inovasi', 'MCU', 'Keputusan Direktur'];
    }

    public function collection(): Collection
    {
        $statusLabel = fn (string $status) => match ($status) {
            'approved', 'fit', 'lulus', 'disarankan' => 'Disetujui',
            'rejected', 'tidak_lulus', 'tidak_disarankan', 'unfit' => 'Ditolak',
            default => 'Menunggu',
        };

        return $this->pengajuan->values()->map(function (Sikartap $item, int $index) use ($statusLabel) {
            return [
                $index + 1,
                $item->user->nip,
                $item->user->name,
                $item->user->company?->name,
                $item->created_at->format('d/m/Y'),
                $statusLabel($item->status_berkas),
                $statusLabel($item->status_tes),
                $statusLabel($item->status_wawancara),
                $item->requiresIdeInovasi() ? $statusLabel($item->ideInovasi?->status_atasan ?? 'pending') : 'Dilewati',
                $item->requiresMcu() ? $statusLabel($item->status_mcu) : 'Dilewati',
                $statusLabel($item->status_keputusan_direktur),
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            ],
        ];
    }
}
