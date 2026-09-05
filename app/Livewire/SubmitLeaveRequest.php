<?php

namespace App\Livewire;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Master\Attendance\LeaveTypes;
use App\Services\Attendance\AttendaceReqService;
use App\Services\Attendance\ScheduleResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitLeaveRequest extends Component
{
    use WithFileUploads;

    public ?string $leave_type_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $reason = null;

    /** Lampiran surat dokter/bidan — wajib untuk cuti khusus (haid/melahirkan/keguguran). */
    public array $attachments = [];

    public ?array $balance = null;
    public ?array $periode = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function getLeaveTypesProperty()
    {
        $isFemale = Auth::user()->profile?->gender === 'perempuan';

        // Jendela pengajuan Cuti Tahunan baru buka 1 bulan sebelum masa kerja minimal
        // terpenuhi (lihat AttendaceReqService::assertCutiTahunanEligibility) - sebelum itu
        // Cuti Tahunan disaring dari dropdown sama sekali, bukan cuma warning. null = tidak
        // dibatasi (setting 0/tidak diset, atau joining_date kosong).
        $windowOpen = app(AttendaceReqService::class)->cutiTahunanEligibleDate(Auth::user())?->subMonth();

        return LeaveTypes::where('company_id', Auth::user()->company_id)
            ->get()
            ->reject(function ($type) use ($isFemale, $windowOpen) {
                $cat = LeaveTypeCategoryEnum::tryFrom($type->category ?? '');

                // Cuti haid/melahirkan/keguguran cuma buat karyawan perempuan.
                if ($cat?->isFemaleOnly() && ! $isFemale) {
                    return true;
                }

                if ($cat === LeaveTypeCategoryEnum::TAHUNAN && $windowOpen && now()->lt($windowOpen)) {
                    return true;
                }

                return false;
            })
            ->values();
    }

    public function getTenureWarningProperty(): ?string
    {
        return app(AttendaceReqService::class)->getMinimumTenureWarning(Auth::user());
    }

    /** Kenapa Cuti Tahunan tidak muncul di dropdown (belum masuk jendela pengajuan) - null
     *  kalau memang tidak dibatasi atau jendelanya sudah buka. */
    public function getCutiTahunanClosedNoteProperty(): ?string
    {
        $eligibleDate = app(AttendaceReqService::class)->cutiTahunanEligibleDate(Auth::user());
        $windowOpen = $eligibleDate?->copy()->subMonth();

        if (! $windowOpen || now()->gte($windowOpen)) {
            return null;
        }

        return "Cuti Tahunan belum bisa diajukan - baru buka mulai {$windowOpen->translatedFormat('d M Y')} (1 bulan sebelum masa kerja minimal terpenuhi pada {$eligibleDate->translatedFormat('d M Y')}).";
    }

    /** Kategori cuti khusus dari jenis cuti yang dipilih (null kalau cuti biasa/tahunan). */
    public function getSelectedCategoryProperty(): ?LeaveTypeCategoryEnum
    {
        if (! $this->leave_type_id) {
            return null;
        }

        $type = LeaveTypes::find($this->leave_type_id);

        return $type ? LeaveTypeCategoryEnum::tryFrom($type->category ?? '') : null;
    }

    public function getRequiresAttachmentProperty(): bool
    {
        return (bool) $this->selectedCategory?->requiresAttachment();
    }

    /**
     * Jumlah hari pengajuan — tanggal mulai == tanggal selesai = 1 hari. MELAHIRKAN/KEGUGURAN
     * (LeaveTypeCategoryEnum::countsCalendarDays()) dihitung hari KALENDER (UU 13/2003 Ps.82);
     * kategori lain dihitung hari kerja saja (off_day jadwal user tidak ikut, ScheduleResolver).
     * null kalau tanggal belum lengkap. Sama persis dengan AttendaceReqService::countLeaveDays()
     * yang jadi total_days saat submit.
     */
    public function getWorkingDaysProperty(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        if ($this->end_date === $this->start_date) {
            return 1;
        }

        if ($this->selectedCategory?->countsCalendarDays()) {
            return \Carbon\Carbon::parse($this->start_date)->diffInDays(\Carbon\Carbon::parse($this->end_date)) + 1;
        }

        return app(ScheduleResolver::class)->workingDaysBetween(Auth::user(), $this->start_date, $this->end_date);
    }

    public function updatedLeaveTypeId(): void
    {
        $this->attachments = [];
        $this->refreshQuota();
    }

    public function updatedStartDate(): void
    {
        $this->refreshQuota();
    }

    /**
     * Sama seperti info kuota di halaman approval (§8) — kuota tahun (LeaveTypes::quota_days)
     * DAN batas per-periode (setting cuti_tahunan_max_per_bulan, kalau leave type-nya kategori
     * Tahunan) ditampilkan ke pemohon SEBELUM submit, bukan cuma diketahui approver.
     */
    private function refreshQuota(): void
    {
        if (! $this->leave_type_id) {
            $this->balance = null;
            $this->periode = null;
            return;
        }

        $service = app(AttendaceReqService::class);

        $this->balance = $service->getLeaveUserBalance(Auth::id(), $this->leave_type_id, $this->start_date);
        $this->periode = $service->getCutiTahunanPeriodeUsage(Auth::user(), $this->leave_type_id, $this->start_date);
    }

    protected function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:m_leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            'attachments' => [$this->requiresAttachment ? 'required' : 'nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function messages(): array
    {
        return [
            'attachments.required' => 'Jenis cuti ini wajib melampirkan surat dokter/bidan.',
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        // Guard server-side — jenis cuti female-only tidak boleh diajukan karyawan non-perempuan
        // (walau di dropdown sudah disembunyikan).
        $type = LeaveTypes::find($data['leave_type_id']);
        $cat = $type ? LeaveTypeCategoryEnum::tryFrom($type->category ?? '') : null;

        if ($cat?->isFemaleOnly() && Auth::user()->profile?->gender !== 'perempuan') {
            Notification::make()
                ->title('Jenis cuti ini hanya untuk karyawan perempuan.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(AttendaceReqService::class)->storeLeaveRequest([
                'type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'attachments' => $data['attachments'] ?? null,
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan cuti berhasil dikirim')
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
        return view('livewire.submit-leave-request')->layout('components.layout', ['title' => 'Pengajuan Cuti']);
    }
}
