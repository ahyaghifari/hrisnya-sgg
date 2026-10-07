<?php

namespace App\Exports\Rekrutmen;

use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export daftar pelamar 1 lowongan ke Excel — DB `recruitment` langsung (HRIS sudah baca-tulis
 * DB itu, tidak lewat API lagi). Port dari App\Exports\ApplicationsExport project `rekrutmen`.
 * Kolom dinamis mengikuti form section/field perusahaan lowongan ini (EAV job_application_answers).
 * Field bertipe file diekspor sebagai nama/path tersimpan apa adanya — unduh dokumen kandidat
 * dari HRIS belum tersedia (lihat CandidateDocumentProxyController, Fase 6b).
 */
class JobApplicantsExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    private const STATUS_LABELS = [
        'applied' => 'Lamaran Masuk',
        'screening' => 'Peninjauan Berkas',
        'shortlisted' => 'Lolos Seleksi Berkas',
        'interview' => 'Wawancara',
        'offering' => 'Offering Letter',
        'mcu' => 'Medical Check Up',
        'onboarding' => 'TTD & Onboarding',
        'passed' => 'Diterima',
        'rejected' => 'Tidak Dilanjutkan',
    ];

    /** Data Utama (section 1 tetap di form lamaran), selalu di depan kolom master. */
    private const MAIN_FIELDS = [
        'full_name' => 'Nama Lengkap',
        'birth_place' => 'Tempat Lahir',
        'birth_date' => 'Tanggal Lahir',
        'gender' => 'Jenis Kelamin',
        'domicile' => 'Domisili',
        'education_level' => 'Jenjang Pendidikan',
    ];

    public function __construct(
        private string $jobOpeningId,
        private ?string $status = null,
    ) {}

    /** @return array<string, array{label:string, type:string}> field name => label/type */
    private function getFieldMap(): array
    {
        $jobOpening = JobOpening::with('company.formSections.fields')->find($this->jobOpeningId);
        $map = [];

        foreach ($jobOpening?->company?->formSections ?? [] as $section) {
            foreach ($section->fields as $field) {
                if (isset(self::MAIN_FIELDS[$field->name])) {
                    continue;
                }
                $map[$field->name] = ['label' => $field->label, 'type' => $field->type];
            }
        }

        return $map;
    }

    public function headings(): array
    {
        return array_merge(
            ['No', 'Status', 'Tanggal Daftar'],
            array_values(self::MAIN_FIELDS),
            array_column($this->getFieldMap(), 'label'),
        );
    }

    public function collection(): Collection
    {
        $fieldMap = $this->getFieldMap();
        $fieldKeys = array_keys($fieldMap);

        $applications = JobApplication::with('answers')
            ->where('job_opening_id', $this->jobOpeningId)
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->get();

        return $applications->map(function ($app, $i) use ($fieldKeys) {
            $answers = $app->answersMap();

            $row = [
                $i + 1,
                self::STATUS_LABELS[$app->status] ?? $app->status,
                $app->created_at->format('d/m/Y H:i'),
            ];

            foreach (array_keys(self::MAIN_FIELDS) as $key) {
                $v = $answers[$key] ?? '';
                $row[] = $key === 'birth_date' && $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : $v;
            }

            foreach ($fieldKeys as $key) {
                $row[] = $answers[$key] ?? '';
            }

            return $row;
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
