<?php

namespace App\Filament\Dokter\Pages;

use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Enum\Doctor\DoctorDocumentType;
use App\Models\Doctor\Doctor;
use App\Models\Doctor\DoctorDocument;
use App\Models\Doctor\Specialty;
use App\Models\Master\Companies;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

/** Beranda panel /dokter: kotak cari + grid kartu dokter; klik kartu = modal layar penuh (data diri + dokumen). */
class Beranda extends Dashboard
{
    protected static ?string $title = 'Beranda';

    protected string $view = 'filament.dokter.pages.beranda';

    public const PER_HALAMAN = 12;

    /** Isi kotak input; $kataCari = yang sudah diterapkan lewat tombol Cari / Enter. */
    public string $q = '';

    public string $kataCari = '';

    public string $filterSpesialisasi = '';

    public string $filterStatus = '';

    public int $halaman = 1;

    public ?int $detailId = null;

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function cari(): void
    {
        $this->kataCari = trim($this->q);
        $this->halaman = 1;
    }

    public function updatedFilterSpesialisasi(): void
    {
        $this->halaman = 1;
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
        $kata = $this->kataCari;

        return Doctor::query()
            ->when($kata !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$kata}%")
                ->orWhere('nip', 'like', "%{$kata}%")
                ->orWhereHas('specialties', fn ($s) => $s->where('nama', 'like', "%{$kata}%"))))
            ->when($this->filterSpesialisasi !== '', fn ($q) => $q->whereHas('specialties', fn ($s) => $s->whereKey($this->filterSpesialisasi)))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('nama');
    }

    /** @return array{rows: \Illuminate\Support\Collection, total: int, halaman: int, terakhir: int, foto: array<string, string>} */
    public function daftar(): array
    {
        $total = $this->query()->count();
        $terakhir = max(1, (int) ceil($total / self::PER_HALAMAN));
        $halaman = min($this->halaman, $terakhir);

        $rows = $this->query()->with('specialties')->withCount('hospitals')
            ->forPage($halaman, self::PER_HALAMAN)->get();

        return ['rows' => $rows, 'total' => $total, 'halaman' => $halaman, 'terakhir' => $terakhir, 'foto' => $this->fotoUrls($rows)];
    }

    /** Foto dokter = users.profile_picture akun HRIS-nya (lintas DB, jadi diambil satu query). @return array<string, string> doctor id => url */
    private function fotoUrls($doctors): array
    {
        $users = User::withoutGlobalScopes()->whereIn('id', $doctors->pluck('hris_user_id')->filter())->get()->keyBy('id');
        $urls = [];

        foreach ($doctors as $doctor) {
            $path = $users->get($doctor->hris_user_id)?->profilePhotoPath();
            if ($path) {
                $urls[$doctor->id] = Storage::disk('public')->url($path);
            }
        }

        // Tanpa foto akun (mis. dokter dari lamaran, akun di-hold): pakai dokumen Pas Foto terbaru
        // (terenkripsi, dibuka lewat route dokumen panel).
        $tanpaFoto = $doctors->pluck('id')->diff(array_keys($urls));
        if ($tanpaFoto->isNotEmpty()) {
            DoctorDocument::whereIn('doctor_id', $tanpaFoto)->where('document_type', DoctorDocumentType::PAS_FOTO->value)
                ->orderBy('id')->get()
                ->each(fn (DoctorDocument $doc) => $urls[$doc->doctor_id] = route('filament.dokter.dokumen.show', $doc));
        }

        return $urls;
    }

    public function spesialisasiOptions(): array
    {
        return Specialty::orderBy('nama')->pluck('nama', 'id')->all();
    }

    public function detail(): ?array
    {
        $doctor = $this->detailId ? Doctor::with(['specialties', 'hospitals', 'documents', 'licenses', 'credentialingCases'])->find($this->detailId) : null;

        if (! $doctor) {
            return null;
        }

        $user = $doctor->hrisUser();
        $rs = Companies::whereIn('id', $doctor->hospitals->pluck('rumah_sakit_id')->merge($doctor->credentialingCases->pluck('rumah_sakit_id')))->pluck('name', 'id');

        return [
            'doctor' => $doctor,
            'user' => $user,
            'foto' => $this->fotoUrls(collect([$doctor]))[$doctor->id] ?? null,
            'rs' => $rs,
            'editUrl' => DoctorResource::getUrl('edit', ['record' => $doctor]),
        ];
    }

    public function tambahUrl(): string
    {
        return DoctorResource::getUrl('create');
    }
}
