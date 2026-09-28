<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Models\Coaching\Coaching;
use App\Models\Master\Companies;
use App\Models\Sp\EmployeeSp;
use App\Models\User;
use App\Services\Sp\SpService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Buat SP manual — portal, padanan CreateCoachingPage. HR pilih sendiri apakah SP ini punya
 * sumber Coaching (opsional) atau murni manual (instruksi user 2026-09-23: Coaching & SP dipisah
 * total). Restrukturisasi 2026-09-25 (lihat diagram ER): surat SP + poin pelanggaran (sp_items,
 * bebas per baris, menggantikan reason_id/reason_note) + Berita Acara (sp_reports) dibuat
 * sekaligus dalam satu form. end_date & refresh cache didelegasikan ke SpService.
 */
#[Layout('components.layout', ['title' => 'Buat SP'])]
class CreateSpPage extends Component
{
    public ?string $companyId = null;
    public ?string $user_id = null;
    public ?string $nomor = null;
    public ?string $sp_level = null;
    public ?string $knows_by = null;
    public ?string $start_date = null;

    /** @var array<int, string> Poin pelanggaran - bebas per baris (instruksi user 2026-09-25). */
    public array $items = [''];

    // Berita Acara (instruksi user 2026-09-25) - opsional, catatan investigasi HR-internal.
    public ?string $kejadian = null;
    public ?string $dasar_klarifikasi = null;
    public ?string $subject_clarification = null;
    public ?string $analysis = null;
    public ?string $kesimpulan = null;
    public ?string $followup_plan = null;

    /** Opsional - SP ini berdasarkan Coaching mana (kalau ada). Kosong = source_type MANUAL. */
    public ?string $coaching_id = null;

    /** TTE Fase 1 (instruksi user 2026-09-28) - konfirmasi password sebelum SP benar-benar
     *  disimpan & ditandatangani, gantinya wire:confirm browser dialog yang cuma "OK/Cancel"
     *  tanpa bukti siapa yang menyetujui. Ini konsen EKSPLISIT created_by sendiri (Pasal 59
     *  ayat 3 butir 6 PP 71/2019) - beda dari knows_by yang konfirmasi terpisah lewat
     *  SignDocumentsPage begitu dia login sendiri. */
    public bool $confirmingSign = false;

