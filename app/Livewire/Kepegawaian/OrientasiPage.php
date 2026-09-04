<?php

namespace App\Livewire\Kepegawaian;

use App\Models\OrientationSubmission;
use App\Services\OrientationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Self-service karyawan Orientasi: ajukan penilaian orientasi + unggah bukti kegiatan, lalu
 * kirim ke Approval Engine. Pola Sikartap (PengajuanKaryawanTetapPage). Sisi penilai ada di
 * OrientasiDetailPage.
 */
#[Layout('components.layout', ['title' => 'Orientasi Saya'])]
class OrientasiPage extends Component
{
    use WithFileUploads;

    public ?string $submissionId = null;

    public $buktiFile = null;

    public ?string $note = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isOrientasiKaryawan(), 403);

        $this->submissionId = OrientationSubmission::where('user_id', Auth::id())->latest()->value('id');
    }

    public function getCurrentProperty(): ?OrientationSubmission
    {
        return $this->submissionId
            ? OrientationSubmission::with('approvalSnapshot')->find($this->submissionId)
            : null;
    }

    public function getSubmissionsProperty()
    {
        return OrientationSubmission::where('user_id', Auth::id())->latest()->get();
    }

    /** Karyawan orientasi boleh mengajukan BERKALI-KALI. Satu-satunya batas: tidak boleh ada
     *  pengajuan yang masih berjalan (draft / menunggu persetujuan / sedang dinilai). Begitu
     *  pengajuan terakhir selesai atau dibatalkan, boleh mengajukan lagi — apa pun keputusannya. */
    public function getCanStartNewProperty(): bool
    {
        return ! $this->current
            || in_array($this->current->status, ['selesai', 'dibatalkan'], true);
    }

    public function startApplication(): void
    {
        abort_unless($this->canStartNew, 403);

        $submission = OrientationSubmission::create([
            'user_id' => Auth::id(),
            'company_id' => Auth::user()->company_id,
            'status' => 'draft',
        ]);

        $this->submissionId = $submission->id;
    }

    public function uploadBukti(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $this->validate(
            ['buktiFile' => ['required', 'file', 'max:20480', 'mimes:pdf,ppt,pptx']],
            [],
            ['buktiFile' => 'bukti kegiatan (PDF atau PowerPoint)'],
        );

        $path = $this->buktiFile->store('orientation/' . $submission->user_id, 'public');

        $attachments = $submission->attachments ?? [];
        $attachments[] = ['path' => $path, 'name' => $this->buktiFile->getClientOriginalName()];
        $submission->update(['attachments' => $attachments]);

        $this->buktiFile = null;

        Notification::make()->title('Bukti kegiatan terunggah')->success()->send();
    }

    public function removeBukti(int $index): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $attachments = $submission->attachments ?? [];

        if (isset($attachments[$index])) {
            Storage::disk('public')->delete($attachments[$index]['path']);
            unset($attachments[$index]);
            $submission->update(['attachments' => array_values($attachments)]);
        }
    }

    public function submit(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        try {
            $submission->update(['note' => $this->note]);
            app(OrientationService::class)->submit($submission, Auth::user());

            Notification::make()->title('Pengajuan orientasi terkirim')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal mengirim')->body($e->getMessage())->danger()->send();
        }
    }

    public function cancel(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->user_id === Auth::id(), 403);

        try {
            app(OrientationService::class)->cancel($submission, Auth::id());
            Notification::make()->title('Pengajuan dibatalkan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal membatalkan')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.orientasi-page');
    }
}
