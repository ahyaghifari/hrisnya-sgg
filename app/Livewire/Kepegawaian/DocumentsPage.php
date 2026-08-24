<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\UserDocumentType;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\User\UserDocuments;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Padanan portal dari admin UserDocumentResource (dulu getNavigationItems() per tipe dokumen,
 * lihat app/Filament/Resources/UserDocuments/) — di portal cuma bisa dilihat/download (tab
 * "Dokumen" di Data Diri/MyStaff), belum ada tempat kelola (upload/edit/hapus) sampai halaman
 * ini. Pola nav kiri/kanan reuse .doc-type-nav/.doc-type-btn yang sudah ada di tab Dokumen.
 */
#[Layout('components.layout', ['title' => 'Dokumen Kepegawaian'])]
class DocumentsPage extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $documentType = 'resignation';

    #[Url]
    public string $status = 'aktif';

    /** @var array<int, UserDocumentType> */
    public array $documentTypes = [
        UserDocumentType::RESIGNATION,
        UserDocumentType::RETIREMENT,
        UserDocumentType::ASSIGNMENT,
        UserDocumentType::WORK_CERTIFICATE,
    ];

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', UserDocuments::class), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function setType(string $type): void
    {
        $this->documentType = $type;
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    protected function baseQuery()
    {
        // Default cuma dokumen aktif (query normal, exclude soft-deleted) — "Tidak Aktif"
        // khusus nampilin yang sudah dihapus, buat dipulihkan kalau perlu.
        $query = ($this->status === 'tidak_aktif' ? UserDocuments::onlyTrashed() : UserDocuments::query())
            ->where('document_type', $this->documentType)
            ->with(['user.company']);

        if ($this->companyId) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId));
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function getDocumentCountsProperty()
    {
        $query = $this->status === 'tidak_aktif' ? UserDocuments::onlyTrashed() : UserDocuments::query();

        if ($this->companyId) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', $this->companyId));
        }

        return $query->selectRaw('document_type, count(*) as total')
            ->groupBy('document_type')
            ->pluck('total', 'document_type');
    }

    public function getDocumentsProperty()
    {
        return $this->baseQuery()->orderByDesc('issued_date')->paginate(10)->appends([
            'companyId' => $this->companyId,
            'search' => $this->search,
            'documentType' => $this->documentType,
            'status' => $this->status,
        ]);
    }

    public function delete(string $id): void
    {
        $document = UserDocuments::findOrFail($id);

        abort_unless($this->canManage($document), 403);
        abort_unless(Auth::user()->can('delete', $document), 403);

        // Soft-delete (UserDocuments::class pakai SoftDeletes) — berkas fisiknya SENGAJA
        // tidak ikut dihapus supaya kalau baris ini dipulihkan lagi, link ke file-nya masih
        // valid. Baru bersihkan berkas kalau nanti ada tombol hapus permanen/force delete.
        $document->delete();
    }

    public function restore(string $id): void
    {
        $document = UserDocuments::onlyTrashed()->findOrFail($id);

        abort_unless($this->canManage($document), 403);
        abort_unless(Auth::user()->can('restore', $document), 403);

        $document->restore();
    }

    protected function canManage(UserDocuments $document): bool
    {
        if (PermissionHelper::hasGlobalRole(Auth::user())) {
            return true;
        }

        // withTrashed() — pemilik dokumen bisa saja karyawan yang sudah dinonaktifkan,
        // relasi default exclude soft-deleted jadi company-check ini bisa keliru.
        $documentOwner = $document->user()->withTrashed()->first();

        return $documentOwner?->company_id === Auth::user()->company_id;
    }

    public function render()
    {
        return view('livewire.kepegawaian.documents-page', [
            'canBrowseAllCompanies' => Auth::user()->canFilterByCompany(),
        ]);
    }
}
