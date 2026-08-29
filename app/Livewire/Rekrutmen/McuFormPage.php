<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\CustomPermissionEnum;
use App\Services\Job\RekrutmenSyncService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form 1 stasiun MCU untuk 1 pelamar Rekrutmen — halaman & route sendiri per stasiun
 * (perawat/lab/radiologi/dokter), pola sama modul Sikartap. Digerbangi permission stasiun
 * itu (reuse INPUT_MCU_* Sikartap) atau EDIT_INPUT_MCU kalau sudah terisi. Data dikirim ke
 * project Rekrutmen eksternal (RekrutmenSyncService::saveMcuStation). Keputusan fit/unfit
 * ada di ApplicantDetailPage.
 */
#[Layout('components.layout', ['title' => 'Input MCU Pelamar'])]
class McuFormPage extends Component
{
    use WithFileUploads;

    public string $jobApplicationId;
    public string $station;
    public string $status = '';
    public string $statusMcu = 'pending';

    public array $form = [];
    public bool $filled = false;
    public $upload = null;

    /** meta per stasiun: label, permission, ada upload file atau tidak. */
    public const STATIONS = [
        'nurse'     => ['Asesmen Perawat & EKG', CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT, true],
        'lab'       => ['Hasil Laboratorium', CustomPermissionEnum::INPUT_MCU_LABORATORIUM, true],
        'radiology' => ['Hasil Radiologi', CustomPermissionEnum::INPUT_MCU_RADIOLOGI, true],
        'doctor'    => ['Pemeriksaan Dokter', CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER, false],
    ];

    /** field => [type, label]. type: text|number|int|textarea|bool */
    public const NURSE_FIELDS = [
        'tinggi_badan' => ['number', 'Tinggi Badan (cm)'], 'berat_badan' => ['number', 'Berat Badan (kg)'],
        'tekanan_darah' => ['text', 'Tekanan Darah'], 'nadi' => ['int', 'Nadi (x/menit)'],
        'suhu' => ['number', 'Suhu (°C)'], 'pernafasan' => ['int', 'Pernafasan (x/menit)'],
        'keluhan_kesehatan' => ['textarea', 'Keluhan Kesehatan'],
        'rpk_hipertensi' => ['bool', 'Riwayat Keluarga: Hipertensi'], 'rpk_stroke' => ['bool', 'Riwayat Keluarga: Stroke'],
        'rpk_jantung' => ['bool', 'Riwayat Keluarga: Jantung'], 'rpk_diabetes' => ['bool', 'Riwayat Keluarga: Diabetes'],
        'rpk_lainnya' => ['text', 'Riwayat Keluarga: Lainnya'],
        'riwayat_penyakit_dahulu' => ['textarea', 'Riwayat Penyakit Dahulu'], 'riwayat_operasi' => ['textarea', 'Riwayat Operasi'],
        'alergi_makanan' => ['bool', 'Alergi Makanan'], 'alergi_obat' => ['bool', 'Alergi Obat'],
        'alergi_debu' => ['bool', 'Alergi Debu'], 'alergi_lainnya' => ['text', 'Alergi Lainnya'],
        'kebiasaan_olahraga' => ['bool', 'Kebiasaan: Olahraga'], 'kebiasaan_merokok' => ['bool', 'Kebiasaan: Merokok'],
        'kebiasaan_alkohol' => ['bool', 'Kebiasaan: Alkohol'], 'kebiasaan_obat' => ['bool', 'Kebiasaan: Obat'],
        'kebiasaan_lainnya' => ['text', 'Kebiasaan Lainnya'],
        'hasil_ekg' => ['text', 'Hasil EKG'], 'keterangan_ekg' => ['textarea', 'Keterangan EKG'],
        'catatan' => ['textarea', 'Catatan'],
    ];

    public const LAB_FIELDS = [
        'hemoglobin' => 'Hemoglobin', 'leukosit' => 'Leukosit', 'eritrosit' => 'Eritrosit', 'trombosit' => 'Trombosit',
        'hematokrit' => 'Hematokrit', 'basofil' => 'Basofil', 'eosinofil' => 'Eosinofil', 'stab' => 'Stab',
        'segmen' => 'Segmen', 'limfosit' => 'Limfosit', 'monosit' => 'Monosit', 'mcv' => 'MCV', 'mch' => 'MCH',
        'mchc' => 'MCHC', 'gds' => 'GDS', 'gda' => 'GDA', 'hbsag' => 'HBsAg', 'hiv' => 'HIV',
    ];

    public const RADIOLOGY_FIELDS = [
        'jenis_pemeriksaan' => 'Jenis Pemeriksaan', 'foto_thorax' => 'Foto Thorax', 'jantung' => 'Jantung',
        'paru_kanan' => 'Paru Kanan', 'paru_kiri' => 'Paru Kiri', 'diafragma' => 'Diafragma', 'tulang' => 'Tulang',
    ];

