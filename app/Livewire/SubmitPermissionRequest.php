<?php

namespace App\Livewire;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeEnum;
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
    public bool $submitted = false; // true sesudah sukses, sampai redirect selesai (cegah kirim ganda)
    public ?string $permission_type = null; // key PermissionTypeEnum (level 2: Meninggalkan Jam Kerja/Khusus)
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

    /** 4 kelompok level 1 - grouping ini konsep UI, bukan data (lihat PermissionGroupEnum).
     * Label Tidak Masuk/Sakit beda buat 2 company tertentu (instruksi user 2026-09-30). */
    public function getKelompokOptionsProperty(): array
    {
        $companyId = Auth::user()->company_id;

        return collect(PermissionGroupEnum::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label($companyId)])->all();
    }

    /** AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB - company-scoped, default OFF. Ditampilkan
     *  ke pemohon SEBELUM submit (asterisk + validasi form), enforcement sebenarnya tetap di
     *  AttendaceReqService::assertKeteranganWajibDiisi() (jaring pengaman server-side, dipakai
     *  juga oleh submit lewat mobile API). */
    public function getPurposeRequiredProperty(): bool
    {
        return (bool) Setting::resolve(
            AttendanceSettingEnum::KETERANGAN_CUTI_IZIN_WAJIB->value,
            Auth::user()->company_id,
            default: false
        );
    }

    protected function messages(): array
    {
        return [
            'purpose.required' => 'Keterangan izin wajib diisi.',
        ];
    }

    private function currentGroup(): ?PermissionGroupEnum
    {
        return $this->group ? PermissionGroupEnum::from($this->group) : null;
    }

    /** Opsi dropdown level 2 - cuma dipakai kelompok yang hasSubKategori() true. */
    /** @return array<int, PermissionTypeEnum> */
    public function getSubJenisOptionsProperty(): array
    {
        $group = $this->currentGroup();

        if (! $group || ! $group->hasSubKategori()) {
            return [];
        }

        return PermissionTypeEnum::forGroup($group, Auth::user()->company_id);
    }

    /** Jenis izin (level 2) yang lagi dipilih - dipakai blade buat tampilkan `description` di
     * bawah dropdown kalau ada isinya (mis. syarat, jumlah hari, dokumen pendukung). */
    public function getSelectedTypeProperty(): ?PermissionTypeEnum
    {
        return $this->permission_type ? PermissionTypeEnum::tryFrom($this->permission_type) : null;
    }

    public function updatedGroup(): void
    {
        $this->permission_type = null;
        $this->category = null;
        $this->end_date = null;
        $this->start_time = null;
        $this->end_time = null;
        $this->attachments = [];

        $group = $this->currentGroup();

        // Kelompok flat (Tidak Masuk Seharian, Sakit) TIDAK ada master sama sekali - category
        // langsung ke-set dari kelompoknya sendiri, tanpa pilihan jenis level 2.
        // Kelompok yang hasSubKategori() (Meninggalkan Jam Kerja, Khusus) memilih jenis dari
        // dropdown level 2 (lihat updatedPermissionType()).
        if ($group && ! $group->hasSubKategori()) {
            $this->category = $group->categories()[0]->value;
        }

        $this->refreshUsage();
    }

    public function updatedPermissionType(): void
    {
        // Prefill end_date dari default_days jenis Izin Khusus yang dipilih - cuma prefill,
        // tetap bebas diubah manual (lihat rencana-izin-empat-kategori.md §2).
        $group = $this->currentGroup();

        if ($group === PermissionGroupEnum::KHUSUS && $this->permission_type && $this->date) {
            $days = PermissionTypeEnum::tryFrom($this->permission_type)?->defaultDays();

            if ($days) {
                $this->end_date = \Carbon\Carbon::parse($this->date)->addDays($days - 1)->toDateString();
            }
        }

        $this->refreshUsage();
    }

    public function updatedDate(): void
    {
        $this->updatedPermissionType();
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

    /** Section Lampiran tampil untuk Sakit & Khusus; Meninggalkan Jam Kerja juga boleh melampirkan
     * (opsional) KHUSUS 2 company override (Banjarbaru/Barabai) - instruksi user 2026-10-01. */
    public function getLampiranTersediaProperty(): bool
    {
        return in_array($this->group, [PermissionGroupEnum::SAKIT->value, PermissionGroupEnum::KHUSUS->value], true)
            || ($this->group === PermissionGroupEnum::MENINGGALKAN_JAM_KERJA->value
                && PermissionGroupEnum::usesLabelOverride(Auth::user()->company_id));
    }

    /** Teks label section Lampiran - KHUSUS 2 company override (Banjarbaru/Barabai) lampiran
     * Sakit WAJIB dari hari pertama (bukan nunggu >1 hari), teksnya polos tanpa syarat hari lagi
     * (instruksi user 2026-09-30). Company lain tetap kondisional seperti semula. */
    public function getLampiranLabelSuffixProperty(): string
    {
        if ($this->group !== PermissionGroupEnum::SAKIT->value) {
            return ' (opsional)';
        }

        if (PermissionGroupEnum::usesLabelOverride(Auth::user()->company_id)) {
            return '';
        }

        return ' (wajib kalau > 2 hari)';
    }

    /** Tanda bintang wajib di label section - cuma buat 2 company override (Sakit wajib pasti
     * dari hari pertama). Company lain kondisional (teks "(wajib kalau > 2 hari)" sudah cukup
     * jelas, tidak perlu bintang karena wajibnya tergantung tanggal yang belum tentu diisi). */
    public function getLampiranWajibProperty(): bool
    {
        return $this->group === PermissionGroupEnum::SAKIT->value
            && PermissionGroupEnum::usesLabelOverride(Auth::user()->company_id);
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
        // wajib surat/bukti sakit - dikonfirmasi user 2026-07-31. KHUSUS 2 company override
        // (Banjarbaru/Barabai, lihat HasCompanyLabelOverride) - Sakit di company itu relabel jadi
        // "Izin Sakit Bersurat", jadi lampiran WAJIB dari hari pertama, tidak nunggu hari ke-2
        // (instruksi user 2026-09-30).
        $wajibLampiran = $group === PermissionGroupEnum::SAKIT
            && (PermissionGroupEnum::usesLabelOverride(Auth::user()->company_id) || $this->totalDays() > 1);
        $butuhMaster = $group?->hasSubKategori() ?? false;

        return [
            'group' => ['required', Rule::in(array_map(fn ($g) => $g->value, PermissionGroupEnum::cases()))],
            // Meninggalkan Jam Kerja & Khusus memilih jenis level 2 (permission_type, harus milik
            // kelompoknya) - kelompok flat (Tidak Masuk Seharian, Sakit) auto-set `category` sendiri.
            'permission_type' => $butuhMaster
                ? ['required', Rule::in(array_map(fn (PermissionTypeEnum $t) => $t->value, $group ? PermissionTypeEnum::forGroup($group, Auth::user()->company_id) : []))]
                : ['nullable'],
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
            'purpose' => [$this->purposeRequired ? 'required' : 'nullable', 'string'],
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
        if ($this->submitted) {
            return;
        }

        $this->start_time = TimeInput::normalize($this->start_time);
        $this->end_time = TimeInput::normalize($this->end_time);

        $data = $this->validate();

        try {
            app(AttendaceReqService::class)->storePermissionRequest([
                'type_id' => $data['permission_type'] ?? null,
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

            // Tombol tetap terkunci sampai halaman berpindah - respons sukses sudah selesai (loading
            // berakhir) tapi navigasi ke daftar pengajuan belum, jangan sampai bisa diklik lagi.
            $this->submitted = true;

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
