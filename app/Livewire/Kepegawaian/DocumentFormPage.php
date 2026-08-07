<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\UserDocumentType;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserDocuments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form Tambah/Edit Dokumen Kepegawaian — satu komponen dipakai untuk 2 route (create tanpa
 * $record, edit dengan $record), field & aturan visibility field kondisional (retirement_*,
 * reason, set_in) diambil dari App\Filament\Resources\UserDocuments\Schemas\UserDocumentForm.
 */
#[Layout('components.layout', ['title' => 'Dokumen Kepegawaian'])]
class DocumentFormPage extends Component
{
    use WithFileUploads;

    public ?UserDocuments $record = null;

    public ?string $company_id = null;
    public ?string $user_id = null;
    public string $document_type = 'other';
    public string $document_number = '';
    public ?string $issued_date = null;
    public $file = null;

    public ?string $retirement_date = null;
    public ?string $retirement_type = null;
    public string $reason = '';
    public string $set_in = '';

    public function mount(?UserDocuments $record = null): void
    {
        if (! PermissionHelper::hasGlobalRole(Auth::user())) {
            $this->company_id = Auth::user()->company_id;
        }

        if ($record?->exists) {
            abort_unless($this->canManage($record), 403);
            abort_unless(Auth::user()->can('update', $record), 403);

            $this->record = $record;
            $this->user_id = $record->user_id;
            // withTrashed() — karyawan pemilik dokumen bisa saja sudah dinonaktifkan/dihapus
            // sejak dokumen ini dibuat (mis. dokumen pengunduran diri), relasi `user()` biasa
            // ikut exclude soft-deleted jadi company_id-nya keliru jadi null.
            $this->company_id = $record->user()->withTrashed()->first()?->company_id ?? $this->company_id;
            $this->document_type = $record->document_type;
            $this->document_number = $record->document_number;
            $this->issued_date = $record->issued_date?->toDateString();
            $this->retirement_date = $record->details['retirement_date'] ?? null;
            $this->retirement_type = $record->details['retirement_type'] ?? null;
            $this->reason = $record->details['reason'] ?? '';
            $this->set_in = $record->details['set_in'] ?? '';
        } else {
            abort_unless(Auth::user()->can('create', UserDocuments::class), 403);

            $this->document_type = request()->query('type', 'other');
        }
    }

    protected function canManage(UserDocuments $document): bool
    {
        if (PermissionHelper::hasGlobalRole(Auth::user())) {
            return true;
        }

        // withTrashed() — sama seperti mount(), pemilik dokumen bisa saja karyawan yang
        // sudah dinonaktifkan, jangan sampai company-check ini ikut salah gara-gara relasi
        // default exclude soft-deleted.
        $documentOwner = $document->user()->withTrashed()->first();

        return $documentOwner?->company_id === Auth::user()->company_id;
    }

    public function updatedCompanyId(): void
    {
        $this->user_id = null;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getUserOptionsProperty()
    {
        $query = User::query();

        if ($this->company_id) {
            $query->where('company_id', $this->company_id);
        }

        $options = $query->orderBy('name')->pluck('name', 'id');

        // Kalau lagi edit dan karyawan yang di-assign ke dokumen ini sudah dinonaktifkan/
        // dihapus (soft-delete), tetap tampilkan dia di opsi (ditandai "nonaktif") — supaya
        // select tidak kosong cuma karena karyawannya sudah bukan aktif lagi.
        if ($this->record && $this->user_id && ! $options->has($this->user_id)) {
            $formerEmployee = User::withTrashed()->find($this->user_id);
            if ($formerEmployee) {
                $options->put($formerEmployee->id, $formerEmployee->name . ' (nonaktif)');
            }
        }

        return $options;
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'document_number' => ['required', 'string', 'max:255'],
            'issued_date' => ['required', 'date'],
            'file' => [$this->record ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'retirement_date' => ['nullable', 'date'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = User::find($this->user_id);

        $details = match ($this->document_type) {
            UserDocumentType::RETIREMENT->value => array_filter([
                'retirement_date' => $this->retirement_date,
                'retirement_type' => $this->retirement_type,
                'reason' => $this->reason,
                'set_in' => $this->set_in,
            ]),
            UserDocumentType::RESIGNATION->value => array_filter([
                'reason' => $this->reason,
            ]),
            default => [],
        };

        $data = [
            'user_id' => $this->user_id,
            'user_name' => $user?->name,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'issued_date' => $this->issued_date,
            'details' => $details,
            'uploaded_by_user' => false,
        ];

        if ($this->file) {
            $data['file_path'] = $this->file->store('user-documents/' . $this->user_id, 'public');
        }

        if ($this->record) {
            if ($this->file && $this->record->file_path) {
                Storage::disk('public')->delete($this->record->file_path);
            }

            $this->record->update($data);
        } else {
            UserDocuments::create($data);
        }

        \Filament\Notifications\Notification::make()
            ->title($this->record ? 'Dokumen berhasil diperbarui' : 'Dokumen berhasil ditambahkan')
            ->success()
            ->send();

        $this->redirect(route('kepegawaian.dokumen', ['type' => $this->document_type]), navigate: true);
    }

    public function render()
    {
        return view('livewire.kepegawaian.document-form-page', [
            'documentTypeLabel' => UserDocumentType::tryFrom($this->document_type)?->label() ?? $this->document_type,
            'canBrowseAllCompanies' => PermissionHelper::hasGlobalRole(Auth::user()),
        ]);
    }
}