    public const DOCTOR_FIELDS = [
        'kulit' => 'Kulit', 'rambut' => 'Rambut', 'visus_mata' => 'Visus Mata', 'buta_warna' => 'Buta Warna',
        'lapang_pandang' => 'Lapang Pandang', 'hidung' => 'Hidung', 'telinga' => 'Telinga', 'tonsil' => 'Tonsil',
        'faring' => 'Faring', 'gigi' => 'Gigi', 'kelenjar_gondok' => 'Kelenjar Gondok',
        'suara_pernafasan' => 'Suara Pernafasan', 'ronchi' => 'Ronchi', 'wheezing' => 'Wheezing',
        'lainnya_paru' => 'Lainnya (Paru)', 'jantung' => 'Jantung', 'hati' => 'Hati', 'limpa' => 'Limpa',
        'epigastrium' => 'Epigastrium', 'ginjal' => 'Ginjal', 'lainnya_abdomen' => 'Lainnya (Abdomen)',
        'ekstremitas' => 'Ekstremitas', 'neurologi' => 'Neurologi',
    ];

    public function mount(string $jobApplication, string $station): void
    {
        abort_unless(isset(self::STATIONS[$station]), 404);

        $this->jobApplicationId = $jobApplication;
        $this->station = $station;

        $this->loadState();

        abort_unless($this->canFill(), 403);
    }

    public function fieldKeys(): array
    {
        return match ($this->station) {
            'nurse'     => array_keys(self::NURSE_FIELDS),
            'lab'       => [...array_keys(self::LAB_FIELDS), 'kesimpulan'],
            'radiology' => [...array_keys(self::RADIOLOGY_FIELDS), 'kesimpulan'],
            'doctor'    => [...array_keys(self::DOCTOR_FIELDS), 'diagnosa', 'rekomendasi', 'catatan'],
        };
    }

    public function stationLabel(): string
    {
        return self::STATIONS[$this->station][0];
    }

    public function hasUpload(): bool
    {
        return self::STATIONS[$this->station][2];
    }

    private function canFill(): bool
    {
        if ($this->status !== 'mcu') {
            return false;
        }

        $perm = self::STATIONS[$this->station][1]->value;

        return $this->filled
            ? Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value)
            : Auth::user()->can($perm);
    }

    private function loadState(): void
    {
        $mcu = app(RekrutmenSyncService::class)->getMcu($this->jobApplicationId);

        if (! $mcu) {
            return;
        }

        $this->status = $mcu['status'] ?? '';
        $this->statusMcu = $mcu['status_mcu'] ?? 'pending';
        $this->filled = ! empty($mcu[$this->station]);
        $this->form = collect($mcu[$this->station] ?? [])->only($this->fieldKeys())->all();
    }

    public function simpan(): void
    {
        abort_unless($this->canFill(), 403);

        $rules = ['upload' => 'nullable|file|max:5120'];
        if ($this->station === 'nurse') {
            $rules += [
                'form.tinggi_badan' => 'required|numeric', 'form.berat_badan' => 'required|numeric',
                'form.suhu' => 'required|numeric', 'form.nadi' => 'required|integer',
                'form.pernafasan' => 'required|integer', 'form.tekanan_darah' => 'required|string',
            ];
        }
        $this->validate($rules);

        $result = app(RekrutmenSyncService::class)->saveMcuStation(
            $this->jobApplicationId,
            $this->station,
            $this->form,
            $this->hasUpload() ? $this->upload : null,
        );

        Notification::make()
            ->title($result['ok'] ? 'Tersimpan' : 'Gagal menyimpan')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();

        if ($result['ok']) {
            $this->reset('upload');
            $this->loadState();
        }
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu-form-page');
    }

    /**
     * Tombol per stasiun (nurse/lab/radiology/doctor) yang boleh diisi user login — dipakai
     * McuQueuePage & ApplicantDetailPage, pola sama Sikartap::mcuActionsFor().
     *
     * @param  list<string>  $filled  stasiun yang sudah terisi
     * @return list<array{station:string,label:string,filled:bool,url:string}>
     */
    public static function availableStationLinks(string $jobApplicationId, array $filled = []): array
    {
        $user = Auth::user();
        $links = [];

        foreach (self::STATIONS as $station => [$label, $perm, $hasUpload]) {
            $isFilled = in_array($station, $filled, true);

            $can = $isFilled
                ? $user->can(CustomPermissionEnum::EDIT_INPUT_MCU->value)
                : $user->can($perm->value);

            if (! $can) {
                continue;
            }

            $links[] = [
                'station' => $station,
                'label'   => $label,
                'filled'  => $isFilled,
                'url'     => route('rekrutmen.pelamar.mcu', ['jobApplication' => $jobApplicationId, 'station' => $station]),
            ];
        }

        return $links;
    }
}