    public ?string $signPassword = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('Create:EmployeeSp'), 403);

        if (! $this->canBrowseAllCompanies) {
            $this->companyId = $user->company_id;
        }

        $this->start_date = today()->toDateString();

        // Prefill dari tombol pintasan "Buat SP" di CoachingDetailPage (Tindak Lanjut = Perlu SP,
        // instruksi user 2026-09-23) - cuma kenyamanan UI, coaching_id tetap divalidasi ulang di
        // save() (harus milik karyawan yang sama).
        if ($prefillUserId = request()->query('user_id')) {
            $prefillUser = User::find($prefillUserId);

            if ($prefillUser) {
                if ($this->canBrowseAllCompanies) {
                    $this->companyId = $prefillUser->company_id;
                }

                $this->user_id = $prefillUser->id;

                $prefillCoachingId = request()->query('coaching_id');
                if ($prefillCoachingId && Coaching::where('id', $prefillCoachingId)->where('user_id', $prefillUser->id)->exists()) {
                    $this->coaching_id = $prefillCoachingId;
                }
            }
        }
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function updatedCompanyId(): void
    {
        if (! $this->canBrowseAllCompanies) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->user_id = null;
        $this->coaching_id = null;
    }

    public function updatedUserId(): void
    {
        $this->coaching_id = null;
    }

    /** Coaching milik karyawan yang dipilih - dropdown "Sumber dari Coaching" cuma masuk akal
     * kalau karyawan sudah dipilih. */
    public function getCoachingOptionsProperty()
    {
        if (! $this->user_id) {
            return collect();
        }

        return Coaching::where('user_id', $this->user_id)
            ->latest()
            ->get()
            ->mapWithKeys(fn (Coaching $c) => [$c->id => "{$c->title} ({$c->created_at->translatedFormat('d M Y')})"]);
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getEmployeeOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::where('company_id', $this->companyId)->orderBy('name')->pluck('name', 'id');
    }

    /** "Mengetahui" - siapa pun di company yang sama (pimpinan/HC Supervisor/Manager), sama
     * daftar dengan karyawan (instruksi user 2026-09-25). */
    public function getKnowsByOptionsProperty()
    {
        return $this->employeeOptions;
    }

    public function getSpLevelOptionsProperty(): array
    {
        return collect(SpLevel::cases())->mapWithKeys(fn ($level) => [$level->value => $level->getLabel()])->all();
    }

    /** Preview "Berlaku sampai" live di form, dihitung sama persis kayak save() (SpService::resolveEndDate)
     * biar HC lihat dulu sebelum submit (instruksi user 2026-09-25). */
    public function getEndDatePreviewProperty(): ?string
    {
        if (! $this->sp_level || ! $this->start_date) {
            return null;
        }

        $companyId = $this->companyId ?? Auth::user()->company_id;

        return app(SpService::class)->resolveEndDate(SpLevel::from($this->sp_level), $companyId, $this->start_date);
    }

    public function addItem(): void
    {
        $this->items[] = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if (empty($this->items)) {
            $this->items = [''];
        }
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'nomor' => ['required', 'string', 'max:255'],
            'sp_level' => ['required', 'in:' . implode(',', array_column(SpLevel::cases(), 'value'))],
            'knows_by' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string'],
            'coaching_id' => ['nullable', 'exists:coachings,id'],
            'kejadian' => ['nullable', 'string'],
            'dasar_klarifikasi' => ['nullable', 'string'],
            'subject_clarification' => ['nullable', 'string'],
            'analysis' => ['nullable', 'string'],
            'kesimpulan' => ['nullable', 'string'],
            'followup_plan' => ['nullable', 'string'],
        ];
    }

    /** Langkah 1 - validasi form seperti biasa, lalu tampilkan langkah konfirmasi password
     *  (bukan langsung simpan). Nilai form TETAP di properti public yang sama, tidak perlu
     *  disalin ke tempat lain - Livewire menjaga state-nya antar render dalam 1 kunjungan. */
    public function chooseConfirmSign(): void
    {
        $this->validate();

        $this->signPassword = null;
        $this->resetErrorBag();
        $this->confirmingSign = true;
    }

    public function cancelConfirmSign(): void
    {
        $this->confirmingSign = false;
        $this->signPassword = null;
    }

    /** Langkah 2 - password Anda sendiri sebagai bukti konsen eksplisit created_by, BARU
     *  benar-benar disimpan & ditandatangani. Re-validasi form penuh lagi (jaring pengaman
     *  server-side kalau ada yang berubah antara langkah 1 dan 2). */
    public function save(): void
    {
        $data = $this->validate();

        $this->validate(['signPassword' => ['required', 'string']], [], ['signPassword' => 'Password']);

        if (! Hash::check($this->signPassword, Auth::user()->password)) {
            $this->addError('signPassword', 'Password salah.');

            return;
        }

        $auth = Auth::user();

        $companyId = $this->companyId ?? $auth->company_id;
        $level = SpLevel::from($data['sp_level']);

        // Coaching cuma boleh dipilih kalau memang punya karyawan yang sama - cegah ditembus
        // lewat wire:model manual pakai id coaching orang lain.
        $coachingId = $data['coaching_id'] ?? null;
        if ($coachingId) {
            abort_unless(Coaching::where('id', $coachingId)->where('user_id', $data['user_id'])->exists(), 403);
        }

        $sp = DB::transaction(function () use ($data, $auth, $companyId, $level, $coachingId) {
            $sp = EmployeeSp::create([
                'nomor' => $data['nomor'],
                'user_id' => $data['user_id'],
                'company_id' => $companyId,
                'sp_level' => $level->value,
                'source_type' => $coachingId ? SpSourceType::COACHING->value : SpSourceType::MANUAL->value,
                'source_id' => $coachingId,
                'start_date' => $data['start_date'],
                'end_date' => app(SpService::class)->resolveEndDate($level, $companyId, $data['start_date']),
                'created_by' => $auth->id,
                'knows_by' => $data['knows_by'],
            ]);

            foreach (array_values($data['items']) as $index => $text) {
                $sp->items()->create(['text' => $text, 'sort_order' => $index]);
            }

            if (filled($data['kejadian']) || filled($data['dasar_klarifikasi']) || filled($data['subject_clarification']) || filled($data['analysis']) || filled($data['kesimpulan']) || filled($data['followup_plan'])) {
                $sp->report()->create([
                    'kejadian' => $data['kejadian'],
                    'dasar_klarifikasi' => $data['dasar_klarifikasi'],
                    'subject_clarification' => $data['subject_clarification'],
                    'analysis' => $data['analysis'],
                    'kesimpulan' => $data['kesimpulan'],
                    'followup_plan' => $data['followup_plan'],
                ]);
            }

            return $sp;
        });

        app(SpService::class)->afterIssued($sp);

        Notification::make()->title('SP berhasil diterbitkan')->success()->send();

        $this->redirect(route('sp.show', $sp), navigate: true);
    }

    public function render()
    {
        return view('livewire.sp.create-sp-page');
    }
}
