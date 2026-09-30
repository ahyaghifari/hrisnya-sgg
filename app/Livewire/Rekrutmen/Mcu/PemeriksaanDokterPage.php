<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Rekrutmen\Mcu\Concerns\HandlesMcuFileUpload;
use App\Models\Rekrutmen\McuInvitee;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pemeriksaan dokter MCU rekrutmen — UNGGAH BERKAS hasilnya, DITAMBAH pilihan rekomendasi fit /
 * tidak fit (keputusan user 2026-09-29). Rincian pemeriksaan fisik tidak diisi lagi di sini;
 * kolomnya masih ada supaya data lama tetap terbaca.
 *
 * Yang dipilih dokter di sini REKOMENDASI, bukan keputusan akhir: keputusan MCU tetap ditekan HRD
 * di halaman MCU panel rekrutmen (mcu_invitees.status_mcu). MCU Sikartap terpisah.
 */
#[Layout('components.layout', ['title' => 'Pemeriksaan Dokter (MCU Rekrutmen)'])]
class PemeriksaanDokterPage extends Component
{
    use HandlesMcuFileUpload;

    public string $inviteeId;

    public ?string $rekomendasiFit = null;

    public function mount(string $invitee): void
    {
        abort_unless(Auth::user()->can($this->stationPermission()), 403);

        $this->inviteeId = $invitee;
        $model = $this->invitee();

        if ($existing = $model->doctorExamination) {
            abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');
            $this->existingFilePath = $existing->file_path;
            $this->rekomendasiFit = $existing->rekomendasi_fit;
        }
    }

    protected function stationPermission(): string
    {
        return CustomPermissionEnum::INPUT_MCU_PEMERIKSAAN_DOKTER->value;
    }

    protected function rules(): array
    {
        return $this->fileRules() + [
            'rekomendasiFit' => ['required', 'in:fit,unfit'],
        ];
    }

    public function getInviteeProperty(): McuInvitee
    {
        return $this->invitee();
    }

    /**
     * Berkas hasil stasiun lain (perawat, lab, radiologi) yang perlu dibaca dokter sebelum
     * memutuskan fit/tidak fit.
     *
     * @return array<int, array{stasiun:string, path:?string}>
     */
    public function getLampiranProperty(): array
    {
        $invitee = $this->invitee();

        return [
            ['stasiun' => 'Asesmen Perawat', 'path' => $invitee->nurseAssessment?->file_path],
            ['stasiun' => 'Laboratorium', 'path' => $invitee->labResult?->file_path],
            ['stasiun' => 'Radiologi', 'path' => $invitee->radiologyResult?->file_path],
        ];
    }

    /**
     * Unduh berkas stasiun lain. Stasiunnya disebut lewat nama, bukan path dari request - path bebas
     * dari luar berarti berkas kandidat lain ikut bisa dibaca.
     */
    public function downloadLampiran(string $stasiun)
    {
        $path = collect($this->lampiran)->firstWhere('stasiun', $stasiun)['path'] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    private function invitee(): McuInvitee
    {
        $model = McuInvitee::with([
            'jobApplication.answers', 'nurseAssessment', 'labResult', 'radiologyResult', 'doctorExamination',
        ])->findOrFail($this->inviteeId);
        abort_unless($model->jobApplication?->status === 'mcu', 403, 'Pelamar ini tidak sedang dalam tahap MCU.');

        return $model;
    }

    public function save(): void
    {
        $this->validate();

        $model = $this->invitee();
        abort_unless(! $model->doctorExamination || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403);

        $path = $this->resolveFilePath($model->id);

        if (! $path) {
            $this->addError('file', 'Berkas hasil pemeriksaan dokter wajib diunggah.');

            return;
        }

        $model->doctorExamination()->updateOrCreate(
            ['mcu_invitee_id' => $model->id],
            ['file_path' => $path, 'rekomendasi_fit' => $this->rekomendasiFit],
        );

        Notification::make()->title('Hasil pemeriksaan dokter tersimpan')->success()->send();

        $this->redirect(route('rekrutmen.mcu.queue'));
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.pemeriksaan-dokter-page');
    }
}
