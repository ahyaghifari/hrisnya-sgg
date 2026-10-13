<?php

namespace App\Filament\Dokter\Pages;

use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Models\Rekrutmen\Company as RekrutmenCompany;
use App\Models\Rekrutmen\DoctorApplication;
use App\Services\Doctor\DoctorApplicationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

/**
 * Lamaran dokter dari portal rekrutmen (DB recruitment), tampil sebagai grid kartu seperti Beranda.
 * Alur: Lamaran -> Tinjau -> Kredensial (undang + unggah PDF kredensial) -> Diterima / Ditolak. "Terima" baru
 * menyalin data ke master DB `doctor` + case credentialing (DoctorApplicationService).
 */
class LamaranDokter extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Lamaran Dokter';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'lamaran-dokter';

    protected string $view = 'filament.dokter.pages.lamaran-dokter';

    public const PER_HALAMAN = 10;

    public string $filterStatus = '';

    public string $cari = '';

    public int $halaman = 1;

    public ?int $detailId = null;

    public bool $pengaturan = false;

    /** PDF kredensial yang sedang dipilih (upload Livewire sementara). */
    public $berkasKredensial = null;

    public static function getNavigationBadge(): ?string
    {
        return DoctorApplication::where('status', 'baru')->count() ?: null;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function updatedFilterStatus(): void
    {
        $this->halaman = 1;
    }

    public function updatedCari(): void
    {
        $this->halaman = 1;
    }

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    public function buka(int $id): void
    {
        $this->detailId = $id;
        $this->berkasKredensial = null;
    }

    public function tutup(): void
    {
        $this->detailId = null;
        $this->berkasKredensial = null;
    }

    private function query()
    {
        return DoctorApplication::query()
            ->when(trim($this->cari) !== '', fn ($q) => $q->where('data->nama', 'like', '%'.trim($this->cari).'%'))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest();
    }

    /** @return array{rows: \Illuminate\Support\Collection, total: int, halaman: int, terakhir: int} */
    public function daftar(): array
    {
        $total = $this->query()->count();
        $terakhir = max(1, (int) ceil($total / self::PER_HALAMAN));
        $halaman = min($this->halaman, $terakhir);

        $rows = $this->query()->with(['user', 'company'])->forPage($halaman, self::PER_HALAMAN)->get();

        return ['rows' => $rows, 'total' => $total, 'halaman' => $halaman, 'terakhir' => $terakhir];
    }

    public function detail(): ?DoctorApplication
    {
        return $this->detailId ? DoctorApplication::with(['user', 'company'])->find($this->detailId) : null;
    }

    public function fotoUrl(DoctorApplication $app): ?string
    {
        $path = $app->files['pas_foto']['path'] ?? null;

        return $path ? route('filament.dokter.lamaran.berkas', ['application' => $app->id, 'path' => $path]) : null;
    }

    public function bukaPengaturan(): void
    {
        $this->pengaturan = ! $this->pengaturan;
    }

    /** Perusahaan rekrutmen: tandai rumah sakit (tujuan lamaran portal dokter) & buka/tutup dokter umum. */
    public function perusahaan()
    {
        return RekrutmenCompany::active()->where('is_hospital', true)->orderBy('name')->get(['id', 'name', 'dokter_umum_open']);
    }

    /** Perusahaan aktif yang belum ditandai rumah sakit (pilihan untuk ditambahkan). */
    public function calonRumahSakit()
    {
        return RekrutmenCompany::active()->where('is_hospital', false)->orderBy('name')->get(['id', 'name']);
    }

    public function toggleRumahSakit(int|string $id): void
    {
        if (! filled($id)) {
            return;
        }

        $c = RekrutmenCompany::findOrFail($id);
        $c->is_hospital = ! $c->is_hospital;

        if (! $c->is_hospital) {
            $c->dokter_umum_open = false; // bukan RS = tidak ada rekrutmen dokter umum
        }

        $c->save();
    }

    public function toggleDokterUmum(int $id): void
    {
        $c = RekrutmenCompany::findOrFail($id);

        if ($c->is_hospital) {
            $c->dokter_umum_open = ! $c->dokter_umum_open;
            $c->save();
        }
    }

    /** Lamaran -> Tinjau: berkas & data pelamar mulai diperiksa. */
    public function tinjau(int $id): void
    {
        DoctorApplication::where('status', 'baru')->whereKey($id)->update(['status' => 'ditinjau']);
    }

    /** Tinjau -> Kredensial: dokter diundang proses kredensial. */
    public function undang(int $id): void
    {
        DoctorApplication::where('status', 'ditinjau')->whereKey($id)->update(['status' => 'kredensial']);
    }

    /** Simpan PDF hasil kredensial ke lamaran (disk `rekrutmen`); jadi dokumen case saat Diterima. */
    public function simpanKredensial(int $id): void
    {
        $this->validate(['berkasKredensial' => ['required', 'file', 'mimes:pdf', 'max:10240']], [
            'berkasKredensial.required' => 'Pilih file PDF kredensial dulu.',
            'berkasKredensial.mimes' => 'Dokumen kredensial harus PDF.',
            'berkasKredensial.max' => 'Ukuran maksimal 10 MB.',
        ]);

        $app = DoctorApplication::findOrFail($id);
        abort_unless($app->tahap() === 'kredensial', 403);

        $files = $app->files ?? [];
        if (isset($files['kredensial'])) {
            Storage::disk('rekrutmen')->delete($files['kredensial']['path']);
        }

        $files['kredensial'] = [
            'path' => $this->berkasKredensial->store("doctor-applications/{$app->id}", 'rekrutmen'),
            'name' => $this->berkasKredensial->getClientOriginalName(),
            'at' => now()->toDateTimeString(),
        ];
        $app->update(['files' => $files]);

        $this->berkasKredensial = null;
        Notification::make()->title('Dokumen kredensial tersimpan')->success()->send();
    }

    public function tolak(int $id): void
    {
        $app = DoctorApplication::findOrFail($id);

        if (! $app->isFinal()) {
            $app->update(['status' => 'ditolak']);
        }
    }

    public function terima(int $id): void
    {
        $app = DoctorApplication::findOrFail($id);

        if ($app->tahap() !== 'kredensial') {
            Notification::make()->title('Undang kredensial dulu sebelum menerima.')->warning()->send();

            return;
        }

        try {
            $doctor = app(DoctorApplicationService::class)->accept($app);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->detailId = null;
        Notification::make()->title('Lamaran diterima, dokter ditambahkan ke master')->success()
            ->actions([\Filament\Actions\Action::make('buka')->label('Buka dokter')->url(DoctorResource::getUrl('edit', ['record' => $doctor]))])
            ->send();
    }
}
