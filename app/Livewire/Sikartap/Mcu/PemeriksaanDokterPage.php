<?php

namespace App\Livewire\Sikartap\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Sikartap\Sikartap;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Rewrite form_pemeriksaan_dokter.php legacy - diisi dokter (atau HRD), jadi gate final
 * lulus/tidak-nya MCU. Baca hasil perawat/lab/radiologi sebagai referensi read-only,
 * persis legacy (asesmen perawat, lab, radiologi di-load tapi tidak bisa diedit di sini).
 * Nilai `status_mcu` (fit/unfit) TETAP diputuskan HRD lewat action Filament ViewSikartap
 * (lihat § Fase 4 rencana-implementasi-sikartap.md) - form dokter cuma catat hasil
 * pemeriksaan fisik + rekomendasi tertulis, sama seperti legacy ($rek_dokter dipakai HRD
 * sebagai saran awal, bukan langsung jadi status_mcu).
 */
#[Layout('components.layout', ['title' => 'Pemeriksaan Dokter'])]
class PemeriksaanDokterPage extends Component
{
    public string $sikartapId;

    public array $form = [
        'kulit' => null, 'rambut' => null, 'visus_mata' => null, 'buta_warna' => null, 'lapang_pandang' => null,
        'hidung' => null, 'telinga' => null, 'tonsil' => null, 'faring' => null, 'gigi' => null,
        'kelenjar_gondok' => null,
        'suara_pernafasan' => null, 'ronchi' => null, 'wheezing' => null, 'lainnya_paru' => null, 'jantung' => null,
        'hati' => null, 'limpa' => null, 'epigastrium' => null, 'ginjal' => null, 'lainnya_abdomen' => null,
        'ekstremitas' => null, 'neurologi' => null,
        'diagnosa' => null, 'rekomendasi' => null, 'catatan' => null,
    ];

    public function mount(Sikartap $sikartap): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value), 403);
        abort_unless($sikartap->status_wawancara === 'disarankan', 403, 'MCU baru bisa diisi setelah wawancara disarankan.');
        abort_unless($sikartap->requiresMcu(), 403, 'Company ini tidak mensyaratkan MCU.');
        abort_unless($sikartap->ideInovasiApproved(), 403, 'MCU baru bisa diisi setelah ide inovasi disetujui atasan.');

        $this->sikartapId = $sikartap->id;

        if ($existing = $sikartap->mcuDoctorExamination) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->form = $existing->only(array_keys($this->form));
        }
    }

    public function getSikartapProperty(): Sikartap
    {
        return Sikartap::with(['mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination'])
            ->findOrFail($this->sikartapId);
    }

    public function save(): void
    {
        $sikartap = $this->sikartap;
        abort_unless(! $sikartap->mcuDoctorExamination || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);

        $sikartap->mcuDoctorExamination()->updateOrCreate(
            ['sikartap_id' => $sikartap->id],
            $this->form,
        );

        Notification::make()->title('Hasil pemeriksaan dokter tersimpan')->success()->send();

        $this->redirect(route('sikartap.dashboard'));
    }

    public function render()
    {
        return view('livewire.sikartap.mcu.pemeriksaan-dokter-page');
    }
}
