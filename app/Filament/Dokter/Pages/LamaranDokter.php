<?php

namespace App\Filament\Dokter\Pages;

use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Models\Rekrutmen\DoctorApplication;
use App\Services\Doctor\DoctorApplicationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Lamaran dokter dari portal rekrutmen (DB recruitment), tampil sebagai grid kartu seperti Beranda.
 * "Terima" menyalin data ke master DB `doctor` (DoctorApplicationService).
 */
class LamaranDokter extends Page
{
    protected static ?string $title = 'Lamaran Dokter';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'lamaran-dokter';

    protected string $view = 'filament.dokter.pages.lamaran-dokter';

    public const PER_HALAMAN = 10;

    public string $filterStatus = '';

    public int $halaman = 1;

    public ?int $detailId = null;

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

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    public function buka(int $id): void
    {
        $this->detailId = $id;
    }

    public function tutup(): void
    {
        $this->detailId = null;
    }

    private function query()
    {
        return DoctorApplication::query()
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

    public function tinjau(int $id): void
    {
        DoctorApplication::where('status', 'baru')->whereKey($id)->update(['status' => 'ditinjau']);
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

        if ($app->isFinal()) {
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
