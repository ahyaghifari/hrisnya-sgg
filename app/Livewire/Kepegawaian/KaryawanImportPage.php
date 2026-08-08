<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Imports\Kepegawaian\KaryawanAccountImport;
use App\Models\Master\Companies;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import akun DASAR karyawan (nama/email/nip saja, TIDAK langsung bikin akun) - upload file
 * langsung dipratinjau (BELUM ditulis ke database), dikelompokkan 3 kategori:
 * - Baru: NIP di file, belum ada di sistem -> dibuatkan akun baru.
 * - Berubah: NIP ketemu, tapi nama/email beda dari file -> ditimpa.
 * - Tidak Ada di File: NIP ada di sistem (company ini) tapi TIDAK disebut di file -
 *   MURNI informasional (instruksi user 2026-08-08), tidak ada aksi apapun buat kategori ini.
 * Baru submit (confirmImport) yang benar-benar menulis ke database. Pola upload+preview+confirm
 * sama persis PayrollsPage::previewImport()/confirmImport() (BonImport).
 */
#[Layout('components.layout', ['title' => 'Import Akun Karyawan'])]
class KaryawanImportPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;

    public $file = null;

    public ?string $storedPath = null;

    /** @var array<int, array<string, mixed>> */
    public array $previewRows = [];

    /** @var array<int, array{id: string, name: string, email: string, nip: string}> */
    public array $missingRows = [];

    public array $importErrors = [];

    public ?int $successCount = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::IMPORT_KARYAWAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        // Pratinjau lama scoped ke company sebelumnya - tidak valid lagi begitu company ganti.
        $this->discardPreview();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** File baru dipilih (wire:model) -> langsung parse & tampilkan pratinjau, JANGAN simpan dulu. */
    public function updatedFile(): void
    {
        $this->importErrors = [];
        $this->successCount = null;
        $this->previewImport();
    }

    private function previewImport(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::IMPORT_KARYAWAN->value), 403);

        $this->previewRows = [];
        $this->missingRows = [];
        $this->discardStoredFile();

        $this->validate([
            'companyId' => 'required',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ], [], [
            'companyId' => 'perusahaan',
            'file' => 'file Excel',
        ]);

        $this->storedPath = $this->file->store('temp-karyawan-account-imports');

        $import = new KaryawanAccountImport($this->companyId, preview: true);
        Excel::import($import, Storage::path($this->storedPath));

        $this->previewRows = $import->getPreview();
        $this->importErrors = $import->getErrors();
        $this->missingRows = $this->findMissingRows($import->getSeenNips());
    }

    /** NIP yang ada di sistem (company terpilih) tapi TIDAK kebaca dari file sama sekali. */
    private function findMissingRows(array $seenNips): array
    {
        return User::where('company_id', $this->companyId)
            ->whereNotIn('nip', $seenNips)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'nip'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'nip' => $u->nip])
            ->all();
    }

    public function getNewRowsProperty(): array
    {
        return array_values(array_filter($this->previewRows, fn ($r) => $r['status'] === 'baru'));
    }

    public function getChangedRowsProperty(): array
    {
        return array_values(array_filter($this->previewRows, fn ($r) => $r['status'] === 'berubah'));
    }

    public function getUnchangedCountProperty(): int
    {
        return count(array_filter($this->previewRows, fn ($r) => $r['status'] === 'tidak_berubah'));
    }

    /** Tombol "Konfirmasi Import" di pratinjau -> baru di sini datanya ditulis ke database. */
    public function confirmImport(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::IMPORT_KARYAWAN->value), 403);

        if ($this->storedPath === null) {
            return;
        }

        $import = new KaryawanAccountImport($this->companyId, preview: false);

        try {
            Excel::import($import, Storage::path($this->storedPath));
        } finally {
            $this->discardStoredFile();
        }

        $this->importErrors = $import->getErrors();
        $this->successCount = $import->getSuccessCount();
        $this->previewRows = [];
        $this->missingRows = [];
        $this->reset('file');

        if ($this->importErrors === []) {
            Notification::make()
                ->title('Import berhasil')
                ->body("{$this->successCount} akun berhasil dibuat/diperbarui.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("{$this->successCount} akun berhasil, " . count($this->importErrors) . ' baris gagal')
                ->body('Lihat detail error di bawah.')
                ->warning()
                ->send();
        }
    }

    public function cancelPreview(): void
    {
        $this->discardPreview();
        $this->reset('file');
    }

    private function discardPreview(): void
    {
        $this->previewRows = [];
        $this->missingRows = [];
        $this->importErrors = [];
        $this->successCount = null;
        $this->discardStoredFile();
    }

    private function discardStoredFile(): void
    {
        if ($this->storedPath !== null) {
            Storage::delete($this->storedPath);
            $this->storedPath = null;
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.karyawan-import-page');
    }
}
