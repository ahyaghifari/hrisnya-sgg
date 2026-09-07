<?php

namespace App\Livewire;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\PermissionGroupEnum;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Setting;
use App\Services\Attendance\AttendaceReqService;
use App\Support\TimeInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitPermissionRequest extends Component
{
    use WithFileUploads;

    public ?string $group = null;
    public ?string $permission_type_id = null;
    public ?string $category = null;
    public ?string $date = null;
    public ?string $end_date = null;
    public ?string $start_time = null;
    public ?string $end_time = null;
    public ?string $purpose = null;
    public ?string $location = null;

    public bool $with_substitute = false;
    public ?string $substitute_user_name = null;
    public ?string $substitute_task = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public ?array $usage = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    /** 4 kelompok level 1 - grouping ini konsep UI, bukan data (lihat PermissionGroupEnum). */
    public function getKelompokOptionsProperty(): array
    {
        return collect(PermissionGroupEnum::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all();
    }

    private function currentGroup(): ?PermissionGroupEnum
    {
        return $this->group ? PermissionGroupEnum::from($this->group) : null;
    }

    /** Opsi dropdown level 2 - cuma dipakai kelompok yang hasSubKategori() true. */
    public function getSubJenisOptionsProperty()
    {
        $group = $this->currentGroup();

        if (! $group || ! $group->hasSubKategori()) {
            return collect();
        }

        $categoryValues = array_map(fn ($c) => $c->value, $group->categories());

        return PermissionTypes::where('company_id', Auth::user()->company_id)
            ->whereIn('category', $categoryValues)
            ->get();
    }

    /** Jenis izin (level 2) yang lagi dipilih - dipakai blade buat tampilkan `description` di
     * bawah dropdown kalau ada isinya (mis. syarat, jumlah hari, dokumen pendukung). */
    public function getSelectedTypeProperty(): ?PermissionTypes
    {
        return $this->permission_type_id ? PermissionTypes::find($this->permission_type_id) : null;
    }

    public function updatedGroup(): void
    {
        $this->permission_type_id = null;
        $this->category = null;
        $this->end_date = null;
        $this->start_time = null;
        $this->end_time = null;
        $this->attachments = [];

        $group = $this->currentGroup();

        // Kelompok flat (Tidak Masuk Seharian, Sakit) TIDAK ada master sama sekali - category
        // langsung ke-set dari kelompoknya sendiri, tidak lewat lookup m_permission_types.
        // Kelompok yang hasSubKategori() (Meninggalkan Jam Kerja, Khusus) masih tetap butuh
        // master (dipilih dari dropdown level 2, lihat updatedPermissionTypeId()).
        if ($group && ! $group->hasSubKategori()) {
            $this->category = $group->categories()[0]->value;
        }

        $this->refreshUsage();
    }

    public function updatedPermissionTypeId(): void
    {
        // Prefill end_date dari default_days jenis Izin Khusus yang dipilih - cuma prefill,
        // tetap bebas diubah manual (lihat rencana-izin-empat-kategori.md §2).
        $group = $this->currentGroup();

        if ($group === PermissionGroupEnum::KHUSUS && $this->permission_type_id && $this->date) {
            $type = PermissionTypes::find($this->permission_type_id);

            if ($type?->default_days) {
                $this->end_date = \Carbon\Carbon::parse($this->date)->addDays($type->default_days - 1)->toDateString();
            }
        }

        $this->refreshUsage();
    }

    public function updatedDate(): void
    {
        $this->updatedPermissionTypeId();
        $this->refreshUsage();
    }

    /**
     * Sama seperti info kuota di halaman approval — batas Izin per kelompok (§8) ditampilkan
     * ke pemohon SEBELUM submit. Null kalau kelompok yang dipilih memang tidak ada limit
     * (Sakit & Khusus) - getIzinPeriodeUsage yang menentukan itu, bukan komponen ini.
     */
    private function refreshUsage(): void
    {
        $group = $this->currentGroup();

        $this->usage = $group
            ? app(AttendaceReqService::class)->getIzinPeriodeUsage(Auth::user(), $group, $this->date)
            : null;
    }

    public function toggleSubstitute(): void
    {
        $this->with_substitute = ! $this->with_substitute;

        if (! $this->with_substitute) {
            $this->substitute_user_name = null;
            $this->substitute_task = null;
        }
    }

    private function totalDays(): int
    {
        if (! $this->date) {
            return 0;
        }

        if (! $this->end_date) {
            return 1;
        }

        return \Carbon\Carbon::parse($this->date)->diffInDays(\Carbon\Carbon::parse($this->end_date)) + 1;
    }

    /** Durasi maksimum Izin Meninggalkan Jam Kerja (berlaku sama untuk semua sub-jenisnya:
     * Terlambat Masuk, Pulang Cepat, Meninggalkan Jam Kerja Tengah Hari) - setting
     * izin_meninggalkan_jam_kerja_max_jam, company-scoped, 0 = tidak dibatasi. */
    private function maxDurationRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $this->start_time || ! $value) {
                return;
            }

            $maxJam = (int) Setting::resolve(
                AttendanceSettingEnum::IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM->value,
                Auth::user()->company_id,
                default: (int) AttendanceSettingEnum::IZIN_MENINGGALKAN_JAM_KERJA_MAX_JAM->default()
            );

            if ($maxJam <= 0) {
                return;
            }

            $start = \Carbon\Carbon::parse($this->start_time);
            $end = \Carbon\Carbon::parse($value);
            $jam = $start->diffInMinutes($end) / 60;

            if ($jam > $maxJam) {
                $fail("Durasi izin maksimal {$maxJam} jam.");
            }
        };
    }

    protected function rules(): array
    {
        $group = $this->currentGroup();
        $isRentang = $group?->isRentangTanggal() ?? false;
        $wajibJam = $group === PermissionGroupEnum::MENINGGALKAN_JAM_KERJA;
        // Hari pertama sakit belum wajib lampiran, tapi begitu masuk hari ke-2 (total_days > 1)
        // wajib surat/bukti sakit - dikonfirmasi user 2026-07-31.
        $wajibLampiran = $group === PermissionGroupEnum::SAKIT && $this->totalDays() > 1;
        $butuhMaster = $group?->hasSubKategori() ?? false;

        return [
            'group' => ['required', Rule::in(array_map(fn ($g) => $g->value, PermissionGroupEnum::cases()))],
            // Meninggalkan Jam Kerja & Khusus dipilih dari master (permission_type_id) - kelompok
            // flat (Tidak Masuk Seharian, Sakit) sudah auto-set `category` sendiri, tanpa master.
            'permission_type_id' => [$butuhMaster ? 'required' : 'nullable', 'exists:m_permission_types,id'],
            'category' => [$butuhMaster ? 'nullable' : 'required', 'string'],
            'date' => ['required', 'date'],
            'end_date' => [$isRentang ? 'required' : 'nullable', 'date', 'after_or_equal:date'],
            'start_time' => [$wajibJam ? 'required' : 'nullable', 'date_format:H:i'],
            'end_time' => [
                $wajibJam ? 'required' : 'nullable',
                'date_format:H:i',
                'after:start_time',
                ...($wajibJam ? [$this->maxDurationRule()] : []),
            ],
            'purpose' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'substitute_user_name' => ['nullable', 'string'],
            'substitute_task' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ...($wajibLampiran ? ['attachments' => ['required', 'array', 'min:1']] : []),
        ];
    }

    public function submit(): void
    {
        $this->start_time = TimeInput::normalize($this->start_time);
        $this->end_time = TimeInput::normalize($this->end_time);

        $data = $this->validate();

        try {
            app(AttendaceReqService::class)->storePermissionRequest([
                'type_id' => $data['permission_type_id'] ?? null,
                'category' => $data['category'] ?? null,
                'date' => $data['date'],
                'end_date' => $data['end_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'purpose' => $data['purpose'],
                'location' => $data['location'],
                'substitute_user_name' => $data['substitute_user_name'],
                'substitute_task' => $data['substitute_task'],
                'attachments' => $data['attachments'] ?? null,
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan izin berhasil dikirim')
                ->success()
                ->send();

            $this->redirect(route('my-attendance-requests'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal mengajukan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.submit-permission-request')
            ->layout('components.layout', ['title' => 'Pengajuan Izin']);
    }
}
