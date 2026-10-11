<?php

namespace App\Livewire;

use App\Models\Attendance\AttendanceRequestAttachments;
use App\Models\Attendance\AttendanceRequests;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Widget beranda "Lembur Hari Ini": muncul kalau hari ini karyawan punya pengajuan lembur berstatus
 * in_review/approved, dan boleh menambah lampiran/bukti kapan saja (termasuk setelah disetujui).
 * Kosong = widget tidak tampil.
 */
class OvertimeTodayCard extends Component
{
    use WithFileUploads;

    /** @var array<string, array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile>> keyed by request id */
    public array $files = [];

    /** @var array<string, string> URL lampiran link, keyed by request id */
    public array $links = [];

    /** Dari kartu Presensi Hari Ini: hari ini libur (tanggal merah / off sesuai jadwal) tapi ada lembur. */
    public bool $isHoliday = false;

    public function getRequestsProperty()
    {
        return $this->loadRequests();
    }

    /** Query segar (tanpa cache computed): addAttachments memakai ini supaya render sesudah simpan tidak memakai daftar lama. */
    private function loadRequests()
    {
        return AttendanceRequests::query()
            ->where('user_id', Auth::id())
            ->where('type', 'overtime')
            ->whereIn('status', ['in_review', 'approved'])
            ->whereHas('overtimeDetails', fn ($q) => $q->whereDate('date', today()))
            ->with(['overtimeDetails' => fn ($q) => $q->whereDate('date', today())->orderBy('start_time'), 'attachments'])
            ->latest()
            ->get();
    }

    public function addAttachments(string $requestId): void
    {
        // Scope ke daftar yang tampil di widget: milik sendiri, hari ini, in_review/approved.
        $request = $this->loadRequests()->firstWhere('id', $requestId);
        abort_unless($request, 403);

        // Sisa slot = batas - lampiran yang sudah ada; pilihan melebihi sisa ditolak seluruhnya (bukan dipotong diam-diam).
        $slots = max(0, AttendanceRequestAttachments::MAX_OVERTIME - $request->attachments->count());

        $this->validate([
            "files.{$requestId}" => ['required', 'array', "max:{$slots}"],
            "files.{$requestId}.*" => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            "files.{$requestId}.max" => 'Maksimal ' . AttendanceRequestAttachments::MAX_OVERTIME . " lampiran per lembur (sisa slot: {$slots}).",
        ], ["files.{$requestId}" => 'lampiran']);

        foreach ($this->files[$requestId] as $file) {
            AttendanceRequestAttachments::storeUploaded($request->id, $file, 'overtime');
        }

        unset($this->files[$requestId]);

        Notification::make()->title('Lampiran lembur ditambahkan')->success()->send();
    }

    /** Tambah lampiran berupa link (URL http/https). Ikut kuota maksimal yang sama dengan file. */
    public function addLink(string $requestId): void
    {
        $request = $this->loadRequests()->firstWhere('id', $requestId);
        abort_unless($request, 403);

        if ($request->attachments->count() >= AttendanceRequestAttachments::MAX_OVERTIME) {
            $this->addError("links.{$requestId}", 'Maksimal ' . AttendanceRequestAttachments::MAX_OVERTIME . ' lampiran per lembur.');

            return;
        }

        // Skema http/https saja (tolak javascript:, data:, dll) - URL ini nanti dirender sebagai href.
        $this->validate([
            "links.{$requestId}" => ['required', 'url:http,https', 'max:255'],
        ], [], ["links.{$requestId}" => 'link']);

        AttendanceRequestAttachments::create([
            'request_id' => $request->id,
            'file_path' => trim($this->links[$requestId]),
        ]);

        unset($this->links[$requestId]);
        $this->dispatch('overtime-link-added');

        Notification::make()->title('Link lampiran ditambahkan')->success()->send();
    }

    public function deleteAttachment(string $requestId, string $attachmentId): void
    {
        // Scope sama dengan addAttachments: hanya lampiran pengajuan milik sendiri yang tampil di widget ini.
        $request = $this->loadRequests()->firstWhere('id', $requestId);
        $attachment = $request?->attachments->firstWhere('id', $attachmentId);
        abort_unless($attachment, 403);

        if (! $attachment->is_link) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
        }
        $attachment->delete();

        Notification::make()->title('Lampiran dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.overtime-today-card');
    }
}
