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
 * Form MCU 1 pelamar Rekrutmen — 4 stasiun (perawat/lab/radiologi/dokter), tiap stasiun
 * digerbangi permission sendiri + punya tombol simpan sendiri. Data dikirim ke project
 * Rekrutmen eksternal (RekrutmenSyncService::saveMcuStation). Keputusan fit/unfit ada di
 * ApplicantDetailPage (butuh Update:JobOpenings + pemeriksaan dokter sudah diisi).
 *
 * Permission dipakai bareng modul Sikartap (INPUT_MCU_ASESMEN_PERAWAT dst + EDIT_INPUT_MCU)
 * — staff klinis yang sama, bukan Sikartap-only.
 */
#[Layout('components.layout', ['title' => 'Input MCU Pelamar'])]
class McuFormPage extends Component
{
    use WithFileUploads;

    public string $jobApplicationId;
    public string $status = '';
    public string $statusMcu = 'pending';

    public array $nurse = [];
    public array $lab = [];
    public array $radiology = [];
    public array $doctor = [];

    public array $filled = ['nurse' => false, 'lab' => false, 'radiology' => false, 'doctor' => false];

    public $nurseFile = null;
    public $labFile = null;
    public $radiologyFile = null;

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

    public function mount(string $jobApplication): void
    {
        abort_unless(
            Auth::user()->canInputSikartapMcu()
                || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value)
                || Auth::user()->can('View:JobOpenings'),
            403,
        );

        $this->jobApplicationId = $jobApplication;
        $this->loadState();
    }

    private function loadState(): void
    {
        $mcu = app(RekrutmenSyncService::class)->getMcu($this->jobApplicationId);

        if (! $mcu) {
            return;
        }

        $this->status = $mcu['status'] ?? '';
        $this->statusMcu = $mcu['status_mcu'] ?? 'pending';

        foreach (['nurse' => array_keys(self::NURSE_FIELDS), 'lab' => [...array_keys(self::LAB_FIELDS), 'kesimpulan'], 'radiology' => [...array_keys(self::RADIOLOGY_FIELDS), 'kesimpulan'], 'doctor' => [...array_keys(self::DOCTOR_FIELDS), 'diagnosa', 'rekomendasi', 'catatan']] as $station => $fields) {
            $this->filled[$station] = ! empty($mcu[$station]);
            $this->{$station} = collect($mcu[$station] ?? [])->only($fields)->all();
        }
    }

    private function canEditFilled(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value);
    }

    public function getCanNurseProperty(): bool
    {
        return ($this->filled['nurse'] ? $this->canEditFilled() : Auth::user()->can(CustomPermissionEnum::INPUT_MCU_ASESMEN_PERAWAT->value))
            && $this->status === 'mcu';
    }

    public function getCanLabProperty(): bool
    {
        return ($this->filled['lab'] ? $this->canEditFilled() : Auth::user()->can(CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value))
            && $this->status === 'mcu';
    }

    public function getCanRadiologyProperty(): bool
    {
        return ($this->filled['radiology'] ? $this->canEditFilled() : Auth::user()->can(CustomPermissionEnum::INPUT_MCU_RADIOLOGI->value))
            && $this->status === 'mcu';
    }

    public function getCanDoctorProperty(): bool
    {
        return ($this->filled['doctor'] ? $this->canEditFilled() : Auth::user()->can(CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value))
            && $this->status === 'mcu';
    }

    public function simpanNurse(): void
    {
        abort_unless($this->canNurse, 403);
        $this->validate([
            'nurse.tinggi_badan' => 'required|numeric', 'nurse.berat_badan' => 'required|numeric',
            'nurse.suhu' => 'required|numeric', 'nurse.nadi' => 'required|integer',
            'nurse.pernafasan' => 'required|integer', 'nurse.tekanan_darah' => 'required|string',
            'nurseFile' => 'nullable|file|max:5120',
        ]);
        $this->save('nurse', $this->nurse, $this->nurseFile);
    }

    public function simpanLab(): void
    {
        abort_unless($this->canLab, 403);
        $this->validate(['labFile' => 'nullable|file|max:5120']);
        $this->save('lab', $this->lab, $this->labFile);
    }

    public function simpanRadiology(): void
    {
        abort_unless($this->canRadiology, 403);
        $this->validate(['radiologyFile' => 'nullable|file|max:5120']);
        $this->save('radiology', $this->radiology, $this->radiologyFile);
    }

    public function simpanDokter(): void
    {
        abort_unless($this->canDoctor, 403);
        $this->save('doctor', $this->doctor, null);
    }

    private function save(string $station, array $data, $file): void
    {
        $result = app(RekrutmenSyncService::class)->saveMcuStation($this->jobApplicationId, $station, $data, $file);

        Notification::make()
            ->title($result['ok'] ? 'Tersimpan' : 'Gagal menyimpan')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();

        if ($result['ok']) {
            if (in_array($station, ['nurse', 'lab', 'radiology'], true)) {
                $this->reset($station . 'File');
            }
            $this->loadState();
        }
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu-form-page');
    }
}
