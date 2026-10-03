<?php

namespace App\Filament\Persuratan\Pages;

use App\Enum\JenisSurat;
use App\Models\Persuratan\Surat;
use App\Models\Persuratan\Unit;
use App\Services\Persuratan\NomorSuratService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use App\Models\Persuratan\Perusahaan;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Beranda 1 layar: kotak cari surat + tombol Buat Nomor Surat (modal). Tanpa widget/header. */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Beranda';

    protected string $view = 'filament.persuratan.pages.dashboard';

    public string $q = '';

    /** Kata kunci yang SUDAH diterapkan (lewat tombol Cari); $q hanya isi kotak input. */
    public string $kataCari = '';

    public ?string $hasilNomor = null;

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public int $halaman = 1;

    public int $perHalaman = 10;

    public string $filterPerusahaan = '';

    public string $filterUnit = '';

    public string $filterJenis = '';

    public ?int $detailId = null;

    public function buka(int $id): void
    {
        $this->detailId = $id;
    }

    public function tutup(): void
    {
        $this->detailId = null;
    }

    public function detail(): ?Surat
    {
        return $this->detailId ? Surat::with(['unit.perusahaan', 'pembuat'])->find($this->detailId) : null;
    }

    public function updatedFilterJenis(): void
    {
        $this->halaman = 1;
    }

    /** Ganti perusahaan = unit yang terpilih belum tentu milik perusahaan itu, jadi direset. */
    public function updatedFilterPerusahaan(): void
    {
        $this->filterUnit = '';
        $this->halaman = 1;
    }

    public function updatedFilterUnit(): void
    {
        $this->halaman = 1;
    }

    public function updatedPerHalaman(): void
    {
        $this->halaman = 1;
    }

    public function keHalaman(int $n): void
    {
        $this->halaman = max(1, $n);
    }

    /**
     * Daftar surat (tanggal surat terbaru dulu). Mode normal: bisa difilter perusahaan / unit / jenis.
     * Mode cari ($kataCari terisi): cari di SEMUA surat (nomor, judul, perihal, pembuat), filter dilepas.
     */
    public function riwayat(): LengthAwarePaginator
    {
        $q = $this->kataCari;

        return Surat::with(['unit.perusahaan', 'pembuat'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('nomor', 'like', "%{$q}%")
                ->orWhere('judul_surat', 'like', "%{$q}%")
                ->orWhere('perihal', 'like', "%{$q}%")
                ->orWhere('dibuat_oleh_nama', 'like', "%{$q}%")
                ->orWhereHas('pembuat', fn ($p) => $p->where('nama_lengkap', 'like', "%{$q}%"))))
            ->when($q === '' && $this->filterUnit !== '', fn ($query) => $query->where('unit_id', $this->filterUnit))
            ->when($q === '' && $this->filterJenis !== '', fn ($query) => $query->where('jenis', $this->filterJenis))
            ->when($q === '' && $this->filterPerusahaan !== '', fn ($query) => $query->whereHas('unit', fn ($u) => $u->where('perusahaan_id', $this->filterPerusahaan)))
            ->latest('tanggal')->latest('id')
            ->paginate($this->perHalaman, ['*'], 'halaman', $this->halaman);
    }

    /** @return Collection<int, string> id => nama */
    public function opsiPerusahaan(): Collection
    {
        return Perusahaan::orderBy('kode_surat')->pluck('nama', 'id');
    }

    /** @return Collection<int, string> id => nama unit (disaring perusahaan terpilih) */
    public function opsiUnit(): Collection
    {
        return Unit::when($this->filterPerusahaan !== '', fn ($q) => $q->where('perusahaan_id', $this->filterPerusahaan))
            ->orderBy('nama')->pluck('nama', 'id');
    }

    /** Terapkan pencarian: daftar yang sama dipakai ulang, filter riwayat diabaikan selama mencari. */
    public function cari(): void
    {
        $this->kataCari = trim($this->q);
        $this->halaman = 1;
    }

    public function hapusCari(): void
    {
        $this->q = '';
        $this->kataCari = '';
        $this->halaman = 1;
    }

    public function generateAction(): Action
    {
        return Action::make('generate')
            ->modalHeading('Buat Nomor Surat')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Buat Nomor')
            ->fillForm(['tanggal' => now()->toDateString()])
            ->schema([
                // Jenis surat & unit sebelahan (1 kolom di layar sempit).
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Select::make('jenis')->label('Jenis Surat')->required()->searchable()
                        ->options(collect(JenisSurat::cases())->mapWithKeys(fn ($c) => [$c->value => "{$c->value} — {$c->label()}"])),
                    Select::make('unit_id')->label('Unit')->required()->searchable()
                        ->options(fn () => Unit::with('perusahaan')->orderBy('perusahaan_id')->orderBy('kode')->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->perusahaan->kode_surat} · {$u->kode} — {$u->nama}"])),
                ]),
                DatePicker::make('tanggal')->label('Tanggal Surat')->required()->native(false),
                TextInput::make('judul_surat')->label('Judul Surat')->required()->maxLength(255),
                TextInput::make('perihal')->label('Perihal')->maxLength(255),
                Textarea::make('deskripsi')->label('Deskripsi')->rows(3),
            ])
            ->action(function (array $data) {
                $this->hasilNomor = app(NomorSuratService::class)->generate(
                    Unit::findOrFail($data['unit_id']),
                    JenisSurat::from($data['jenis']),
                    Carbon::parse($data['tanggal']),
                    $data,
                    auth()->user(),
                )->nomor;
            });
    }
}
